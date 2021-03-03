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
}

