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

class Setup extends Command
{
    protected function configure()
    {
        $this
            ->setName('setup')
            ->setDescription('Create the initial mysites  configuration file')
            ->addOption(
                'file_sync',
                'f',
                InputOption::VALUE_REQUIRED,
                "Select your type of file sync (nfs | docker-sync | native)",
                'nfs'
            );
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $current_location = trim(shell_exec('echo $PWD'));
        $path_array =  explode("/", $current_location);
        $project = end($path_array);
        $docker_compose = $current_location ."/_mysites/console/bin/.files/docker/docker-compose.yml";

        //create IDE docker-compose and populate with correct details
        shell_exec("cp $docker_compose /tmp/docker-compose.yml");

        $config = file_get_contents('/tmp/docker-compose.yml');
        $updated = str_replace(array('~project~', '~pwd~'), array($project, $current_location), $config);

        file_put_contents("/tmp/docker-compose.yml", $updated);
        shell_exec("cp /tmp/docker-compose.yml $current_location/docker-compose.yml && rm /tmp/docker-compose.yml");

        //now make commands executable
        $path = '/usr/local/bin';

         if (!file_exists($path)){
             $path = 'usr/local/sbin';
         }

         shell_exec("chmod u+x $current_location/_mysites/console/bin/mysites && ln -s $current_location/_mysites/console/bin/mysites $path/$project");

         $output->writeLn('<info>Success, interact with my-site through the following path</info>');

         $output->writeLn("$path/$project");

         shell_exec("cp $current_location/_mysites/console/bin/.files/docker/docker_file_sharing.sh $current_location/docker_file_sharing.sh");

         shell_exec("cp -R $current_location/_mysites/console/bin/.files/Sites $current_location/" );

         shell_exec("cp -R $current_location/_mysites/console/bin/.files/Projects $current_location/Projects");

        return Command::SUCCESS;
    }
}