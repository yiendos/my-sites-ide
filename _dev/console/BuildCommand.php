<?php 

namespace Yiendos\MySitesIde;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

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
        ;
    }
    /**
     * After the command has been configured, the user has provided input then run the command
     *
     * @param OutputInterface $output
     * @param Application $application
     * @return integer
     */
    public function __invoke(OutputInterface $output, Application $application): int
    {

        //we first need the cron build 
        //wierdly we need to have the mysql_cron built first 
        //this is because mysql_cli is built FROM it 
        //and will fail because it doesn't exist 
        $output->writeLn("docker compose build cron");
        exec("docker compose build cron");

        //now we proceed to build the rest of the containers
        $output->writeLn("docker compose build");
        exec("docker compose build");

        $output->writeLn("<info>Now lets spark our containers into life</>");

        $startContainers = new ArrayInput(['command' => 'ide:spark']);
        $application->doRun($startContainers, $output);

        return Command::SUCCESS;
    }
}