<?php

namespace Yiendos\MySitesIde;

use Dotenv\Dotenv;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ZapHudCommand extends Command
{
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

        $command = "docker compose run --rm --service-ports --user zap"
            . " -e " . escapeshellarg("ZAP_WEBSWING_OPTS=$zapOpts")
            . " zaproxy zap-webswing.sh";

        // ZAP_TARGET_ALIAS is baked into nginx's network alias at container-creation time
        // (Compose substitution, see servers/nginx/docker-compose.yml) - not something this
        // command can change at runtime, so surface it here rather than let it be a silent
        // stale value someone forgets they changed.
        $targetAlias = getenv('ZAP_TARGET_ALIAS') ?: 'default.test';

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
}
