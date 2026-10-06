<?php

namespace Yiendos\MySitesIde;

use Dotenv\Dotenv;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\InteractsWithZapApi; 

class ZapHudCommand extends Command
{
    use InteractsWithZapApi;

    /**
     * Configures each ZAP session Webswing starts (scan limits, database rules,
     * and with a target: context import, proxy exclusion, Protected mode) by
     * polling the API from inside the container - see the script for why.
     * Mounted read-only from zaproxy/scripts/ (zaproxy/docker-compose.yml).
     */
    private const HUD_WATCHER = '/zap/scripts/hud-watcher.sh';

    /**
     * The ability to configure the console command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('ide:zap-hud')
            ->setDescription('Launch the OWASP ZAP Desktop UI (webswing) in your browser, for interactive login and scope selection')
            ->addArgument('target', InputArgument::OPTIONAL, 'Config name, matching contexts/<target>.zap-config.php - prints its write_routes/livewire_actions manual-verification checklist before launching')
        ;
    }
    /**
     * Invoke vs execute because you cannot Dependancy Inject the requirements because of the command concrete cast
     * Same same, same but different ensure that we execute the user input
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output,InputInterface $input, SymfonyStyle $io): int
    {
        $target = $input->getArgument('target');
        $config = $target !== null ? $this->loadConfig($io, $target) : null;

        if ($config !== null) {
            $this->printChecklist($io, $target, $config);
        }

        // The bootstrap only loads the root .env, so pull in zaproxy/.env here as the
        // default source for its own config - safeLoad() + Immutable won't overwrite
        // whatever the root .env already set, so root still wins on override.
        Dotenv::createImmutable(__DIR__ . '/../environment/security/zaproxy')->safeLoad();

        // zap-webswing.sh ignores CLI args, but honours the ZAP_WEBSWING_OPTS env var as a
        // full replacement of its default ZAP_OPTS (host/port/webswing stat) - so we have to
        // restate those alongside our own additions, not just append to them.
        $threadsPerHost = getenv('ZAP_ASCAN_THREADS_PER_HOST') ?: 2;
        $delayInMs = getenv('ZAP_ASCAN_DELAY_MS') ?: 0;

        $zapOpts = "-host 0.0.0.0 -port 8090 -config stats.pkg.webswing=1"
            . " -config api.disablekey=true"
            . " -config ascan.threadPerHost=$threadsPerHost"
            . " -config ascan.delayInMs=$delayInMs";

        $domXssStrength = getenv('ZAP_ASCAN_DOMXSS_STRENGTH') ?: 'LOW';
        $databaseRules = $this->databaseRuleRegexes($io);

        $command = "docker compose run --rm --service-ports --user zap"
            . " -e " . escapeshellarg("ZAP_WEBSWING_OPTS=$zapOpts")
            . " -e " . escapeshellarg("ZAP_HUD_THREADS=$threadsPerHost")
            . " -e " . escapeshellarg("ZAP_HUD_DELAY_MS=$delayInMs")
            . " -e " . escapeshellarg("ZAP_HUD_DOMXSS_STRENGTH=$domXssStrength")
            . " -e " . escapeshellarg("ZAP_HUD_DB_ENABLE={$databaseRules['enable']}")
            . " -e " . escapeshellarg("ZAP_HUD_DB_DISABLE={$databaseRules['disable']}");

        $context = $target !== null ? $this->resolveContext($io, $target, $config) : null;

        if ($context !== null) {
            $command .= " -e " . escapeshellarg("ZAP_HUD_CONTEXT_NAME={$context['name']}")
                . " -e " . escapeshellarg("ZAP_HUD_CONTEXT_FILE=" . rawurlencode($context['file']))
                . " -e " . escapeshellarg("ZAP_HUD_PROXY_EXCLUDE=" . rawurlencode($context['proxy_exclude']));
        }

        $command .= " zaproxy sh -c " . escapeshellarg('sh ' . self::HUD_WATCHER . ' & exec zap-webswing.sh');

        // ZAP_TARGET_ALIAS is baked into nginx's network alias at container-creation time
        // (Compose substitution, see servers/nginx/docker-compose.yml) - not something this
        // command can change at runtime, so surface it here rather than let it be a silent
        // stale value someone forgets they changed.
        $targetAlias = getenv('ZAP_TARGET_ALIAS') ?: 'default.test';

        $this->stopConflictingContainers($io);

        $io->note([
            "We are going to start ZAP HUD and expect a network connection to target: $targetAlias",
            "(this is configured via ZAP_TARGET_ALIAS in the root .env - requires 'docker compose up -d nginx' after changing it)",
        ]);

        if (!$io->confirm('Do you wish to continue?', true)) {
            return Command::SUCCESS;
        }

        $output->writeLn([
            "",
            $command,
            ""
        ]);

        $output->writeln('<href=http://localhost:8080/zap>Open the ZAP Desktop UI</> (once the container has finished starting up)');

        passthru($command);

        return Command::SUCCESS;
    }

    /**
     * Two distinct ways a stale container blocks a fresh HUD launch, both
     * confirmed live:
     *
     * 1. ide:zap-daemon's container shares the zap-home volume (added to
     *    persist Insights-addon-uninstall state across --rm daemon restarts)
     *    - meaning it also shares ZAP's own home-directory lock file with the
     *    HUD, and two ZAP processes can never hold that at once. Running both
     *    concurrently was always going to fight over the same live ZAP
     *    session anyway, so stopping the daemon here isn't a workaround -
     *    it's the correct outcome either way.
     * 2. A second `ide:zap-hud` while one's already running fails outright
     *    with "port is already allocated" (--service-ports binds the same
     *    host ports every time) - a much more opaque error than explaining
     *    up front that only one HUD session can run at a time.
     *
     * Both produce a confusing failure with no indication of the real cause
     * if left to happen - stopped here, explained via $io->note(), same
     * pattern as each other.
     */
    private function stopConflictingContainers(SymfonyStyle $io): void
    {
        $daemonRunning = trim((string) shell_exec('docker ps -q --filter name=^zap-daemon$'));

        if ($daemonRunning !== '') {
            $io->note("Stopping ide:zap-daemon's container first - it and the HUD share the same ZAP home directory, so both can't run at once.");
            shell_exec('docker stop zap-daemon 2>&1');
        }

        // The HUD's container gets an auto-generated name each run
        // (<project>-zaproxy-run-<hash>) - matched by substring, not an exact
        // name, since the hash varies every launch. Stops via command
        // substitution in one shell call rather than round-tripping the
        // captured id(s) back through PHP - correctly handles the (unlikely
        // but possible) case of more than one match, which word-splits fine
        // as $(...) but wouldn't as a re-interpolated PHP string.
        $hudRunning = trim((string) shell_exec('docker ps -q --filter name=zaproxy-run'));

        if ($hudRunning !== '') {
            $io->note('Stopping an existing HUD session first - only one can run at a time (it publishes the same host ports every launch).');
            shell_exec('docker stop $(docker ps -q --filter name=zaproxy-run) 2>&1');
        }
    }

    /**
     * The context exported by ide:zap-context - its name comes from the config's
     * `target` key (the hostname), which needn't match the command argument.
     *
     * @return array{name: string, file: string, proxy_exclude: string}|null
     */
    private function resolveContext(SymfonyStyle $io, string $target, ?array $config): ?array
    {
        $hostContextFile = __DIR__ . "/../environment/security/zaproxy/reports/{$target}.context";

        if (!is_file($hostContextFile)) {
            $io->warning("No exported context at _dev/environment/security/zaproxy/reports/{$target}.context - run `ide:zap-context {$target}` first. Launching without it.");
            return null;
        }

        $name = $config['target'] ?? $target;

        $io->note("The '{$name}' context will be imported automatically once ZAP has started in the browser.");

        // Matches every URL whose host isn't the target (optional port, then a
        // path or the end) - verified live that ZAP accepts the lookahead and
        // still passes excluded traffic through.
        $host = str_replace('.', '\.', $name);
        $proxyExclude = "^(?!https?://{$host}(?::\d+)?(?:/|$)).*";

        return ['name' => $name, 'file' => "/zap/wrk/{$target}.context", 'proxy_exclude' => $proxyExclude];
    }

    /**
     * Required once - a config's manifest keys can shell out to the target
     * app's artisan commands, so each require costs a few seconds.
     */
    private function loadConfig(SymfonyStyle $io, string $target): ?array
    {
        $configPath = __DIR__ . "/../environment/security/zaproxy/contexts/{$target}.zap-config.php";

        if (!is_file($configPath)) {
            $io->warning("No config found at _dev/environment/security/zaproxy/contexts/{$target}.zap-config.php - skipping the write-route/Livewire checklist.");
            return null;
        }

        return require $configPath;
    }

    /**
     * write_routes/livewire_actions have no automated path into ZAP's
     * history at all (no crawlable body, no route to enumerate) - printed
     * here, unconditionally and un-diffed, since this is the moment someone
     * is actually about to sit down and drive the browser, not a separate
     * command they have to remember exists. ide:zap-coverage remains the
     * place to check afterwards what was actually recorded.
     */
    private function printChecklist(SymfonyStyle $io, string $target, array $config): void
    {
        $writeRoutes = $config['write_routes'] ?? [];
        $livewireActions = $config['livewire_actions'] ?? [];

        if ($writeRoutes === [] && $livewireActions === []) {
            return;
        }

        $io->section("Manual verification checklist for {$target}");
        $io->writeln('Not reachable by the automated CLI scan - trigger each of these in this HUD session so ZAP records them for Active Scan.');
        $io->newLine();

        foreach ($writeRoutes as $route) {
            $io->writeln("  * {$route['method']}  {$route['uri']}");
            $io->newLine();
        }

        foreach ($livewireActions as $action) {
            $io->writeln("  * {$action['method']}()  on  {$action['uri']}");
            $io->newLine();
        }
    }
}
