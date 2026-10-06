<?php

namespace Yiendos\MySitesIde;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ZapStopCommand extends Command
{
    /**
     * The ability to configure the console command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('ide:zap-stop')
            ->setDescription('Stop every running ZAP container (HUD and daemon), ending the current ZAP session')
        ;
    }

    /**
     * The HUD and daemon are both one-off `docker compose run --rm`
     * containers, so they're matched the same way ZapHudCommand's
     * stopConflictingContainers() matches them: the HUD by its
     * auto-generated <project>-zaproxy-run-<hash> name, the daemon by its
     * fixed zap-daemon name. Both are --rm, so stopping also removes them.
     *
     * Webswing only allows one browser client (maxClients: 1 in the image's
     * own webswing.config) and its sessions never time out, so stopping the
     * container is the way to free that slot and get a fresh ZAP session.
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output, InputInterface $input, SymfonyStyle $io): int
    {
        $containers = [
            'HUD' => trim((string) shell_exec('docker ps -q --filter name=zaproxy-run')),
            'daemon' => trim((string) shell_exec('docker ps -q --filter name=^zap-daemon$')),
        ];

        $running = array_filter($containers, fn (string $ids) => $ids !== '');

        if ($running === []) {
            $io->writeln('No ZAP containers are running - nothing to stop.');
            return Command::SUCCESS;
        }

        foreach ($running as $label => $ids) {
            $io->writeln("Stopping the {$label} container...");
            // Word-splits on purpose, in case more than one id matched
            shell_exec('docker stop ' . preg_replace('/\s+/', ' ', $ids) . ' 2>&1');
        }

        $io->success('ZAP stopped. The next ide:zap-hud or ide:zap-daemon starts with a fresh session.');

        return Command::SUCCESS;
    }
}
