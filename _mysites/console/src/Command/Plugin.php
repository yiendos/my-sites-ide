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
use WP_CLI\Iterators\Exception;

class Plugin extends Command
{
    protected function configure()
    {
        $configs = new Get();
        $this->config = $configs->getConfig();

        $this->setName('plugin:install')
            ->addArgument(
                'plugin',
                InputArgument::REQUIRED,
                'give the name of the folder within _mysites/plugins'
            )
            ->setDescription("Checked out a plugin, next lets install it");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $plugin_name = $input->getArgument('plugin');

        //so now we want to grab all the custom install instructions from the plugin
        $instruction_file = $this->config['x-path'] . '/_mysites/plugins/'. $plugin_name . "/install.yml";

        $output->writeLn($instruction_file);

        //$this->check($input, $output);
        if (!file_exists($instruction_file)){
            throw new Exception('Stop Cant carry on');
        }

        $instructions = Yaml::parseFile($instruction_file);

        //pre-scripts here

        //symlinks here
        foreach($instructions['install']['actions']['symlinks'] as $symlink){
            passthru("ln -s $symlink");
        }

        //postscripts
        foreach($instructions['install']['actions']['postscripts'] as $postscript){
            passthru("$postscript");
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