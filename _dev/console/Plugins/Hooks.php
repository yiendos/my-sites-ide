<?php

namespace Yiendos\MySitesIde\Plugins;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Runs the commands plugins attach to IDE events, declared in their manifest:
 *
 *     "hooks": { "site-created": ["servers:apache-vhost"] }
 *
 * Hooked commands are given the site, not its path: the site's application
 * code is in Repos/<site>/<IDE_APP_DIR>, which the CLI exports (deploy by
 * default).
 *
 * Events and the arguments each hooked command is given:
 *
 * - site-created  {site}  after ide:create-site / ide:repo-clone has the
 *                         site's Repos/<site>/_build/config in place, before
 *                         the IDE restarts
 * - site-dependencies  {site}  after ide:repo-clone --laravel has made the
 *                         Laravel folders, before site-assets - how a
 *                         build plugin installs the site's
 *                         dependencies (e.g. build:composer-install)
 * - site-assets  {site}  straight after site-dependencies, so a site's
 *                         assets build with its dependencies in place -
 *                         how a build plugin builds them (e.g.
 *                         build:node-assets)
 */
final class Hooks
{
    /**
     * Runs every command hooked to an event, in plugin (package name) order
     *
     * @param string $event
     * @param array<string, mixed> $arguments
     * @param Application $application
     * @param OutputInterface $output
     * @return int how many hooked commands ran
     */
    public static function run(string $event, array $arguments, Application $application, OutputInterface $output): int
    {
        $ran = 0;

        foreach (Discover::load() as $package => $plugin) {
            foreach ($plugin['hooks'][$event] ?? [] as $command) {
                if (!$application->has($command)) {
                    $output->writeLn("<comment>{$package} hooks {$command} to {$event}, but no such command is registered - skipping</>");
                    continue;
                }

                $output->writeLn("php my-sites-ide {$command} " . implode(' ', $arguments));
                $application->doRun(new ArrayInput(['command' => $command, ...$arguments]), $output);
                $ran++;
            }
        }

        return $ran;
    }
}
