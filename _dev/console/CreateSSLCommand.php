<?php 

namespace Yiendos\MySitesIde;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CreateSSLCommand extends Command
{
    /**
     * The ability to configure the console command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('ide:ssl')
            ->setDescription('Create a local SSL powered by Cloudflare and Certbot certificate')
            ->addArgument('certName', InputArgument::REQUIRED, 'the name of the domain you would like to create a SSL for')
            ->addOption('credentialsPath', null, InputOption::VALUE_OPTIONAL, 'Specify a custom credentials ini file', '_dev/environment/certificates/certbot/credentials.ini')
        ;
    }
    /**
     * Invoke vs execute because you cannot Dependancy Inject the requirements because of the command concrete cast
     * Same same, same but different ensure that we execute the user input
     *
     * @param OutputInterface $output
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return integer
     */
    public function __invoke(OutputInterface $output,InputInterface $input, SymfonyStyle $io): int
    {
        $certName = $input->getArgument('certName'); 
        $credentialsPath = $input->getOption('credentialsPath'); 

        $output->writeLn([
            "",
            "docker compose run certbot certonly --dns-cloudflare --dns-cloudflare-credentials $credentialsPath -d $certName"
        ]);
         
        passthru("docker compose run certbot certonly --dns-cloudflare --dns-cloudflare-credentials $credentialsPath -d $certName");

        //confirm to the user and inform them that they need to add the following lines to the nginx server block 
        $output->writeLn([
            "If the process was succesful, you should no add the following lines to your nginx vhost:", 
            "", 
            "ssl_certificate /etc/nginx/ssl/live/$certName/fullchain.pem;",
            "ssl_certificate_key /etc/nginx/ssl/live/$certName/privkey.pem;"
        ]); 

        return Command::SUCCESS;
    }
}