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
use Yiendos\MySitesIde\Plugins\Hooks;

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
        $clone = $this->cloneRepository($io, $repo, $projectName);

        if ($clone == false)
        { 
            $io->error("Something went wrong with the clone process");
            return Command::FAILURE;
        }
        //then we need the _build/config folder, for the site's own configuration
        $this->createConfigFolder($projectName, $io, $output);

        //server plugins add their own site configuration (e.g. the nginx plugin's vhost)
        Hooks::run('site-created', ['site' => $projectName], $application, $output);

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
     * @return [string|false|null]
     */
    public function cloneRepository($io, $repo, $projectName)
    {
        $io->info("git clone --recurse-submodules $repo Repos/$projectName");
        $result = passthru("git clone --recurse-submodules $repo Repos/$projectName");

        return $result;
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
     * Create the site's _build/config folder - server plugins write their
     * vhosts into it through the site-created hook, unless the repository
     * already brings its own
     *
     * @param [string] $projectName
     * @param \Symfony\Component\Console\Style\SymfonyStyle $io
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return void
     */
    public function createConfigFolder($projectName, $io, $output)
    {
        if (file_exists("Repos/$projectName/_build/config"))
        {
            $io->warning("Default configuration folders already exist");
            return;
        }

        $output->writeLn("<comment>Going to create the default site configuration folder</>"); 
        passthru("mkdir -p Repos/$projectName/_build/config");
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
 
        //build plugins install the site's dependencies (e.g. the composer plugin's composer install)
        if (!Hooks::run('site-dependencies', ['site' => $projectName], $application, $output)) {
            $io->warning("No plugin installs dependencies - add yiendos/my-sites-ide-build-composer to composer.local.json for composer install");
        }

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
        $lavavelIndex = getenv('LARAVEL_INDEX'); 
        
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