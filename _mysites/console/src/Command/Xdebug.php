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
            ->setName('xdebug:status')
            ->setDescription('Enable or disable xdebug support')
            ->addArgument(
                'state',
                InputArgument::REQUIRED,
                "Toggle the use of xdebug image, to improve browser times (enable | disable)"
            );
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $toggle = $input->getArgument('state');
        $composer_mysites_dir = dirname(__FILE__,4);

        $command_input = new ArrayInput(array(
            '--snuff' => true
        ));

        $command = new Douse();
        $command->run($command_input, $output);

        passthru("Docker rmi mysites_php");

        if ($toggle == 'disable'){
            passthru('docker build -t mysites_php:latest -f ' . $composer_mysites_dir . "/docker/php/Dockerfile . ");
        }else{
            passthru('docker build -t mysites_php:latest -f ' . $composer_mysites_dir . "/docker/php/xdebug/Dockerfile . ");
        }

        $command_input = new ArrayInput(array());

        $command = new Spark();
        $command->run($command_input, $output);

        return Command::SUCCESS;
    }
}