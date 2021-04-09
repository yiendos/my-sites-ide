<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace MySites\Command\Joomla;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use MySites\Command\Config\Get;
use MySites\Command\Wordpress;

class Vhost extends Wordpress\Vhost
{
    protected function configure()
    {
        $configs = new Get();
        $this->config = $configs->getConfig();

        $this
            ->setName('joomla:vhost')
            ->setDescription('Creates a new Apache2 and/or Nginx virtual host')
            ->addArgument(
                'site',
                InputOption::VALUE_REQUIRED,
                'The name of the existing site to create vhosts for'
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
                InputOption::VALUE_OPTIONAL,
                'Disable SSL for this site',
                true
            )
            ->addOption(
                'ssl-crt',
                null,
                InputOption::VALUE_REQUIRED,
                'The full path to the signed cerfificate file',
                '/etc/apache2/ssl/server.crt'
            )
            ->addOption(
                'ssl-key',
                null,
                InputOption::VALUE_REQUIRED,
                'The full path to the private cerfificate file',
                '/etc/apache2/ssl/server.key'
            )
            ->addOption(
                'ssl-port',
                null,
                InputOption::VALUE_REQUIRED,
                'The port on which the server will listen for SSL requests',
                443
            )
            ->addOption(
                'php-fpm-address',
                null,
                InputOption::VALUE_REQUIRED,
                'PHP-FPM address or path to Unix socket file, set as value for fastcgi_pass in Nginx config',
                $this->config['x-project'] . '_php_fpm'
            )
            ->addOption(
                'remove',
                'r',
                InputOption::VALUE_NONE,
                'Optionally remove any vhosts that have already been created'
            );
    }
}