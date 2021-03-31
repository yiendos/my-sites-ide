<?php
/**
 * @copyright   Copyright (C) 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     Mozilla Public License, version 2.0
 * @link        https://github.com/foliolabs/folioshell for the canonical source repository
 */

namespace MySites\Command\Wordpress;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use MySites\Command\Config\Get;

class Domain extends Command
{
    protected function configure()
    {
        $this->config = Get::config();

        $this->setName('wordpress:domain')
            ->setDescription("By default wordpress is configured for localhost:8080/{site}, this command will enable you to specify a new site_url")
            ->addArgument(
                'site',
                InputArgument::REQUIRED,
                'Alphanumeric site name. the short name of the site you would like to configure differently'
            )
            ->addOption(
                'alias',
                'a',
                InputOption::VALUE_OPTIONAL,
                'Alias trumps all other options, and should be provided in the format http://wordpress.kindle:8080'
            )
            ->addOption(
                'port',
                'p',
                InputOption::VALUE_OPTIONAL,
                'By providinig a port you can change localhost:8080/wordpress to localhost:8081/wordpress'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->site       = $input->getArgument('site');
        $this->www        = $this->config['www_dir']. "/" . $this->config['container_html'];
        $this->target_dir = $this->www.'/'.$this->site;
        $alias = $input->getOption('alias');
        $port = $input->getOption('port');

        $this->check($input, $output);

        $site_url = 'http://localhost:8080/' . $this->site;

        if (strlen($port)){
            $site_url = 'http://localhost:'. $port . '/' . $this->site;
        }

        if (strlen($alias)){
            $site_url = $alias;
        }

        $output->writeLn(Wp::call("config set --path={$this->target_dir} WP_HOME '$site_url'"));
        $output->writeLn(Wp::call("config set --path={$this->target_dir} WP_SITEURL '$site_url'"));

        return Command::SUCCESS;
    }

    public function check(InputInterface $input, OutputInterface $output)
    {
        if (!file_exists($this->target_dir)) {
            throw new \RuntimeException(sprintf('Sorry %s site does not exist yet', $this->site));
        }
    }
}