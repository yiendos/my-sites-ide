<?php

namespace Yiendos\MySitesIde;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Yiendos\MySitesIde\Plugins\Discover;

class PluginDiscoverCommand extends Command
{
    /**
     * The ability to configure the console command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('ide:plugin-discover')
            ->setDescription('Rebuild the plugin cache and docker-compose.plugins.yml (composer install/update does this for you)')
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
        $plugins = Discover::run();

        $io->success('Discovered ' . count($plugins) . ' plugin(s) - restart the CLI to pick up new commands.');

        return $this->getApplication()->find('ide:plugin-list')->run(new ArrayInput([]), $output);
    }
}
