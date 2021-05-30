<?php

namespace MySites\Command\Joomla;

use Joomla\Input\Input;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use MySites\Command\Config\Get;

class Create extends Command
{
    protected static $files;

    protected function configure()
    {
        $configs = new Get();
        $this->config = $configs->getConfig();

        if (!self::$files) {
            self::$files = realpath(__DIR__.'/../../../bin/.files');
        }

        $this
            ->setName('joomla:create')
            ->setDescription('Create a Joomla site')
            ->addArgument(
                'site',
                InputArgument::REQUIRED,
                'Alphanumeric site name. Also used in the site URL with localhost:8080/{site}'
            )
            ->addOption(
                'www',
                null,
                InputOption::VALUE_REQUIRED,
                "Web server root",
                $this->config['x-path'] . '/Sites/'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->site             = $input->getArgument('site');
        $this->www              = $input->getOption('www');
        $this->target_db_prefix = 'db';
        $this->target_db        = 'sites_' . $this->site;
        $this->target_dir       = $this->www . $this->site;

        $credentials = array('root', 'root');
        $this->mysql = (object) array('user' => $credentials[0], 'password' => $credentials[1]);

        //some initial checks
        $this->check($input, $output);

        //create folder
        $this->createFolder($input, $output);

        //get latest version number to help with the download
        $latest = $this->getLatest($input, $output);

        //download a copy to /tmp
        $this->download($input, $output, $latest);

        //setup copy from /tmp
        $this->setUpCopy($input, $output);

        //create configuration
        $this->createConfig($input, $output);

        //create htaccess file for SEO
        $this->createHtaccess($input, $output);

        //create database
        $this->createDatabase($input, $output);

        //remove installation folder
        $this->removeInstallFolder($input, $output);

        //let the user know of the good news
        $output->writeLn('<info>Success your new Joomla! site is available</info>');
        $output->writeLn('Sitename: '. $this->site);
        $output->writeLn('Sitepath: ' . $this->target_dir);
        $output->writeLn('Site accessible at:');
        $output->writeLn('http://localhost:8080/' . $this->site);
        $output->writeLn('usernames: (admin:admin | user:user | manager:manager)');

        return Command::SUCCESS;
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        if (file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('A site with name %s already exists at: %s', $this->site, $this->target_dir));
        }
    }

    public function createFolder(InputInterface $input, OutputInterface $output){
        `mkdir -p $this->target_dir`;
    }

    public function getLatest(InputInterface $input, OutputInterface $output)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://downloads.joomla.org/api/v1/latest/cms');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $results = json_decode(curl_exec($ch));
        $info   = curl_getinfo($ch);

        if ($info['http_code'] != '200')
        {
            throw new Exception("Sorry can't get the latest version number for Joomla");
            exit();
        }

        $filtered = array_map(function($branch)
        {
            if ($branch->branch == "Joomla! 3"){
                return $branch->version;
            }
        }, $results->branches);


        $latest_version = current(array_filter($filtered));

        return $latest_version;
    }

    public function download(InputInterface  $input, OutputInterface $output, $latest)
    {
        $version = str_replace('.', '-', $latest);
        $url = "https://downloads.joomla.org/cms/joomla3/$version/Joomla_$version-Stable-Full_Package.tar.gz?format=gz";

        $output->writeLn("About to download Joomla! v$version");

        passthru("wget $url -O /tmp/latest_joomla.tar.gz > /dev/null 2>&1");
    }

    public function setUpCopy(InputInterface $input, OutputInterface $output)
    {
        $output->writeLn('About to extract Joomla!');

        `cd $this->target_dir && tar -xvf /tmp/latest_joomla.tar.gz > /dev/null 2>&1`;

        `rm /tmp/latest_joomla.tar.gz`;
    }

    public function createConfig(InputInterface $input, OutputInterface $output)
    {
        $output->writeLn('Creating the site configuration');

        $configuration = self::$files . "/Joomla/configuration.php";
        $configuration_details = file_get_contents($configuration);

        //now we replace our placeholders with the site name
        $revised_details = str_replace('%replace%', $this->site, $configuration_details);

        `touch $this->target_dir/configuration.php`;

        //finally put the new details there
        file_put_contents($this->target_dir . "/configuration.php", $revised_details);
    }

    public function createHtAccess(InputInterface $input, OutputInterface $output)
    {
        `mv $this->target_dir/htaccess.txt $this->target_dir/.htaccess`;
    }

    public function createDatabase(InputInterface $input, OutputInterface $output)
    {
        $output->writeLn('Creating database with sample users');

        $sql_contents = file_get_contents("$this->target_dir/installation/sql/mysql/joomla.sql");
        $revised_sql = str_replace('#__', 'j_', $sql_contents);
        $users_sql = self::$files . "/joomla3.users.sql";

        file_put_contents('/tmp/install.sql', $revised_sql);

        shell_exec("mysql -h 127.0.0.1 -uroot -proot -e 'CREATE DATABASE IF NOT EXISTS sites_$this->site' > /dev/null 2>&1;");

        shell_exec("mysql -h 127.0.0.1 -uroot -proot sites_$this->site < /tmp/install.sql > /dev/null 2>&1;");

        //and lets set up our custom users
        passthru("mysql -h 127.0.0.1 -uroot -proot sites_$this->site < $users_sql > /dev/null 2>&1;");

        `rm /tmp/install.sql`;
    }

    public function removeInstallFolder(InputInterface $input, OutputInterface $output){
        $output->writeLn('Removing the installation folder');

        `rm -Rf $this->target_dir/installation`;
    }
}