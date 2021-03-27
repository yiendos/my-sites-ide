<?php

namespace MySites\Command\Wordpress;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use MySites\Command\Config\Get;

class Create extends Command
{
    /**
     * File cache
     *
     * @var string
     */
    protected static $files;

    /**
     * Downloaded WordPress tarball
     *
     * @var
     */
    protected $source_tarball;

    /**
     * Clear cache before fetching versions
     * @var bool
     */
    protected $clear_cache = false;

    protected $template;

    /**
     * WordPress version to install
     *
     * @var string
     */
    protected $version;

    /**
     * Projects to symlink
     * @var array
     */
    protected $symlink = array();

    protected $symlinked_projects;

    /**
     * WP_CLI executable path
     *
     * @var string
     */
    protected $wp;

    protected function configure()
    {
        $configs = new Get();
        $this->config = $configs->getConfig();

        if (!self::$files) {
            self::$files = realpath(__DIR__.'/../../../bin/.files');
        }

        $this
            ->setName('wordpress:create')
            ->setDescription('Create a WordPress site')
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
            )
            ->addOption(
                'wordpress',
                null,
                InputOption::VALUE_REQUIRED,
                "WordPress version. Can be a release number (3.2, 4.2.1, ..) or branch name. Run `wordpress versions` for a full list.\nUse \"none\" for an empty virtual host.",
                'latest'
            )
            ->addOption(
                'symlink',
                null,
                InputOption::VALUE_REQUIRED,
                'A comma separated list of folders to symlink from projects folder'
            )
            ->addOption(
                'clear-cache',
                null,
                InputOption::VALUE_NONE,
                'Update the list of available tags and branches from the WordPress repository'
            )
            ->addOption(
                'projects-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Directory where your custom projects reside',
                $this->config['x-path'] . '/Projects/'
            )
            ->addOption(
                'http-port',
                null,
                InputOption::VALUE_REQUIRED,
                'The HTTP port the virtual host should listen to',
                '8080'
            )
            ->addOption(
                'disable-ssl',
                null,
                InputOption::VALUE_NONE,
                'Disable SSL for this site'.
                true
            )
            ->addOption(
                'ssl-crt',
                null,
                InputOption::VALUE_OPTIONAL,
                'The full path to the signed cerfificate file',
                '/etc/apache2/ssl/server.crt'
            )
            ->addOption(
                'ssl-key',
                null,
                InputOption::VALUE_OPTIONAL,
                'The full path to the private cerfificate file',
                '/etc/apache2/ssl/server.key'
            )
            ->addOption(
                'ssl-port',
                null,
                InputOption::VALUE_OPTIONAL,
                'The port on which the server will listen for SSL requests',
                '443'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->site       = $input->getArgument('site');
        $this->www        = $input->getOption('www');
        $this->target_db_prefix = 'db';

        $this->target_db  = 'sites_' . $this->site;
        $this->target_dir = $this->www.'/'. $this->site;

        $credentials = array('root', 'root');
        $this->mysql = (object) array('user' => $credentials[0], 'password' => $credentials[1]);

        $default_port = $input->getOption('http-port');

        $this->check($input, $output);

        $this->createFolder($input, $output);

        $this->modifyConfiguration($input, $output);
        $this->createDatabase($input, $output);

        $this->installWordPress($input, $output);
        $this->addVirtualHost($input, $output);

        $output->writeLn(Wp::call("config set --path={$this->target_dir} DB_HOST 'db'"));

        $output->writeln("Your new <info>WordPress</info> site has been created.");
        $output->writeln("It was installed using the domain name <info>localhost:$default_port/$this->site</info>.");
        $output->writeln("You can login using the following username and password combination: <info>admin</info>/<info>admin</info>.");

        return Command::SUCCESS;
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        if (file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('A site with name %s already exists at: %s', $this->site, $this->target_dir));
        }
    }

    public function createFolder(InputInterface $input, OutputInterface $output)
    {
        $version = $input->getOption('wordpress');

        `mkdir -p $this->target_dir`;

        $output->writeln(Wp::call("core download --path=$this->target_dir --version=$version"));
    }

    public function modifyConfiguration(InputInterface $input, OutputInterface $output)
    {
        $output->writeln(Wp::call("config create --dbhost=127.0.0.1 --path={$this->target_dir} --dbname={$this->target_db} --dbuser={$this->mysql->user} --dbpass={$this->mysql->password} --extra-php=\"define( 'WP_DEBUG', true ); define( 'WP_DEBUG_LOG', true );\""));
    }

    public function createDatabase(InputInterface $input, OutputInterface $output)
    {
        $output->writeLn(Wp::call("db create --path=$this->target_dir"));
    }

    public function installWordPress(InputInterface $input, OutputInterface $output)
    {
        $default_port = $input->getOption('http-port');

        Wp::call("core install --url=localhost:$default_port/$this->site --path=$this->target_dir --title=$this->site --admin_user=admin --admin_password=admin --admin_email=admin@$this->site.mysites");

        Wp::call("user update admin --role=administrator --path=$this->target_dir");

        $roles = ['author', 'contributor', 'editor', 'subscriber'];

        foreach ($roles as $role)
        {
            $command = "user create {$role} {$role}@{$this->site}.kindle --user_pass={$role} --role={$role} --path={$this->target_dir}";

            $output->writeln(Wp::call($command));
        }

    }

    public function addVirtualHost(InputInterface $input, OutputInterface $output)
    {
        $command_input = new ArrayInput(array(
            'wordpress:vhost',
            'site'          => $this->site,
            '--http-port'   => $input->getOption('http-port'),
            '--disable-ssl' => $input->getOption('disable-ssl'),
            '--ssl-crt'     => '',
        ));

        $command = new Vhost();
        $command->run($command_input, $output);
    }

    public function symlinkProjects(InputInterface $input, OutputInterface $output)
    {
        if ($this->symlink)
        {
            $symlink_input = new ArrayInput(array(
                'wordpress:symlink',
                'site'    => $input->getArgument('site'),
                'symlink' => $this->symlink,
                '--www'   => $this->www,
                '--projects-dir' => $input->getOption('projects-dir')
            ));
            $symlink = new Extension\Symlink();

            $symlink->run($symlink_input, $output);

            $this->symlinked_projects = $symlink->getProjects();
        }
    }

    public function installExtensions(InputInterface $input, OutputInterface $output)
    {
        if ($this->symlinked_projects)
        {
            $plugin_input = new ArrayInput(array(
                'extension:install',
                'site'           => $input->getArgument('site'),
                'extension'      => $this->symlinked_projects,
                '--www'          => $this->www,
                '--projects-dir' => $input->getOption('projects-dir')
            ));
            $installer = new Extension\Install();

            $installer->run($plugin_input, $output);
        }
    }
}
