<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace MySites\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use MySites\Command\Config\Get;

class Export extends Command
{
    protected $mysql_host = '127.0.0.1';
    protected $username ='root';
    protected $password;
    protected $port;
    protected $mysql_container;

    protected function configure()
    {
        $configs = new Get();

        $this->config = $configs->getConfig();
        $this->port = current($this->config['services']['db']['expose']);
        $this->password = $configs->getEnv('db', 'MYSQL_ROOT_PASSWORD');
        $this->mysql_container = $this->config['x-project'] . "_mysql";

        $this
        ->setName('export')
        ->setDescription('Export a database(s)')
            ->addOption(
            'output-file',
            'o',
            InputOption::VALUE_REQUIRED,
            'The path to export the database to',
            '/docker-entrypoint-initdb.d/'
        )
        ->addOption(
            'all-dbs',
            'all',
            InputOption::VALUE_NONE,
            'Whether all dbs should be exported'
        )
        ->addOption(
            'site',
            's',
            InputOption::VALUE_OPTIONAL,
            'A specific site to export'
        )
        ->addOption(
            'benchmark',
            'b',
            InputOption::VALUE_NONE,
            'Create your site database seed file, used in conjunction with initial deploys'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output_dir = $input->getOption('output-file');
        $site = $input->getOption('site');
        $all_dbs = $input->getOption('all-dbs');
        $benchmark = $input->getOption('benchmark');

        if (!$site && !$all_dbs){
            $output->writeln("<error>You must select at least one site -s or --all-dbs</error>");
            exit(1);
        }

        if ($benchmark){
            $output_dir = $output_dir . 'seeds/';
        }

        $output->writeln('<info>About to export database</info>');

        $file = $output_dir . $site . '.sql';

        $dbs = '-B sites_' . $site;

        if ($all_dbs)
        {
            $dbs = '-A';
            $file = $output_dir . 'all-dbs.sql';
        }

        $args = "-uroot --opt --skip-dump-date $dbs";
        $sed = 'sed \'s$VALUES ($VALUES\n($g\' | sed \'s$),($),\n($g\'';

        shell_exec("docker exec $this->mysql_container sh -c 'MYSQL_PWD=$this->password mysqldump --add-drop-database -h $this->mysql_host -P $this->port $args > $file'");

        $output->writeln("<info>File updated: " . $file . "</info>");

        return Command::SUCCESS;
    }
}