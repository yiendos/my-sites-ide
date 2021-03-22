<?php

namespace MySites\Command\Config;

use Symfony\Component\Yaml\Yaml as Yaml;

class Get
{
    public $settings;

    public function __construct()
    {
        $root = dirname(__FILE__, 6);

        $this->settings =  Yaml::parseFile($root . "/docker-compose.yml");
    }

    public function getConfig()
    {
        return  $this->settings;
    }

    public function getEnv($service, $search, $assignment = '=')
    {
        $environment_vars = $this->settings['services'][$service]['environment'];

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
        $this->settings['x-path'];

        $my_sites_ide_files = new \RecursiveDirectoryIterator($this->settings['x-path'] . "/");
        $display = Array ( '.my-sites-sites', '.my-sites-projects' );

        $found = array();

        foreach(new \RecursiveIteratorIterator($my_sites_ide_files) as $file)
        {
            if (in_array($file->getFilename(), $display) && !strpos($file->getPathname(), '_mysites/console/bin'))
            {
                $type_of_folder = str_replace('.my-sites-', '', $file->getFilename());
                $found[$type_of_folder] = str_replace(array($this->settings['x-path'],  $file->getFilename()), '', $file->getPathname());
            }
        }

        return $found;
    }
}

