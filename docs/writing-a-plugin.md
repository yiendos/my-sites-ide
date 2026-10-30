# Writing a plugin

`composer install`/`update` discovers installed plugins and generates `docker-compose.plugins.yml` (included by `docker-compose.yml`), so a plugin's console commands, docker services and `.env` defaults are picked up without editing any core file. A plugin's user data lives in `storage/plugins/<service>/`, never in `vendor/` - with `"storage": true` the IDE creates that folder and mounts it at `/storage` in the plugin's containers.

See [Architecture](architecture.md) for how the pieces fit together.

## Naming

| Thing | Pattern | Example |
|---|---|---|
| Package / repository | `<vendor>/my-sites-ide-<category>-<service>` | `yiendos/my-sites-ide-security-zaproxy` |
| Composer type | `my-sites-ide-plugin` | |
| Namespace | `<Vendor>\MySitesIde\<Category>\<Service>` | `Yiendos\MySitesIde\Security\Zaproxy` |
| Compose service | `<service>` | `zaproxy` |
| Env prefix | `<SHORT>_` | `ZAP_` |
| Commands | `<category>:<short>-<action>` | `security:zap-scan` |
| User data | `storage/plugins/<service>/` | `storage/plugins/zaproxy/` |

Categories follow the `_dev/environment/` folders: preprocessors, servers, databases, build, caching, editor, certificates - plus security, mail, deploy, clusters.

## The manifest

Describe it in the plugin's own `composer.json`:

```json
"type": "my-sites-ide-plugin",
"extra": {
    "my-sites-ide": {
        "commands":    "src/Console",
        "compose":     "docker-compose.yml",
        "env":         ".env",
        "env-example": "env-example",
        "services":    ["zaproxy"],
        "autostart":   false,
        "storage":     true,
        "hooks":       { "site-created": ["servers:apache-vhost"] }
    }
}
```

- `commands` - a directory (every Symfony `Command` in it is registered, class names via the package's PSR-4 autoload) or a list of directories/class names.
- `compose` - included into the stack. Reach the project root with `${IDE_ROOT}`, never `../../..` - the package lives in `vendor/`.
- `env` - the plugin's defaults, loaded after the root `.env`, so the user's values win (for both commands and compose interpolation).
- `services` / `autostart` - with `autostart: true`, `ide:spark` starts these alongside `APP`.
- `storage` - with `true`, the IDE creates `storage/plugins/<service>/` (`<service>` from the package name) and mounts it at `/storage` in every one of `services` - git ignored, outside `vendor/`, so it survives `composer update`. Commands reach it on the host through `IDE_ROOT`. Discover writes the mount as an override in `_dev/cache/storage/`, merged into the plugin's own compose file.
- `hooks` - commands the IDE runs on its events. `site-created` runs once `ide:create-site` / `ide:repo-clone` has the site's `Repos/<site>/_build/config` in place, with the site as the `site` argument - how a server plugin adds its own vhost. `site-dependencies` runs during `ide:repo-clone --laravel`, once the Laravel folders exist, also with `site` - how the composer plugin installs the site's dependencies. `site-assets` runs straight after it, with `site` - how the node plugin builds the site's assets.

Commands find the project root through the `IDE_ROOT` environment variable, and a site's application code in `Repos/<site>/<IDE_APP_DIR>` - the CLI sets both, and `IDE_APP_DIR` is always a valid folder (`deploy` unless `.env` says otherwise, `.` for the repository root). Fall back to `deploy` if it's missing, for older versions of the IDE. List `IDE_APP_DIR` in your plugin's `env-example` if it uses it.

## Developing a plugin locally

To develop a plugin locally, clone it into `Packages/<vendor>/my-sites-ide-<category>-<service>` - the root `composer.json` has a path repository for `Packages/*/my-sites-ide-*`, so adding `"<vendor>/<package>": "@dev"` to `composer.local.json` and running `composer update` symlinks your working copy into `vendor/`. Nothing is committed to my-sites-ide - which plugins you've cloned is up to you. Discover reads each plugin's own `composer.json`, so manifest edits in your clone apply after `php my-sites-ide ide:plugin-discover`, committed or not.
