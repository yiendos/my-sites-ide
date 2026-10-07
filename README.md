# my-sites-ide 

![Screenshot](https://raw.githubusercontent.com/yiendos/my-sites-ide/master/screenshot.png?raw=true)

Welcome to my-sites-ide, contained within this project is over 8 years of experience working with Docker, condensed, distilled into one lean mean Dev-ops code base. There are many features: 

* Modular - pick which services you require - Nginx, plus Apache as a [plugin](#plugins), Mysql or Mariab or all  
* Small image size, all images < 200mb - Yet still contain all the php goodies you require for most PHP websites including Laravel. 
* Blazingly fast build, CI, deployment of containers - Because of the small image sizes, all waiting times are reduced
* Configurable, the main .env can override the settings of all the docker containers being run

my-sites-ide gives you full control over how your sites are run, failing that you are free to modify the original Dockerfiles for total configuration-city 

You can run the same images for your local environment, CI/CD/ staging/ and production environments you can be assured of perfect results everytime. 

## installation 

* `git clone git@github.com:yiendos/my-sites-ide.git`

* `cd my-sites-ide`

* `cp env-example .env` 

* `composer install` 

* `php my-sites-ide ide:build` 

Now you can access your default homepage: 

* https://default.localhost/ [nginx]

* https://default.localhost:8443/ [apache, with the [apache plugin](https://github.com/yiendos/my-sites-ide-servers-apache) installed]

## See available commands 

`php my-sites-ide` 

```
Console Tool

Usage:
  command [options] [arguments]

Options:
  -h, --help            Display help for the given command. When no command is given display help for the list command
      --silent          Do not output any message
  -q, --quiet           Only errors are displayed. All other output is suppressed
  -V, --version         Display this application version
      --ansi|--no-ansi  Force (or disable --no-ansi) ANSI output
  -n, --no-interaction  Do not ask any interactive question
  -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Available commands:
  completion       Dump the shell completion script
  help             Display help for a command
  list             List commands
 ide
  ide:build        Build the containers you wish
  ide:create-site  Create a new Laravel site, with my-site-ide integration
  ide:douse        Finished, until next time? Bring the containers down
  ide:restart      Cloned a new site, or made configuration changes? Restart the IDE
  ide:spark        Spark your creativity to life, by bringing the IDE up
```

For help and guidance relating to any command 

`php my-sites-ide [command:subcommand] --help` 

## Background to modular docker containers

In terms of running containers on the IDE you have the choice of: 

* PHP-FPM 
* Nginx 
* Apache (plugin: [yiendos/my-sites-ide-servers-apache](https://github.com/yiendos/my-sites-ide-servers-apache))
* Mariadb 
* MySQL
* Redis 
* PHP-CLI 
* PHP cron

A default list of Applications are defined in the `./env` file: 

`APP="fpm,nginx,mariadb,redis,cli,cron"`

And these are the default containers that will run when you invoke: 

`php my-sites-ide ide:spark` 

To change the default behaviour add or remove containers from the `./env` file or provide further options via the spark command: 

`php my-sites-ide ide:spark --app=fpm,nginx`

## Next steps 

Make things more interesting by installing a Laravel site: 

`php my-sites-ide ide:create-site example`

Run through the normal install steps...

This will create a new Laravel instance under `./Repos/example`

```
└── Repos
   └── example
      ├── _build
      │   └── config
      └── Projects
      └── Sites
```

Then access your brand new Laravel site: 

* https://example.localhost [nginx]

* https://example.localhost:8443 [apache plugin]

### New site configuration 

You can configure how your servers respond to requests by changing the default configuration files `Repos/[example]/_build/`. 

If your sites, require a common code base, these can be installed and shared via the `Packages` folder

Site specific packages can be installed under the `Repos/[example]/Projects` folder 

## Hosting Repositories 

my-sites-ide can handle as many github repositories or individual projects you can throw at them, however some default structure should be applied. 

1. First clone any projects to the IDE under the `./Repos` folder 
2. Each project should contain the following folder structure 

```
 PROJECT NAME                           //name of the project/ repository
   ├── _build
   │   ├── config
   │   │   ├── 1-default-apache.conf    //provide a vhost configuration for apache (if you are using the apache plugin)
   │   │   └── 1-default-nginx.conf     //provide a vhost configuration for nginx (if you are using)
   └── Sites                            //where your PHP app should be hosted 
``` 

Remember after each time you clone a repository/ create a new site to `./Repos` you should restart your IDE for these changes to take effect: 

`php my-sites-ide ide:restart`

## Multiple installations of my-sites-ide

You can use my-sites-ide as many times as you like locally, because the system creates images and containers based off the `.env` NAMESPACE variable. 

Therefore for different organisations/ projects you just need to provide a unique NAMESPACE variable. 

Then when you build your images `php my-sites-ide ide:build` this unique NAMESPACE variable will create corresponding images.

For example: If you named one project `yiendos` via the `.env` NAMESPACE variable, images would be named `yiendos_fpm` etc 

For another project: if you named this project `paul` via the `.env` NAMESPACE variable, images would be named `paul_fpm` etc

This way your projects are sandboxed.


## Plugins

Extra services (security scanners, alternative servers, deployment targets) install as Composer packages of type `my-sites-ide-plugin`, e.g. [yiendos/my-sites-ide-security-zaproxy](https://github.com/yiendos/my-sites-ide-security-zaproxy) or [yiendos/my-sites-ide-servers-apache](https://github.com/yiendos/my-sites-ide-servers-apache).

Which plugins you use is your choice, so they're listed in your own `composer.local.json` (git ignored, merged into `composer.json` by [wikimedia/composer-merge-plugin](https://github.com/wikimedia/composer-merge-plugin)) rather than the tracked `composer.json`. `composer.lock` is git ignored for the same reason - every installation's set of plugins differs.

```
php my-sites-ide ide:plugin-search                              # find plugins on Packagist
cp composer.local-example.json composer.local.json              # first time only, then list your plugins under "require"
composer update                                                 # install them
php my-sites-ide ide:plugin-env yiendos/my-sites-ide-security-zaproxy   # optional: copy its options into .env, commented out
php my-sites-ide ide:plugin-list                                # what's installed
```

Don't `composer require` a plugin - that writes to the tracked `composer.json`. A plugin that isn't on Packagist can be added through a `repositories` entry in `composer.local.json`, which is merged too.

`composer install`/`update` discovers installed plugins and generates `docker-compose.plugins.yml` (included by `docker-compose.yml`), so a plugin's console commands, docker services and `.env` defaults are picked up without editing any core file. A plugin's user data lives in `storage/plugins/<service>/`, never in `vendor/`.

### Naming

| Thing | Pattern | Example |
|---|---|---|
| Package / repository | `<vendor>/my-sites-ide-<category>-<service>` | `yiendos/my-sites-ide-security-zaproxy` |
| Composer type | `my-sites-ide-plugin` | |
| Namespace | `<Vendor>\MySitesIde\<Category>\<Service>` | `Yiendos\MySitesIde\Security\Zaproxy` |
| Compose service | `<service>` | `zaproxy` |
| Env prefix | `<SHORT>_` | `ZAP_` |
| Commands | `<category>:<short>-<action>` | `security:zap-scan` |
| User data | `storage/plugins/<service>/` | `storage/plugins/zaproxy/` |

Categories follow the `_dev/environment/` folders: preprocessors, servers, databases, build, caching, mailcatchers, editor, certificates - plus security, deploy.

### Writing a plugin

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
        "hooks":       { "site-created": ["servers:apache-vhost"] }
    }
}
```

- `commands` - a directory (every Symfony `Command` in it is registered, class names via the package's PSR-4 autoload) or a list of directories/class names.
- `compose` - included into the stack. Reach the project root with `${IDE_ROOT}`, never `../../..` - the package lives in `vendor/`.
- `env` - the plugin's defaults, loaded after the root `.env`, so the user's values win (for both commands and compose interpolation).
- `services` / `autostart` - with `autostart: true`, `ide:spark` starts these alongside `APP`.
- `hooks` - commands the IDE runs on its events. `site-created` runs once `ide:create-site` / `ide:repo-clone` has the site's `Repos/<site>/_build/config` in place, with the site as the `site` argument - how a server plugin adds its own vhost.

Commands find the project root through the `IDE_ROOT` environment variable, which the CLI sets.

To develop a plugin locally, clone it into `Packages/<vendor>/my-sites-ide-<category>-<service>` - the root `composer.json` has a path repository for `Packages/*/my-sites-ide-*`, so adding `"<vendor>/<package>": "@dev"` to `composer.local.json` and running `composer update` symlinks your working copy into `vendor/`. Nothing is committed to my-sites-ide - which plugins you've cloned is up to you.

### Certificates

Sites use the IDE's self-signed certificate (`_dev/environment/servers/ssl/`) unless a certificate plugin issues a real one. Those plugins share one store, `storage/certificates/`, in the Let's Encrypt layout (`live/<domain>/fullchain.pem` linking into `archive/`). nginx always mounts it, at `/etc/nginx/ssl/live` and `/etc/nginx/ssl/archive`. Without a certificate plugin it's just empty (`ide:spark` creates it), so nginx starts either way. With [yiendos/my-sites-ide-certificates-certbot-cloudflare](https://github.com/yiendos/my-sites-ide-certificates-certbot-cloudflare) installed, `certificates:certbot-create <domain>` fills it, replacing the old `ide:ssl`.
