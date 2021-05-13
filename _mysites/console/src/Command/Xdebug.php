<?php


namespace MySites\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Yaml\Yaml;

class Xdebug extends Command
{
    protected function configure()
    {
        $this
            ->setName('xdebug:toggle')
            ->setDescription('Create the initial mysites  configuration file')
            ->addArgument(
                'enable',
                InputArgument::REQUIRED,
                "Toggle the use of xdebug image, to improve browser times (enable | disable)"
            );
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $toggle = $input->getArgument('enable');
        $composer_mysites_dir = dirname(__FILE__,4);

        if ($toggle == 'disable'){
            passthru('docker rmi mysites_php:latest');
        }else{
            passthru('docker build -t mysites_php:latest -f ' . $composer_mysites_dir . "/docker/php/xdebug/Dockerfile . ");
        }

        return Command::SUCCESS;
    }
}