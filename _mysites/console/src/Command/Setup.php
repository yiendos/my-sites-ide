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
                'compose_file',
                'f',
                InputOption::VALUE_OPTIONAL,
                "Select the standard `docker-compose.yml` or ARM64 support via `docker-compose-arm.yml`",
                'docker-compose.yml'
            );
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $compose_file = $input->getOption('compose_file');

        $composer_mysites_dir = dirname(__FILE__,4);
        $current_location = trim(shell_exec('echo $PWD'));

        //first up does the base images exist
        $base_images = array('apache2', 'nginx' , 'php');

        foreach($base_images as $image)
        {
            $result =  trim(shell_exec('RET=`docker images -q mysites_' . $image . ':latest`;echo $RET'));

            if (!strlen($result)) {
                passthru('docker build -t mysites_' . $image . ':latest -f ' . $composer_mysites_dir . "/docker/" . $image . "/Dockerfile . ");
            }
        }

        shell_exec("cp -R $composer_mysites_dir/ $current_location/_mysites");

        $path_array =  explode("/", $current_location);
        $project = end($path_array);

        $docker_compose = $current_location ."/_mysites/console/bin/.files/docker/$compose_file";
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

        shell_exec("cp -R $current_location/_mysites/console/bin/.files/Sites/ $current_location/Sites/" );
        shell_exec("cp -R $current_location/_mysites/console/bin/.files/Projects/ $current_location/Projects/");
        shell_exec("cp -R $current_location/_mysites/console/bin/.files/.github $current_location/.github");

        return Command::SUCCESS;
    }
}