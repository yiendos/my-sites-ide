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

class Restart extends Command
{
    protected function configure()
    {
        $configs = new Get();
        $this->config = $configs->getConfig();

        $this->setName('restart')
            ->setDescription('Made changes locally? Restart the corresponding docker container')
            ->addArgument(
                'container',
                InputArgument::REQUIRED,
                'Provide the name of the container (apache | nginx | mysql)'

            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $input->getArgument('container');

        switch ($container) {
            case 'apache':
                passthru("docker exec loeror_$container /usr/local/apache2/bin/apachectl restart -D FOREGROUND");
                break;
            case 'mysql':
                passthru("docker restart loeror_$container > /dev/null 2>&1");
                break;
            default:
                passthru("docker restart loeror_$container > /dev/null 2>&1");
                break;
        }

        return Command::SUCCESS;
    }
}