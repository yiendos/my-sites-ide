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

class AssetsCommand extends Command
{
    /**
     * The ability to configure the console command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('ide:build-assets')
            ->setDescription('Ensure that npm builds the site assets')
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

        $output->writeLn('<info>About to build our site assets</>');

        $io->info("docker compose run --rm node /usr/local/bin/npm --prefix $project/Sites install"); 
        passthru("docker compose run --rm node /usr/local/bin/npm --prefix $project/Sites install");

        //but we have customised our builds 
        if (file_exists("Repos/$project/Sites/webpack.mix.cjs")) 
        {
            $io->info("docker compose run --rm node /usr/local/bin/npm --prefix $project/Sites run prod"); 
            passthru("docker compose run --rm node /usr/local/bin/npm --prefix $project/Sites run prod"); 

            return true;
        }

        //default for a new site is npm run build 
        if (file_exists("Repos/$project/Sites/vite.config.js")) 
        {
            $io->info("docker compose run --rm node /usr/local/bin/npm --prefix $project/Sites run build");
            passthru("docker compose run --rm node /usr/local/bin/npm --prefix $project/Sites run build");

            return true;
        }

        $io->warning("Unable to determine your node build environment, please run your build assets command manually");
       
        return Command::SUCCESS;
    }    
}