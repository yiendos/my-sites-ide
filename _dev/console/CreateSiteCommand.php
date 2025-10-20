<?php 

namespace Yiendos\MySitesIde;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CreateSiteCommand extends Command
{
    /**
     * The ability to configure the console command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('ide:create-site')
            ->setDescription('Create a new Laravel site, with my-site-ide integration')
            ->addArgument('projectName', InputArgument::REQUIRED, 'What is the name of your project e.g foo')
        ;
    }
    /**
     * Invoke vs execute because you cannot Dependancy Inject the requirements because of the command concrete cast
     * Same same, same but different ensure that we execute the user input
     *
     * @param OutputInterface $output
     * @param Application $application
     * @param InputInterface $input
     * @return integer
     */
    public function __invoke(OutputInterface $output, Application $application, InputInterface $input): int
    {
        $projectName = strtolower($input->getArgument('projectName'));

        $output->writeLn("php vendor/bin/laravel new Repos/$projectName/Sites"); 
        passthru("php vendor/bin/laravel new Repos/$projectName/Sites");

        $output->writeLn("mkdir -p Repos/$projectName/_build/config"); 
        passthru("mkdir -p Repos/$projectName/_build/config");
        
        $output->writeLn("mkdir -p Repos/$projectName/Projects"); 
        passthru("mkdir -p Repos/$projectName/Projects");
        
        //then we need to configure the _build/config files
        $this->copyVhosts($projectName);

        /** If the user has a deployment mechanism installed via composer */
        if (class_exists('Yiendos\Deploy\Console\Commands')) 
        {
            $createDeploy = new ArrayInput(['command' => 'deploy:site:create-scripts', 'site' => $projectName]);
            $application->doRun($createDeploy, $output);
        }
        //then we need to update the local.yaml - definately APP_KEY

        $output->writeLn('php my-sites-ide ide:restart'); 
        $restartInput = new ArrayInput(['command' => 'ide:restart']); 
        $application->doRun($restartInput, $output);

        return Command::SUCCESS;
    }

    public function copyVhosts($projectName)
    {
        $servers = ['nginx', 'apache']; 

        foreach($servers as $server)
        {
            $vhost = "Repos/$projectName/_build/config/1-$projectName-$server.conf";
            passthru("cp _dev/environment/servers/$server/sample.vhost $vhost");
            file_put_contents($vhost, str_replace("__PROJECT__", $projectName, file_get_contents($vhost)));
        }
    }
}