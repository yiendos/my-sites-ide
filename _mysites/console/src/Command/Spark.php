<?php

namespace MySites\Command;

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

class Spark extends Command
{
    protected $mysql_host = '127.0.0.1';
    protected $username = 'root';
    protected $password;
    protected $port;
    protected $mysql_container;

    protected function configure()
    {
        $configs = new Get();
        $this->config = $configs->getConfig();
        $this->port = current($this->config['services']['db']['expose']);
        $this->password = $configs->getEnv('db','MYSQL_ROOT_PASSWORD');
        $this->mysql_container = $this->config['x-project'] . "_mysql";

        $this->setName('spark')
            ->setDescription('Fire up your mysites  IDE');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->check($input, $output);
        $root = $this->config['x-path'];
        $sites = '/Sites/';
        $path = realpath(__DIR__ . '/../../bin/.files');

        if (file_exists($root ."/.paused"))
        {
            passthru('docker-compose unpause');
            exec("rm $root/.paused");

            $this->openTabs();

            return Command::SUCCESS;
        }

        //so if the site wasn't paused we need to establish when the databases are available
        //slight delay to proceedings
        shell_exec("cp $path/rebuild.html $root/$sites/");
        passthru("docker-compose -f $root/docker-compose.yml up -d");

        shell_exec('docker exec ' . $this->mysql_container . ' sh -c "export MYSQL_PWD=root; mysqladmin ping -h ' . $this->mysql_host . ' -u root --wait=30--silent"');

        //now the db container is available we wait for the databases to be ready
        while (true) {
            $cmd = <<<EOT
docker exec $this->mysql_container sh -c "export MYSQL_PWD=$this->password; mysql -u$this->username -e 'SHOW DATABASES'"
EOT;
            $result = shell_exec($cmd);

            if ($result) {
                break;
            }
        }

        sleep(5);

        if (file_exists("$root/Sites/rebuild.html")){
            exec("rm $root/Sites/rebuild.html");
        }

        $output->writeLn('<info>Houston you are cleared for take off</info>');

        $this->openTabs();

        return Command::SUCCESS;
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        $kindle_root = $this->config['x-path'];
        $current_directory = trim(shell_exec('pwd'));

        if ($current_directory != $kindle_root){
            throw new \RuntimeException(sprintf('You must be in the mysites  root: ' . $kindle_root));
        }
    }

    public function openTabs()
    {
        sleep(5);

        $open_tabs = <<<EOT
        open -a "Google Chrome" http://localhost:8080/pages/hello &&
        open -a "Google Chrome" http://localhost:8081/pages/hello
EOT;

        shell_exec($open_tabs);
    }
}