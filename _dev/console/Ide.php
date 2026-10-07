<?php

namespace Yiendos\MySitesIde;

/**
 * Paths within the my-sites-ide project. Has no dependencies on purpose, as
 * Composer runs Plugins\Discover (which uses it) before vendor/ is autoloadable.
 */
final class Ide
{
    /**
     * The project root, the directory holding the my-sites-ide CLI
     *
     * @return string
     */
    public static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * A path relative to the project root
     *
     * @param string $path
     * @return string
     */
    public static function path(string $path = ''): string
    {
        return self::root() . ($path === '' ? '' : '/' . ltrim($path, '/'));
    }

    /**
     * Where a site's application code lives, relative to Repos/<site>/ - the
     * IDE_APP_DIR setting: `deploy` by default, `Sites` for the older layout,
     * `.` for an app at the root of the repository
     *
     * @return string
     * @throws \InvalidArgumentException for an absolute path or one leaving the site's folder
     */
    public static function appDir(): string
    {
        $setting = trim((string) getenv('IDE_APP_DIR'));
        $dir = trim($setting, '/');

        if ($setting === '') {
            return 'deploy';
        }

        if (str_starts_with($setting, '/') || in_array('..', explode('/', $dir), true)) {
            throw new \InvalidArgumentException("IDE_APP_DIR must be a folder inside Repos/<site>/, e.g. deploy - not '{$setting}'");
        }

        return $dir === '' ? '.' : $dir;
    }

    /**
     * A path within a site's application code, relative to the project root,
     * e.g. appPath('example', 'public') is Repos/example/deploy/public
     *
     * @param string $site
     * @param string $path
     * @return string
     */
    public static function appPath(string $site, string $path = ''): string
    {
        $app = self::appDir();

        return "Repos/{$site}" . ($app === '.' ? '' : "/{$app}") . ($path === '' ? '' : '/' . ltrim($path, '/'));
    }
}
