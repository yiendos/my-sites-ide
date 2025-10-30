<?php 

namespace Yiendos\MySitesIde;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ComposerCommand extends Command
{
    /**
     * The ability to configure the console command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('ide:install-dependancies')
            ->setDescription('Ensure that composer dependancies are installed')
            ->addArgument('project', InputArgument::REQUIRED, 'What is the organisation/name for the repository');
        ;
    }
    /**
     * Invoke vs execute because you cannot Dependancy Inject the requirements because of the command concrete cast
     * Same same, same but different ensure that we execute the user input
     *
     * @param OutputInterface $output
     * @param Application $application
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output, Application $application, InputInterface $input, SymfonyStyle $io): int
    {
        $project = $input->getArgument('project'); 

        $output->writeLn('<info>About to use our composer container to install dependencies</>'); 

        $io->info("docker compose run --rm composer --working-dir=$project/Sites install --no-scripts --ignore-platform-reqs --no-autoloader --prefer-dist"); 
        passthru("docker compose run --rm composer --working-dir=$project/Sites install --no-scripts --ignore-platform-reqs --no-autoloader --prefer-dist"); 

        $io->info("docker compose run --rm composer --working-dir=$project/Sites install --ignore-platform-reqs --prefer-dist");
        passthru("docker compose run --rm composer --working-dir=$project/Sites install --ignore-platform-reqs --prefer-dist");
       
        return Command::SUCCESS;
    }    
}