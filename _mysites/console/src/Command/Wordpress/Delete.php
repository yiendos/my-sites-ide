<?php

namespace MySites\Command\Wordpress;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Yaml\Yaml;
use MySites\Command\Config\Get;
use \MySites\Command\Joomla\Util;
use MySites\Command\Export;

class Delete extends Command
{
    protected function configure()
    {
        $configs = new Get();
        $this->config = $configs->getConfig();

        $this
            ->setName('wordpress:delete')
            ->setDescription('Nuke an existing site')
            ->setHelp(<<<EOF
    <info>kindle joomla:create testsite --release=2.5</info>
EOF
            )
            ->addArgument(
                'site',
                InputArgument::REQUIRED,
                'Alphanumeric site name. Also used in the site URL with .test domain'
            )
            ->addOption(
                'www',
                'w',
                InputOption::VALUE_OPTIONAL,
                'provide the path to the site',
                $this->config['x-path'] . '/Sites/'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->site = $input->getArgument('site');
        $this->target_dir = $input->getOption('www') . $this->site;
        $this->target_db  = 'sites_' . $this->site;

        //so first up remove vhosts
        $this->removeVhosts($input, $output);

        //first switch-a-roo container host for local
        $output->writeLn(Wp::call("config set --path={$this->target_dir} DB_HOST '127.0.0.1'"));

        //next up clear any db backed up/// all-dbs will replaced on box down
        $this->removeDatabase($input, $output);

        //remove site files
        shell_exec('rm -Rf ' . $this->target_dir);

        return Command::SUCCESS;

    }

    protected function removeVhosts(InputInterface $input, OutputInterface $output)
    {
        $kindle_nginx_vhosts = $this->config['x-path'] . '/_mysites/docker/nginx/sites-enabled';

        shell_exec("rm $kindle_nginx_vhosts/1-$this->site.conf");

        $kindle_apache_vhosts = $this->config['x-path'] . '/_mysites/docker/apache2/sites-enabled';

        shell_exec("rm $kindle_apache_vhosts/1-$this->site.conf");
    }

    protected function removeDatabase($input, $output)
    {
        $output->writeLn('db drop --path=' . $this->target_dir);

        //straight over to WP to drop our database obtained from the site configuration
        $output->writeLn(Wp::call('db drop --yes --path=' . $this->target_dir));

        //so now the database has been removed lets back up all dbs once more
        $arguments = array(
            '--all-dbs' => true
        );

        $command = new \MySites\Command\Export();
        $command->run(new ArrayInput($arguments), $output);
    }
}