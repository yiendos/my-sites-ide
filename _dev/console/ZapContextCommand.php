<?php

namespace Yiendos\MySitesIde;

use Dotenv\Dotenv;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\InteractsWithZapApi;
class ZapContextCommand extends Command
{
    use InteractsWithZapApi;

    /**
     * The ability to configure the console command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('ide:zap-context')
            ->setDescription('Build (or rebuild) a ZAP authenticated-scan context from contexts/<target>.zap-config.php')
            ->addArgument('target', InputArgument::REQUIRED, 'Config name, matching contexts/<target>.zap-config.php')
            ->addOption('site-url', null, InputOption::VALUE_REQUIRED, 'Base URL, e.g. https://stockman.test - resolves relative --login-url/--poll-url/--logout-url and derives scope. Only used when no config exists yet for this target.')
            ->addOption('login-url', null, InputOption::VALUE_REQUIRED, 'Login form URL, absolute or relative to --site-url, e.g. /login')
            ->addOption('login-data', null, InputOption::VALUE_REQUIRED, 'Login POST body template, e.g. "email={%username%}&password={%password%}&_token={%_token%}"')
            ->addOption('login-page-url', null, InputOption::VALUE_REQUIRED, 'Page ZAP fetches fresh CSRF/hidden fields from - defaults to --login-url if omitted')
            ->addOption('indicator', null, InputOption::VALUE_REQUIRED, 'Logged-in indicator regex, e.g. \'action="https://stockman\.test/logout"\'')
            ->addOption('poll-url', null, InputOption::VALUE_REQUIRED, 'Session-liveness poll URL, absolute or relative to --site-url, e.g. /home')
            ->addOption('logout-url', null, InputOption::VALUE_REQUIRED, 'Logout URL, absolute or relative to --site-url - defaults to /logout')
            ->addOption('username', null, InputOption::VALUE_REQUIRED, 'Login email/username - the password comes from the ZAP_TARGET_PASSWORD env var, never a flag')
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
    public function __invoke(OutputInterface $output, InputInterface $input, SymfonyStyle $io): int
    {
        // The bootstrap only loads the root .env, so pull in zaproxy/.env here as the
        // default source for ZAP_TARGET_PASSWORD - safeLoad() + Immutable won't
        // overwrite whatever the root .env already set, so root still wins on override.
        Dotenv::createImmutable(__DIR__ . '/../environment/security/zaproxy')->safeLoad();

        $target = $input->getArgument('target');
        $configPath = __DIR__ . "/../environment/security/zaproxy/contexts/{$target}.zap-config.php";

        if (!is_file($configPath) && !$this->writeConfigFromOptions($io, $input, $target, $configPath)) {
            return $this->scaffoldConfig($io, $target, $configPath);
        }

        $config = require $configPath;
        $contextName = $config['target'];

        // The ZAP API is only reachable from inside the container's own network
        // namespace - it silently drops connections that don't present as true
        // loopback, which a host->container port-forward does not on Docker
        // Desktop for Mac. So every call goes via `docker compose exec`, not a
        // direct HTTP request - ide:zap-daemon ensures something's actually
        // running to exec into, starting a headless one if needed (a full
        // ide:zap-hud session works fine too, if one's already open).
        $daemonExit = $this->getApplication()->find('ide:zap-daemon')->run(new ArrayInput([]), $output);

        if ($daemonExit !== Command::SUCCESS) {
            $io->error('Could not reach a ZAP daemon.');
            return Command::FAILURE;
        }

        // Rebuilding from scratch each run avoids duplicate/stale contexts from
        // repeated invocations - removeContext errors harmlessly if it's not there yet.
        $this->zapApi($io, 'context/action/removeContext', ['contextName' => $contextName], allowMissing: true);
        $created = $this->zapApi($io, 'context/action/newContext', ['contextName' => $contextName]);
        $contextId = $created['contextId'] ?? null;

        if ($contextId === null) {
            $io->error('Failed to create ZAP context - is `ide:zap-hud` running?');
            return Command::FAILURE;
        }

        foreach ($config['scope']['include'] ?? [] as $regex) {
            $this->zapApi($io, 'context/action/includeInContext', ['contextName' => $contextName, 'regex' => $regex]);
        }

        foreach ($config['scope']['exclude'] ?? [] as $regex) {
            $this->zapApi($io, 'context/action/excludeFromContext', ['contextName' => $contextName, 'regex' => $regex]);
        }

        $authMethodConfigParams = http_build_query([
            'loginUrl' => $config['login']['url'],
            'loginPageUrl' => $config['login']['page_url'] ?? $config['login']['url'],
            'loginRequestData' => $config['login']['request_data'],
        ]);

        $this->zapApi($io, 'authentication/action/setAuthenticationMethod', [
            'contextId' => $contextId,
            'authMethodName' => 'formBasedAuthentication',
            'authMethodConfigParams' => $authMethodConfigParams,
        ]);

        if (!empty($config['indicator']['logged_in'])) {
            $this->zapApi($io, 'authentication/action/setLoggedInIndicator', [
                'contextId' => $contextId,
                'loggedInIndicatorRegex' => $config['indicator']['logged_in'],
            ]);
        }

        if (!empty($config['indicator']['logged_out'])) {
            $this->zapApi($io, 'authentication/action/setLoggedOutIndicator', [
                'contextId' => $contextId,
                'loggedOutIndicatorRegex' => $config['indicator']['logged_out'],
            ]);
        }

        $newUser = $this->zapApi($io, 'users/action/newUser', [
            'contextId' => $contextId,
            'name' => $config['user']['name'],
        ]);
        $userId = $newUser['userId'] ?? null;

        if ($userId === null) {
            $io->error('Failed to create ZAP user.');
            return Command::FAILURE;
        }

        $credentialParams = http_build_query([
            'username' => $config['user']['username'],
            'password' => $config['user']['password'],
        ]);

        $this->zapApi($io, 'users/action/setAuthenticationCredentials', [
            'contextId' => $contextId,
            'userId' => $userId,
            'authCredentialsConfigParams' => $credentialParams,
        ]);

        $this->zapApi($io, 'users/action/setUserEnabled', [
            'contextId' => $contextId,
            'userId' => $userId,
            'enabled' => 'true',
        ]);

        $contextFile = "/zap/wrk/{$target}.context";
        $hostContextFile = __DIR__ . "/../environment/security/zaproxy/reports/{$target}.context";

        $this->zapApi($io, 'context/action/exportContext', [
            'contextName' => $contextName,
            'contextFile' => $contextFile,
        ]);

        // No API action sets the authentication verification strategy - a fresh
        // context always defaults to POLL_URL with no poll URL configured, which
        // crashes every authentication check. EACH_RESP (check the indicator on
        // every response) looks like the obvious alternative but has no caching,
        // so it re-authenticates before most requests and self-locks against any
        // login rate limit. POLL_URL WITH a poll URL set is correct: it caches
        // its result for pollFrequency (60s) - verified live, 0 vs 15 re-logins
        // for the same scan. Only reachable by editing the exported XML and
        // re-importing it, since the API has no action for this field either.
        //
        // A never-imported context has no <pollurl> element at all (ZAP only
        // emits it once a context has round-tripped through XML at least once),
        // so this injects it after <strategy> rather than replacing one that
        // doesn't exist yet.
        if (!empty($config['indicator']['poll_url'])) {
            $xml = file_get_contents($hostContextFile);
            $pollUrl = htmlspecialchars($config['indicator']['poll_url'], ENT_XML1);
            $xml = preg_replace(
                '/<strategy>.*?<\/strategy>/',
                "<strategy>POLL_URL</strategy>\n            <pollurl>{$pollUrl}</pollurl>",
                $xml
            );
            file_put_contents($hostContextFile, $xml);

            $this->zapApi($io, 'context/action/removeContext', ['contextName' => $contextName]);
            $this->zapApi($io, 'context/action/importContext', ['contextFile' => $contextFile]);
            $this->zapApi($io, 'context/action/exportContext', [
                'contextName' => $contextName,
                'contextFile' => $contextFile,
            ]);
        }

        $io->success("Exported context to _dev/environment/security/zaproxy/reports/{$target}.context (user: {$config['user']['name']})");

        return Command::SUCCESS;
    }

    /**
     * Builds a complete <target>.zap-config.php directly from CLI options
     * (+ ZAP_TARGET_PASSWORD), rather than scaffolding a placeholder that
     * needs manual editing before this command can do anything. Only covers
     * the fields scaffoldConfig() itself leaves for a human - login/
     * indicator/scope/user; seed_urls/write_routes/livewire_actions stay a
     * per-target, hand-wired shell-out to the target app's own artisan
     * commands, same as every existing config - not something derivable
     * from CLI flags. Returns false (falling back to the placeholder
     * scaffold) if the minimum required options/env aren't all present,
     * rather than writing a half-complete file.
     */
    private function writeConfigFromOptions(SymfonyStyle $io, InputInterface $input, string $target, string $configPath): bool
    {
        $siteUrl = $input->getOption('site-url');
        $loginUrl = $input->getOption('login-url');
        $loginData = $input->getOption('login-data');
        $indicator = $input->getOption('indicator');
        $pollUrl = $input->getOption('poll-url');
        $username = $input->getOption('username');
        $password = getenv('ZAP_TARGET_PASSWORD') ?: null;

        if ($siteUrl === null || $loginUrl === null || $loginData === null || $indicator === null || $pollUrl === null || $username === null || $password === null) {
            return false;
        }

        if (!str_starts_with($siteUrl, 'http')) {
            $io->error("--site-url must be a full URL with scheme, e.g. https://{$target}.test - got '{$siteUrl}'.");
            return false;
        }

        $siteUrl = rtrim($siteUrl, '/');
        $host = parse_url($siteUrl, PHP_URL_HOST);

        $resolve = fn (string $url): string => str_starts_with($url, 'http') ? $url : $siteUrl.'/'.ltrim($url, '/');

        $loginUrl = $resolve($loginUrl);
        $loginPageUrl = $input->getOption('login-page-url');
        $loginPageUrl = $loginPageUrl !== null ? $resolve($loginPageUrl) : null;
        $pollUrl = $resolve($pollUrl);
        $logoutUrl = $resolve($input->getOption('logout-url') ?? '/logout');

        // Matches the existing hand-written convention exactly (e.g.
        // 'https://stockman\.test.*') - only the literal dots are escaped for
        // regex purposes, not a full preg_quote(), so the generated file reads
        // the same as one a human wrote by hand.
        $escapeForRegex = fn (string $url): string => str_replace('.', '\.', $url);

        $loginPageUrlLine = $loginPageUrl !== null
            ? "        'page_url' => '{$loginPageUrl}',\n"
            : '';

        $php = <<<PHP
        <?php

        return [
            'target' => '{$host}',

            'login' => [
                'url' => '{$loginUrl}',
        {$loginPageUrlLine}        'request_data' => '{$loginData}',
            ],

            'indicator' => [
                'logged_in' => '{$indicator}',
                'poll_url' => '{$pollUrl}',
            ],

            'scope' => [
                'include' => [
                    '{$escapeForRegex($siteUrl)}.*',
                ],
                'exclude' => [
                    '{$escapeForRegex($loginUrl)}.*',
                    '{$escapeForRegex($logoutUrl)}.*',
                ],
            ],

            'user' => [
                'name' => 'demo',
                'username' => '{$username}',
                'password' => '{$password}',
            ],
        ];

        PHP;

        file_put_contents($configPath, $php);

        $io->success("Generated _dev/environment/security/zaproxy/contexts/{$target}.zap-config.php from command-line options.");

        return true;
    }

    /**
     * Scaffolds a new <target>.zap-config.php from the tracked example
     * rather than requiring a manual copy - substitutes the example's
     * placeholder hostname throughout. Can't guess real credentials, login
     * field names, or an indicator regex, so this always stops short of
     * actually building anything - the point is removing the copy/rename
     * step, not the "fill in what only you know" step.
     */
    private function scaffoldConfig(SymfonyStyle $io, string $target, string $configPath): int
    {
        $examplePath = __DIR__ . '/../environment/security/zaproxy/contexts/example.zap-config.php';

        if (!is_file($examplePath)) {
            $io->error("No config found at _dev/environment/security/zaproxy/contexts/{$target}.zap-config.php, and no example.zap-config.php to scaffold from.");
            return Command::FAILURE;
        }

        $host = str_contains($target, '.') ? $target : "{$target}.test";

        $scaffold = str_replace(
            ['example.test', 'example\.test'],
            [$host, str_replace('.', '\.', $host)],
            file_get_contents($examplePath)
        );
        file_put_contents($configPath, $scaffold);

        $io->warning([
            "No config existed for '{$target}' - scaffolded _dev/environment/security/zaproxy/contexts/{$target}.zap-config.php from the example.",
            "Edit its login/indicator/user details for {$host}, then run this command again.",
        ]);

        return Command::FAILURE;
    }
}
