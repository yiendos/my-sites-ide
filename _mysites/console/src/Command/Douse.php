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
use MySites\Command\Config\Get;

class Douse extends Command
{
    protected function configure()
    {
        $configs = new Get();
        $this->config = $configs->getConfig();

        $this->setName('douse')
            ->setDescription('Temporarily pause your sites')
            ->addOption(
                'xdebug',
                'x',
                InputOption::VALUE_REQUIRED,
                'Destroy a previously created php-fpm + xdebug container',
                false
            )
            ->addOption(
                'snuff',
                's',
                InputOption::VALUE_NONE,
                'Snuff out mysites  by stopping and pruning containers'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $snuff = $input->getOption('snuff');

        $this->check($input, $output);

        //first back up all-dbs so they are available next time
        $command_input = new ArrayInput(array(
            '--all-dbs'     => "true",
        ));

        $command = new Export();
        $command->run($command_input, $output);

        $kill_xdebug = $input->getOption('xdebug');

        if ($kill_xdebug){
            passthru("docker rmi loeror_php_fpm:latest");
        }

        if ($snuff)
        {
            $paused_file = $this->config['x-path'] . "/.paused";

            shell_exec("rm $paused_file > /dev/null 2>&1");

            passthru('docker-compose down');

            passthru('docker container prune');

            $output->writeLn('<info>Containers and network removed');
        }
        else
        {
            passthru('docker-compose pause');

            //create text file to denote the containers are currently paused
            $file = $this->config['x-path'] ."/.paused";
            exec("touch $file");
        }

        return Command::SUCCESS;
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        $kindle_root = $this->config['x-path'];
        $current_directory = trim(shell_exec('pwd'));

        if ($current_directory != $kindle_root){
            throw new \RuntimeException(sprintf('You must be in the mysites  root: ' . $kindle_root));
        }
    }
}