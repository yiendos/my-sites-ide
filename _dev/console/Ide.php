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
}
