<?php

namespace Yiendos\MySitesIde\Plugins;

use Yiendos\MySitesIde\Ide;

/**
 * Finds every installed Composer package of type my-sites-ide-plugin and
 * writes the generated files everything else reads:
 *
 * - _dev/cache/plugins.php      what the bootstrap registers (commands, env)
 * - docker-compose.plugins.yml  one include per plugin, pulled in by docker-compose.yml
 * - _dev/cache/ide.env          IDE_ROOT, so plugin compose files can reach the project root
 *
 * Runs on Composer's post-autoload-dump, so a `composer require` / `remove`
 * is all it takes. Deliberately plain PHP: Composer only autoloads the root
 * package's own classes for script callbacks, not vendor/.
 */
final class Discover
{
    public const TYPE = 'my-sites-ide-plugin';

    private const CACHE = '_dev/cache/plugins.php';
    private const COMPOSE = 'docker-compose.plugins.yml';
    private const ENV = '_dev/cache/ide.env';
    private const INSTALLED = 'vendor/composer/installed.json';

    /**
     * Rebuild the generated files - Composer's post-autoload-dump entry point
     * (the Composer event it passes is ignored)
     *
     * @return array<string, array<string, mixed>> the plugins found, keyed by package name
     */
    public static function run(): array
    {
        $plugins = self::fromInstalled();

        self::write(self::CACHE, '<?php return ' . var_export($plugins, true) . ";\n");
        self::write(self::COMPOSE, self::compose($plugins));
        self::write(self::ENV, 'IDE_ROOT=' . Ide::root() . "\n");

        return $plugins;
    }

    /**
     * The installed plugins, rebuilding the generated files when they're
     * missing or older than Composer's install record
     *
     * @return array<string, array<string, mixed>>
     */
    public static function load(): array
    {
        $cache = Ide::path(self::CACHE);
        $installed = Ide::path(self::INSTALLED);

        $stale = !file_exists($cache)
            || !file_exists(Ide::path(self::COMPOSE))
            || (file_exists($installed) && filemtime($installed) > filemtime($cache));

        return $stale ? self::run() : require $cache;
    }

    /**
     * Reads each plugin's extra.my-sites-ide manifest from installed.json.
     * Paths are kept relative to the project root, so the cache stays valid
     * if the project folder moves.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function fromInstalled(): array
    {
        $installed = Ide::path(self::INSTALLED);

        if (!file_exists($installed)) {
            return [];
        }

        $json = json_decode((string) file_get_contents($installed), true) ?? [];
        $plugins = [];

        foreach ($json['packages'] ?? [] as $package) {
            if (($package['type'] ?? '') !== self::TYPE) {
                continue;
            }

            $manifest = $package['extra']['my-sites-ide'] ?? [];
            $path = self::normalise('vendor/composer/' . $package['install-path']);
            $file = fn (string $key): ?string => isset($manifest[$key]) ? "{$path}/{$manifest[$key]}" : null;

            $plugins[$package['name']] = [
                'description' => $package['description'] ?? '',
                'path' => $path,
                'commands' => self::commands($package, $path, (array) ($manifest['commands'] ?? [])),
                'compose' => $file('compose'),
                'env' => $file('env'),
                'env-example' => $file('env-example'),
                'services' => $manifest['services'] ?? [],
                'autostart' => (bool) ($manifest['autostart'] ?? false),
                'hooks' => array_map(fn ($commands): array => (array) $commands, $manifest['hooks'] ?? []),
            ];
        }

        ksort($plugins);

        return $plugins;
    }

    /**
     * Each manifest entry is either a class name or a directory - for a
     * directory, every PHP file in it is mapped to a class via the package's
     * own PSR-4 autoload. Whether each is really a Command is checked at boot,
     * when the classes can actually be loaded.
     *
     * @param array<string, mixed> $package
     * @param string $path
     * @param array<int, string> $entries
     * @return array<int, string>
     */
    private static function commands(array $package, string $path, array $entries): array
    {
        $classes = [];

        foreach ($entries as $entry) {
            if (str_contains($entry, '\\')) {
                $classes[] = ltrim($entry, '\\');
                continue;
            }

            $directory = trim($entry, '/');

            foreach ($package['autoload']['psr-4'] ?? [] as $prefix => $bases) {
                foreach ((array) $bases as $base) {
                    $base = trim($base, '/');

                    if ($directory !== $base && !str_starts_with($directory, "{$base}/")) {
                        continue;
                    }

                    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(Ide::path("{$path}/{$directory}"), \FilesystemIterator::SKIP_DOTS));

                    foreach ($files as $file) {
                        if ($file->getExtension() !== 'php') {
                            continue;
                        }

                        $relative = substr($file->getPathname(), strlen(Ide::path("{$path}/{$base}")) + 1, -4);
                        $classes[] = $prefix . str_replace('/', '\\', $relative);
                    }
                }
            }
        }

        sort($classes);

        return array_values(array_unique($classes));
    }

    /**
     * One include per plugin with a compose file. Each include interpolates
     * from the plugin's own .env defaults, then the root .env (later files
     * win, so the user's overrides apply), then IDE_ROOT.
     *
     * @param array<string, array<string, mixed>> $plugins
     * @return string
     */
    private static function compose(array $plugins): string
    {
        $yaml = "# Generated by Plugins\\Discover on composer install/update - do not edit.\n"
            . "# Rebuild with `php my-sites-ide ide:plugin-discover`.\n";

        $includes = '';

        foreach ($plugins as $plugin) {
            if ($plugin['compose'] === null) {
                continue;
            }

            $envFiles = array_filter([$plugin['env'], '.env', self::ENV]);

            $includes .= "    - path: {$plugin['compose']}\n"
                . "      env_file:\n"
                . implode('', array_map(fn (string $env): string => "          - {$env}\n", $envFiles));
        }

        // An empty mapping is a valid compose file, so the include in
        // docker-compose.yml never breaks when no plugins are installed
        return $yaml . ($includes === '' ? "{}\n" : "include:\n{$includes}");
    }

    /**
     * Resolves the ../ in Composer's install-path (relative to vendor/composer)
     *
     * @param string $path
     * @return string
     */
    private static function normalise(string $path): string
    {
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            match ($segment) {
                '', '.' => null,
                '..' => array_pop($segments),
                default => $segments[] = $segment,
            };
        }

        return implode('/', $segments);
    }

    /**
     * Writes a generated file, creating its directory on first run
     *
     * @param string $path
     * @param string $contents
     * @return void
     */
    private static function write(string $path, string $contents): void
    {
        $path = Ide::path($path);

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $contents);
    }
}
