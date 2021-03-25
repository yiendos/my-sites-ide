<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace MySites\Command\Wordpress;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use MySites\Command\Config\Get;

class Vhost extends Command
{
    protected function configure()
    {
        $configs = new Get();
        $this->config = $configs->getConfig();

        $this
            ->setName('wordpress:vhost')
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
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $site = $input->getArgument('site');
        $remove = $input->getOption('remove');
        $docker_nginx_container = $this->config['x-project'] . '_nginx';
        $kindle_nginx_vhosts = $this->config['x-path'] . '/_mysites/docker/nginx/sites-enabled';
        $docker_apache_container = $this->config['x-project'] . '_apache';
        $kindle_apache_vhosts = $this->config['x-path'] . '/_mysites/docker/apache2/sites-enabled/';

        if (!strlen($site)){
            throw new \RuntimeException('You must provide a value for site');
        }

        if (!file_exists($this->config['x-path'] . '/Sites/' .  $site. "/")){
            throw new \RuntimeException(sprintf('Site not found: %s', $this->config['x-path'] . '/Sites/' .  $site));
        }

        if ($remove)
        {
            shell_exec("rm $kindle_nginx_vhosts/1-$site.conf");
            //passthru("docker kill -s HUP $docker_nginx_container > /dev/null 2>&1");

            shell_exec("rm $kindle_apache_vhosts/1-$site.conf");
            //passthru("docker exec $docker_apache_container /usr/local/apache2/bin/apachectl restart -D FOREGROUND");

            return Command::SUCCESS;
        }

        $tmp  = '/tmp/vhost.tmp';

        $variables = $this->_getVariables($input);

        $template = $this->_getTemplate($input, 'nginx');
        $vhost = str_replace(array_keys($variables), array_values($variables), $template);

        file_put_contents($tmp, $vhost);

        shell_exec("cp $tmp $kindle_nginx_vhosts/1-$site.conf");

        //passthru("docker kill -s HUP $docker_nginx_container > /dev/null 2>&1");

        $template = $this->_getTemplate($input, 'apache');
        $vhost = str_replace(array_keys($variables), array_values($variables), $template);

        file_put_contents($tmp, $vhost);
        
        shell_exec("cp $tmp $kindle_apache_vhosts/1-$site.conf");
        //passthru("docker exec $docker_apache_container /usr/local/apache2/bin/apachectl restart -D FOREGROUND");

        return Command::SUCCESS;
    }

    protected function _getVariables(InputInterface $input)
    {
        $site = $input->getArgument('site');

        $doc_root = '/Sites/' . $site;

        $variables = array(
            '%site%'       => $input->getArgument('site'),
            '%root%'       => $doc_root,
            '%http_port%'  => $input->getOption('http-port'),
            '%php_fpm%'    => $this->config['x-project'] . "_php_fpm",
            '%nginx_http_port%' => '8081'
        );

        if (!$input->getOption('disable-ssl'))
        {
            $variables = array_merge($variables, array(
                '%ssl_port%'    => $input->getOption('ssl-port'),
                '%certificate%' => $input->getOption('ssl-crt'),
                '%key%'         => $input->getOption('ssl-key')
            ));
        }

        return $variables;
    }

    protected function _getTemplate(InputInterface $input, $application = 'apache')
    {
        $path = realpath(__DIR__ . '/../../../bin/.files/vhosts');

        /*if ($template = $input->getOption(sprintf('%s-template', $application)))
        {
            if (file_exists($template)) {
                return file_get_contents($template);
            }
            else throw new \Exception(sprintf('Template file %s does not exist.', $template));
        }*/

        switch($application)
        {
            case 'nginx':
                //$file = Util::isKodekitPlatform($this->target_dir) ? 'nginx.kodekit.conf' : 'nginx.conf';
                $file = 'nginx.conf';
                break;
            case 'apache':
            default:
                $file = 'apache.conf';
                break;
        }

        $template = file_get_contents(sprintf('%s/%s', $path, $file));

        /*if (!$input->getOption('disable-ssl'))
        {
            if (file_exists($input->getOption('ssl-crt')) && file_exists($input->getOption('ssl-key')))
            {
                $file = str_replace('.conf', '.ssl.conf', $file);

                $template .= "\n\n" . file_get_contents(sprintf('%s/%s', $path, $file));
            }
            else throw new \Exception('Unable to enable SSL for the site: one or more certificate files are missing.');
        }*/

        return $template;
    }

}