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

class RepoCloneCommand extends Command
{
    /**
     * The ability to configure the console command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('ide:repo-clone')
            ->setDescription('Clone an existing git code base, and configure _build/config for my-sites-ide')
            ->addArgument('repo', InputArgument::REQUIRED, 'What is the organisation/name for the repository')
            ->addOption('project', null, InputOption::VALUE_OPTIONAL, 'What is the project name', null)
            ->addOption('laravel', null, InputOption::VALUE_NEGATABLE, 'Should we create default laravel folders', null);
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
        $repo           = $input->getArgument('repo'); 
        $project        = $input->getOption('project');
        $laravel        = $input->getOption('laravel'); 
        $projectName    = $this->determineProjectName($repo, $project);

        if (!strlen($projectName)) 
        {
            $io->error('This command expects a fully qualified SSH clone web url');
            return Command::FAILURE;
        }  
        //lets proceed to clone the repository with the given projectName
        $this->cloneRepository($io, $repo, $projectName);

        //then we need to configure the _build/config files
        $this->copyVhosts($projectName, $io, $output);

        //@todo refactor into own composer dependancy for my-sites-ide 
        if ($laravel) {
            $this->handleLaravel($io, $output, $application, $projectName); 
        }

        //now lets restart the servers so the changes are picked up 
        $restartInput = new ArrayInput(['command' => 'ide:restart']);
        $application->doRun($restartInput, $output);

        return Command::SUCCESS;
    }
    /**
     * Determine the project name 
     * If the user has provided a $project name use it 
     * Otherwise use the organisation/project as the basis
     * @param \Symfony\Component\Console\Style\SymfonyStyle $io
     * @param [string] $repo
     * @param [string] $projectName
     */
    public function cloneRepository($io, $repo, $projectName)
    {
        $io->info("git clone --recurse-submodules $repo Repos/$projectName");
        passthru("git clone --recurse-submodules $repo Repos/$projectName");
    }
    /**
     * Determine the project name 
     * If the user has provided a $project name use it 
     * Otherwise use the organisation/project as the basis
     *
     * @param [string] $repo
     * @param [string] $project
     * @return [string]
     */
    public function determineProjectName($repo, $project)
    {
        //lets find out whether we have a valid git clone web url
        $re = '/(git@github\.com\:)([A-Za-z0-9\/A-ZA-z0-9\.]*)(.git)/m';
        preg_match($re, $repo, $matches);

        if (!count($matches)) {
            return '';    
        }

        return !is_null($project) ? $project : last(explode("/", $matches[2]));
    }
    /**
     * Create the default server vhosts 
     *
     * @param [string] $projectName
     * @param \Symfony\Component\Console\Style\SymfonyStyle $io
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return void
     */
    public function copyVhosts($projectName, $io, $output)
    {
        //should be provided in via environment
        $servers = ['nginx', 'apache']; 

        if (file_exists("Repos/$projectName/_build/config"))
        {
            $io->warning("Default configuration folders already exist");
            return;
        }

        $output->writeLn("<comment>Going to create the default site vhost configuration</>"); 
        passthru("mkdir -p Repos/$projectName/_build/config");

        foreach($servers as $server)
        {
            $vhost = "Repos/$projectName/_build/config/1-$projectName-$server.conf";

            $io->info("cp _dev/environment/servers/$server/sample.vhost $vhost"); 
            passthru("cp _dev/environment/servers/$server/sample.vhost $vhost");

            file_put_contents($vhost, str_replace("__PROJECT__", $projectName, file_get_contents($vhost)));
        }
    }
    /**
     * 
     * @param \Symfony\Component\Console\Style\SymfonyStyle $io
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param \Symfony\Component\Console\Application $application
     * @param [string] $projectName
     */
    public function handleLaravel($io, $output, $application, $projectName)
    {
        $this->createLaravelFolders($projectName, $output);
 
        //now lets install the composer dependencies
        $installDependencies = new ArrayInput(['command' => 'ide:install-dependancies', 'project' => $projectName]);
        $application->doRun($installDependencies, $output);

        //now lets build the site assets 
        $buildAssets = new ArrayInput(['command' => 'ide:build-assets', 'project' => $projectName]);
        $application->doRun($buildAssets, $output);
    }
    /**
     * Create associated laravel folders 
     *
     * @param [string] $projectName
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return void
     */
    public function createLaravelFolders($projectName, $output)
    {
        //not sure I like using a global 
        //https://raw.githubusercontent.com/laravel/laravel/refs/heads/12.x/public/index.php
        $lavavelIndex = $_ENV['LARAVEL_INDEX']; 
        
        $output->writeLn([
            '',
            '<comment>Going to make the default Laravel folders (storage | public)</>',
            ''
        ]);

        $output->writeLn("<info>mkdir -p Repos/$projectName/Sites/storage/framework/{cache,sessions,testing,views}</>"); 
        exec("mkdir -p Repos/$projectName/Sites/storage/framework/{cache,sessions,testing,views}");

        $output->writeLn("<info>mkdir -p Repos/$projectName/Sites/storage/framework/cache/data</>"); 
        exec("mkdir -p Repos/$projectName/Sites/storage/framework/cache/data");

        $output->writeLn("<info>mkdir -p Repos/$projectName/Sites/public</>"); 
        exec("mkdir -p Repos/$projectName/Sites/public");

        $output->writeLn([
            '',
            '',
            '<comment>About to create the default public/index.php file</>',
            ''
        ]); 
        
        $output->writeLn("<info>wget $lavavelIndex -O Repos/$projectName/Sites/public/index.php</>"); 
        exec("wget $lavavelIndex -O Repos/$projectName/Sites/public/index.php");



    }
}