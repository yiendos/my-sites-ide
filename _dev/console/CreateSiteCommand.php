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
use Yiendos\MySitesIde\Plugins\Hooks;

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

        //into the site's application folder - IDE_APP_DIR, deploy by default
        $app = Ide::appPath($projectName);

        $output->writeLn("php vendor/bin/laravel new $app"); 
        passthru("php vendor/bin/laravel new $app");

        $output->writeLn("mkdir -p Repos/$projectName/_build/config"); 
        passthru("mkdir -p Repos/$projectName/_build/config");
        
        //plugins add their own site configuration (e.g. the nginx plugin's vhost, the
        //deploy plugin's deployment files)
        Hooks::run('site-created', ['site' => $projectName], $application, $output);

        $output->writeLn('php my-sites-ide ide:restart'); 
        $restartInput = new ArrayInput(['command' => 'ide:restart']); 
        $application->doRun($restartInput, $output);

        return Command::SUCCESS;
    }
}