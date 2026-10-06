<?php

namespace Yiendos\MySitesIde;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\Plugins\Discover;

class PluginListCommand extends Command
{
    /**
     * The ability to configure the console command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('ide:plugin-list')
            ->setDescription('List the installed my-sites-ide plugins')
        ;
    }

    /**
     * Invoke vs execute because you cannot Dependancy Inject the requirements because of the command concrete cast
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output, InputInterface $input, SymfonyStyle $io): int
    {
        $plugins = Discover::load();

        if ($plugins === []) {
            $io->writeln('No plugins installed - find some with ide:plugin-search, add them to composer.local.json, then composer update.');
            return Command::SUCCESS;
        }

        $rows = [];

        foreach ($plugins as $name => $plugin) {
            $rows[] = [
                $name,
                implode(', ', $plugin['services']) ?: '-',
                $plugin['autostart'] ? 'yes' : 'no',
                count($plugin['commands']),
                $plugin['path'],
            ];
        }

        $io->table(['Package', 'Services', 'Autostart', 'Commands', 'Path'], $rows);

        return Command::SUCCESS;
    }
}
