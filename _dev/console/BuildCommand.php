<?php 

namespace Yiendos\MySitesIde;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class BuildCommand extends Command
{
    /**
     * The ability to configure the console command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('ide:build')
            ->setDescription('Build the containers you wish')
            ->addOption('app', null, InputOption::VALUE_OPTIONAL, 'Which containers you would like to open', 'node fpm nginx apache mariadb redis cli cron composer')
        ;
    }
    /**
     * After the command has been configured, the user has provided input then run the command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return integer
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $app = $input->getOption('app');

        $output->writeLn("docker compose up -d $app  --build");
        exec("docker compose up -d $app  --build --remove-orphans");

        foreach (explode(" ", $_ENV['BURNER_CONTAINERS']) as $container) { 
             passthru("docker-compose rm -svf $container");
        }

        return Command::SUCCESS;
    }
}