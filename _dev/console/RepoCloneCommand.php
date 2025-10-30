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
        $repo = $input->getArgument('repo'); 
        $project = $input->getOption('project');
        $laravel = $input->getOption('laravel'); 

        //lets find out whether we have a valid git clone web url
        $re = '/(git@github\.com\:)([A-Za-z0-9\/A-ZA-z0-9\.]*)(.git)/m';
        preg_match($re, $repo, $matches);

        if (!count($matches)) 
        {
            $io->error('This command expects a fully qualified SSH clone web url');

            return Command::FAILURE;
        }

        $projectName = !is_null($project) ? $project : last(explode("/", $matches[2]));

        $io->info("git clone --recurse-submodules $repo Repos/$projectName");
        
        //then we need to configure the _build/config files
        $this->copyVhosts($projectName, $io, $output);

        if ($laravel) 
        {
            $this->createLaravelFolders($projectName, $io, $output);

            $this->installDependencies($projectName, $io, $output); 

            $this->buildAssets($projectName, $io, $output);

            //$this->createDatabase($projectName, $io, $output);
        }

        //now lets restart the servers so the changes are picked up 
        $restartInput = new ArrayInput(['command' => 'ide:restart']);
        $application->doRun($restartInput, $output);

        return Command::SUCCESS;
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
        $servers = ['nginx', 'apache']; 

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
     * Create associated laravel folders 
     *
     * @param [string] $projectName
     * @param \Symfony\Component\Console\Style\SymfonyStyle $io
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return void
     */
    public function createLaravelFolders($projectName, $io, $output)
    {
        //not sure I like using a global 
        //https://raw.githubusercontent.com/laravel/laravel/refs/heads/12.x/public/index.php
        $lavavelIndex = $_ENV['LARAVEL_INDEX']; 
        
        $output->writeLn('<comment>Going to make the default Laravel folders (storage | public)</>');

        $io->info("mkdir -p Repos/$projectName/Sites/storage/framework/{cache,sessions,testing,views}"); 
        passthru("mkdir -p Repos/$projectName/Sites/storage/framework/{cache,sessions,testing,views}");

        $io->info("mkdir -p Repos/$projectName/Sites/storage/framework/cache/data"); 
        passthru("mkdir -p Repos/$projectName/Sites/storage/framework/cache/data");

        $io->info("mkdir -p Repos/$projectName/Sites/public");
        passthru("mkdir -p Repos/$projectName/Sites/public");

        $output->writeLn('<comment>About to create the default public/index.php file</>'); 
        
        $io->info("wget $lavavelIndex -O Repos/$projectName/Sites/public/index.php");
        passthru("wget $lavavelIndex -O Repos/$projectName/Sites/public/index.php");

    }
    /**
     * Create associated laravel folders 
     *
     * @param [string] $projectName
     * @param \Symfony\Component\Console\Style\SymfonyStyle $io
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return void
     */
    public function installDependencies($projectName, $io, $output)
    {
        $output->writeLn('<info>About to use our composer container to install dependencies</>'); 

        $io->info("docker compose run --rm composer --working-dir=$projectName/Sites install --no-scripts --ignore-platform-reqs --no-autoloader --prefer-dist"); 
        passthru("docker compose run --rm composer --working-dir=$projectName/Sites install --no-scripts --ignore-platform-reqs --no-autoloader --prefer-dist"); 

        $io->info("docker compose run --rm composer --working-dir=$projectName/Sites install --ignore-platform-reqs --prefer-dist");
        passthru("docker compose run --rm composer --working-dir=$projectName/Sites install --ignore-platform-reqs --prefer-dist");
    }
    /**
     * Create associated laravel folders 
     *
     * @param [string] $projectName
     * @param \Symfony\Component\Console\Style\SymfonyStyle $io
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return [boolean]
     */
    public function buildAssets($projectName, $io, $output)
    {
        $output->writeLn('<info>About to build our site assets</>');

        $io->info("docker compose run --rm node /usr/local/bin/npm --prefix $projectName/Sites install"); 
        passthru("docker compose run --rm node /usr/local/bin/npm --prefix $projectName/Sites install");

        //default for a new site is npm run build 
        if (file_exists("Repos/$projectName/Sites/vite.config.js")) 
        {
            $io->info("docker compose run --rm node /usr/local/bin/npm --prefix $projectName/Sites run build");
            passthru("docker compose run --rm node /usr/local/bin/npm --prefix $projectName/Sites run build");

            return true;
        }

        //but we have customised our builds 
        if (file_exists("Repos/$projectName/Sites/webpack.mix.cjs")) 
        {
            $io->info("docker compose run --rm node /usr/local/bin/npm --prefix $projectName/Sites run prod"); 
            passthru("docker compose run --rm node /usr/local/bin/npm --prefix $projectName/Sites run prod"); 

            return true;
        }

        $io->warning("Unable to determine your node build environment, please run your build assets command manually");

        return true;
    }
}