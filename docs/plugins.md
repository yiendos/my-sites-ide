# Plugins

Extra services (security scanners, alternative servers, deployment targets) install as Composer packages of type `my-sites-ide-plugin`, e.g. [yiendos/my-sites-ide-security-zaproxy](https://github.com/yiendos/my-sites-ide-security-zaproxy) or [yiendos/my-sites-ide-servers-nginx](https://github.com/yiendos/my-sites-ide-servers-nginx).

To build your own, see [Writing a plugin](writing-a-plugin.md).

## Available plugins

| Plugin | What it adds | Starts with `ide:spark` |
|---|---|---|
| [yiendos/my-sites-ide-preprocessors-php](https://github.com/yiendos/my-sites-ide-preprocessors-php) | PHP - php-fpm serving your sites, and a cli container for artisan, queues and background jobs | yes |
| [yiendos/my-sites-ide-servers-nginx](https://github.com/yiendos/my-sites-ide-servers-nginx) | nginx web server, https://<site>.localhost | yes |
| [yiendos/my-sites-ide-mail-mailhog](https://github.com/yiendos/my-sites-ide-mail-mailhog) | MailHog, catches the mail your sites send - http://localhost:8025 | yes |
| [yiendos/my-sites-ide-databases-mysql](https://github.com/yiendos/my-sites-ide-databases-mysql) | MySQL 8.4, data kept in `storage/plugins/mysql/` | yes |
| [yiendos/my-sites-ide-caching-redis](https://github.com/yiendos/my-sites-ide-caching-redis) | Redis, for cache, sessions and queues | yes |
| [yiendos/my-sites-ide-databases-mariadb](https://github.com/yiendos/my-sites-ide-databases-mariadb) | MariaDB 11.8, data kept in `storage/plugins/mariadb/` | no - add `mariadb` to `APP` |
| [yiendos/my-sites-ide-editor-theia](https://github.com/yiendos/my-sites-ide-editor-theia) | Theia IDE in the browser - PHP, Composer, Claude Code and Xdebug debugging, settings kept in `storage/plugins/theia/` | no - add `theia` to `APP` |
| [yiendos/my-sites-ide-servers-apache](https://github.com/yiendos/my-sites-ide-servers-apache) | Apache web server, https://<site>.localhost:8443 | yes |
| [yiendos/my-sites-ide-servers-caddy](https://github.com/yiendos/my-sites-ide-servers-caddy) | Caddy web server with trusted local HTTPS, https://<site>.localhost:9443 | yes |
| [yiendos/my-sites-ide-build-composer](https://github.com/yiendos/my-sites-ide-build-composer) | Composer in a container - installs a site's PHP dependencies, including on `ide:repo-clone --laravel` | no - run on demand |
| [yiendos/my-sites-ide-build-node](https://github.com/yiendos/my-sites-ide-build-node) | Node and npm in a container - installs a site's npm dependencies and builds its assets, including on `ide:repo-clone --laravel` | no - run on demand |
| [yiendos/my-sites-ide-certificates-certbot-cloudflare](https://github.com/yiendos/my-sites-ide-certificates-certbot-cloudflare) | real Let's Encrypt certificates through Cloudflare DNS | no - run on demand |
| [yiendos/my-sites-ide-security-zaproxy](https://github.com/yiendos/my-sites-ide-security-zaproxy) | OWASP ZAP security scanning | no - run on demand |
| [yiendos/my-sites-ide-clusters-minikube](https://github.com/yiendos/my-sites-ide-clusters-minikube) | Minikube - a local Kubernetes cluster that mimics DigitalOcean, for test deployments (runs on your host: needs minikube, kubectl and helm) | no - `clusters:minikube-start-configure <site>` |
| [yiendos/my-sites-ide-deploy-k8s](https://github.com/yiendos/my-sites-ide-deploy-k8s) | Deploy sites to Kubernetes - production images, a container registry and a helm chart, for DigitalOcean (DOKS) or minikube | no - `deploy:k8s-build-push <site> <environment>` |
| [yiendos/my-sites-ide-monitoring-grafana](https://github.com/yiendos/my-sites-ide-monitoring-grafana) | Grafana, http://localhost:3000 - data sources for whichever monitoring plugins are installed, and container dashboards | no - `monitoring:grafana-start` |
| [yiendos/my-sites-ide-monitoring-prometheus](https://github.com/yiendos/my-sites-ide-monitoring-prometheus) | Prometheus metrics, scraping any IDE container labelled `prometheus.io/scrape`, and every container's CPU and memory from Alloy | no - `monitoring:prometheus-start` |
| [yiendos/my-sites-ide-monitoring-loki](https://github.com/yiendos/my-sites-ide-monitoring-loki) | Loki log storage | no - `monitoring:loki-start` |
| [yiendos/my-sites-ide-monitoring-tempo](https://github.com/yiendos/my-sites-ide-monitoring-tempo) | Tempo distributed tracing | no - `monitoring:tempo-start` |
| [yiendos/my-sites-ide-monitoring-alloy](https://github.com/yiendos/my-sites-ide-monitoring-alloy) | Grafana Alloy - ships the IDE's container logs to Loki and every container's CPU, memory and network to Prometheus, takes your apps' OpenTelemetry on `alloy:4318` | no - `monitoring:alloy-start` |

`composer.local-example.json` holds the default stack - PHP, nginx, MailHog, MySQL and Redis, the services `ide:spark` used to run before they became plugins. Add any of the others to your own `composer.local.json`.

## Installing plugins

Which plugins you use is your choice, so they're listed in your own `composer.local.json` (git ignored, merged into `composer.json` by [wikimedia/composer-merge-plugin](https://github.com/wikimedia/composer-merge-plugin)) rather than the tracked `composer.json`. `composer.lock` is git ignored for the same reason - every installation's set of plugins differs.

```
php my-sites-ide ide:plugin-search                              # find plugins on Packagist
cp composer.local-example.json composer.local.json              # first time only: the default stack, then add your plugins under "require"
composer update                                                 # install them
php my-sites-ide ide:plugin-env yiendos/my-sites-ide-security-zaproxy   # optional: copy its options into .env, commented out
php my-sites-ide ide:plugin-list                                # what's installed
```

Don't `composer require` a plugin - that writes to the tracked `composer.json`. A plugin that isn't on Packagist can be added through a `repositories` entry in `composer.local.json`, which is merged too.

## Overriding a plugin's settings

Each plugin ships its own `.env` with safe defaults, and the IDE's root `.env` overrides any of them. For example, the [php plugin](https://github.com/yiendos/my-sites-ide-preprocessors-php) disables `exec`, `shell_exec`, `phpinfo` and other risky functions in fpm by default. To lift every restriction while you test something that needs them, set the variable to nothing in the root `.env`:

```
PHP_DISABLE_FUNCTIONS=
```

Then recreate the container so it picks up the new value - a plain restart keeps the old environment:

```
docker compose up -d fpm
```

Take the line out again, and recreate the container, to go back to the plugin's default.

The root `.env` wins in both ways the IDE runs Docker Compose:

- **`docker compose` on its own** - `docker-compose.plugins.yml` lists each plugin's env files in this order: the plugin's `.env`, the root `.env`, then `_dev/cache/ide.env` (just `IDE_ROOT`). Later files win, so a root value replaces the plugin's.
- **`php my-sites-ide` commands** - the CLI loads the root `.env` first, then each plugin's `.env` without overwriting anything already set. An empty value counts as set, so `PHP_DISABLE_FUNCTIONS=` stays empty.

Only settings a plugin's `docker-compose.yml` reads from a variable (`${PHP_DISABLE_FUNCTIONS}`) can be overridden. Values written straight into its compose file can't be. Each plugin's README lists its variables, and `php my-sites-ide ide:plugin-env <plugin>` copies them into the root `.env`, commented out, ready to change.

To check what a service will actually get, run `docker compose config <service>`.

## Presets

A preset is a Composer metapackage that requires a set of plugins, so one line in `composer.local.json` installs them all - e.g. [yiendos/my-sites-ide-preset-monitoring](https://github.com/yiendos/my-sites-ide-preset-monitoring) for the five monitoring plugins:

```json
"yiendos/my-sites-ide-preset-monitoring": "@dev"
```

The plugins only have dev versions so far, and Composer ignores a preset's `@dev` flags - it only honours the root's. So the root `composer.json` sets `"minimum-stability": "dev"` with `"prefer-stable": true`: anything with a stable release still gets one.
