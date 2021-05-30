<?php

namespace MySites\Command\Joomla;

use Joomla\Input\Input;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use MySites\Command\Config\Get;

class Delete extends Command
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
            ->setName('joomla:delete')
            ->setDescription('Delet a Joomla site')
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

        $this->removeDatabase($input, $output);

        //remove vhost when available

        //remove the site files
        $this->removeSite($input, $output);

        return Command::SUCCESS;
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        if (!file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('%s site does not exist: %s', $this->site, $this->target_dir));
        }
    }

    public function removeDatabase(InputInterface $input, OutputInterface $output)
    {
        shell_exec("mysql -h 127.0.0.1 -uroot -proot -e 'DROP DATABASE sites_$this->site' > /dev/null 2>&1;");
    }

    public function removeSite(InputInterface $input, OutputInterface $output)
    {
        `rm -Rf $this->target_dir`;
    }
}