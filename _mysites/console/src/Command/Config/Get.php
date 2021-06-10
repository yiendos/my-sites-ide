<?php

namespace MySites\Command\Config;
use Symfony\Component\Yaml\Yaml as Yaml;
use Symfony\Component\Dotenv\Dotenv;

class Get
{
    public $settings;

    public $root;

    public function __construct()
    {
        $this->root = dirname(__FILE__, 6);

        $dotenv = new Dotenv();
        $dotenv->load($this->root . '/.env');

        $this->settings = $_ENV;

        $compose_settings =  Yaml::parseFile($this->root . "/docker-compose.yml");

        $this->settings = array_merge($this->settings, $compose_settings);
    }

    public function getConfig()
    {
        return $this->settings;
    }

    public function getEnv($service, $search, $assignment = '=')
    {
        $compose_settings = Yaml::parseFile($this->root . "/docker-compose.yml");
        $environment_vars = $compose_settings['services'][$service]['environment'];

        $needle = $search . $assignment;
        $result = array_filter($environment_vars, function($el) use ($needle) {
            return ( strpos($el, $needle) !== false );
        });

        if (count($result)){
            return str_replace($needle, '', current($result));
        }
    }

    public function getIDEFolders()
    {
        $this->settings['PROJECT_PATH'];

        $my_sites_ide_files = new \RecursiveDirectoryIterator($this->settings['PROJECT_PATH'] . "/");
        $display = Array ( '.my-sites-sites', '.my-sites-projects' );

        $found = array();

        foreach(new \RecursiveIteratorIterator($my_sites_ide_files) as $file)
        {
            if (in_array($file->getFilename(), $display) && !strpos($file->getPathname(), '_mysites/console/bin'))
            {
                $type_of_folder = str_replace('.my-sites-', '', $file->getFilename());
                $found[$type_of_folder] = str_replace(array($this->settings['PROJECT_PATH'],  $file->getFilename()), '', $file->getPathname());
            }
        }

        return $found;
    }
}

