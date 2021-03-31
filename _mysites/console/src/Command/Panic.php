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

class Panic extends Command
{
    protected function configure()
    {
        $configs = new Get();
        $this->config = $configs->getConfig();

        $this->setName('panic')
            ->setDescription("Kindle won't launch? This is our troubleshooting command");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->check($input, $output);

        //first back up all-dbs so they are available next time
        $command_input = new ArrayInput(array(
            '--all-dbs'     => "true",
        ));

        $command = new Export();
        $command->run($command_input, $output);

        $output->writeLn('<info>First lets pull mysites  down</info> `docker-compose down`');

        shell_exec("docker-compose down");

        $output->writeLn('<info>Lets destroy our containers</info> `docker container prune`');

        passthru("docker container prune");

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