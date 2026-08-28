<?php

namespace Yiendos\MySitesIde;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ZapDaemonCommand extends Command
{
    /**
     * The ability to configure the console command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('ide:zap-daemon')
            ->setDescription('Ensure a ZAP daemon is running and its API is reachable, starting a headless one if needed')
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
        if ($this->isReachable()) {
            $io->writeln('ZAP daemon already up.');
            return Command::SUCCESS;
        }

        // -d backgrounds this at the Docker level (unlike ide:zap-hud, which
        // uses passthru() to stream the interactive Desktop UI in the
        // foreground) - no shell job control or output redirection needed
        // here since nobody's watching this one interactively. No webswing/UI
        // overhead either - ide:zap-context only ever needs the bare API.
        $io->writeln('Starting a headless ZAP daemon...');
        shell_exec('docker compose run -d --rm --name zap-daemon zaproxy zap.sh -daemon -host 0.0.0.0 -port 8090 -config api.disablekey=true 2>&1');

        $waited = 0;
        while (!$this->isReachable()) {
            if ($waited >= 60) {
                $io->error('ZAP daemon did not become reachable within 60s.');
                return Command::FAILURE;
            }

            sleep(2);
            $waited += 2;
        }

        $io->writeln("ZAP daemon ready after {$waited}s.");

        return Command::SUCCESS;
    }

    /**
     * The ZAP API is only reachable from inside the container's own network
     * namespace (see ZapContextCommand) - `docker compose exec` also fails
     * outright with a non-zero exit if no container is running for the
     * service at all, which doubles as the "nothing running yet" check.
     *
     * @return boolean
     */
    private function isReachable(): bool
    {
        $response = shell_exec('docker compose exec zaproxy curl -s http://localhost:8090/JSON/core/view/version/ 2>/dev/null');

        return is_string($response) && str_contains($response, 'version');
    }
}
