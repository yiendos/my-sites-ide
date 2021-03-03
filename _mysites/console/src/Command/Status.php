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

class Status extends Command
{
    protected function configure()
    {
        $configs = new Get();
        $this->config = $configs->getConfig();

        $this->setName('status')
            ->setDescription('See the status of mysites  containers')
            ->addOption(
                'inspect',
                'i',
                InputOption::VALUE_OPTIONAL,
                "Inspect any running container, by providing it's name as the option"
            )
            ->addOption(
                'system',
                's',
                InputOption::VALUE_OPTIONAL,
                "Inspect any running container's system log, by providing it's name as the option"
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $inspect_container = $input->getOption('inspect');
        $system_logs = $input->getOption('system');
        $project = $this->config['x-project'];

        $output->writeLn('<info>Running containers</info>');

        passthru('docker container list | grep "' . $project . '"');

        passthru('docker ps -a | grep "Exited"');

        $output->writeLn("<info>$project network</info>");

        passthru('docker network list | grep "' . $project .'"');

        if ($inspect_container)
        {
            $output->writeLn('<info>Config details for: ' .  $inspect_container . '</info>');
            $result = json_decode(shell_exec("docker inspect $inspect_container"));
            $top = current($result);

            echo "<pre>";
            print_r($top->Config);
            echo "</pre>";
        }

        if ($system_logs){
            shell_exec("docker logs $system_logs");
        }

        return Command::SUCCESS;
    }
}