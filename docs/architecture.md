# Architecture

my-sites-ide is a thin core - a Symfony Console app - that every service plugs into. Plugins are Composer packages: installing one is enough for its commands, containers and settings to join the IDE, with no core file edited.

## Component layers

```mermaid
flowchart TB
    subgraph host["① Host - what you touch"]
        direction LR
        cli["php my-sites-ide<br/>Symfony Console"]
        composer["composer install / update"]
        rootenv[".env<br/>NAMESPACE · APP · IDE_APP_DIR<br/>overrides any plugin setting"]
        local["composer.local.json<br/>your plugins and presets"]
    end

    subgraph core["② Core - _dev/console"]
        direction LR
        cmds["ide:* commands<br/>spark · douse · build · restart<br/>create-site · repo-clone · plugin-*"]
        discover["Plugins\Discover"]
        hooks["Plugins\Hooks<br/>site-created · site-dependencies · site-assets"]
    end

    subgraph plugins["③ Plugins - vendor/, type my-sites-ide-plugin"]
        direction LR
        manifest["each plugin ships<br/>console commands · docker-compose.yml · .env<br/>manifest in composer.json extra.my-sites-ide"]
        cats["preprocessors · servers · databases · caching<br/>mail · editor · build · certificates<br/>security · monitoring · clusters · deploy"]
    end

    subgraph generated["④ Generated wiring - git ignored"]
        direction LR
        pcompose["docker-compose.plugins.yml<br/>included by docker-compose.yml"]
        cache["_dev/cache/<br/>plugins.php · ide.env · storage/*.yml"]
    end

    subgraph runtime["⑤ Docker Compose stack - NAMESPACE_* images on the my-sites-ide network"]
        containers["plugin containers<br/>autostart plugins + the services in APP"]
    end

    subgraph files["⑥ Your files - bind mounted into the containers"]
        direction LR
        repos["Repos/*/<br/>_build/config vhosts · IDE_APP_DIR app code"]
        packages["Packages/<br/>shared code · local plugin clones"]
        storage["storage/<br/>plugins/* data · certificates"]
    end

    local --> composer
    composer -- installs --> plugins
    composer -- post-autoload-dump --> discover
    discover -- reads manifests --> manifest
    discover -- writes --> generated
    cli -- loads .env, then each plugin's .env --> rootenv
    cli -- registers --> cmds
    cli -- registers plugin commands --> manifest
    cmds -- create-site / repo-clone --> hooks
    hooks -- runs plugin commands --> manifest
    cmds -- docker compose up / down / build --> runtime
    pcompose --> runtime
    runtime --> files
```

1. **Host** - you run `php my-sites-ide`, and `composer install`/`update` with your plugins listed in `composer.local.json`. The root `.env` sets `NAMESPACE`, `APP` and `IDE_APP_DIR`, and overrides any plugin's setting.
2. **Core** - the `ide:*` commands, `Plugins\Discover`, which runs on Composer's `post-autoload-dump`, and `Plugins\Hooks`, which runs plugin commands on IDE events.
3. **Plugins** - Composer packages of type `my-sites-ide-plugin` in `vendor/`, each with a manifest in its `composer.json` (see [Writing a plugin](writing-a-plugin.md)).
4. **Generated wiring** - Discover writes `docker-compose.plugins.yml` and `_dev/cache/`, so `docker compose` and the CLI see every installed plugin.
5. **Docker Compose stack** - the autostart plugins plus the services in `APP`, built as `<NAMESPACE>_*` images on the `my-sites-ide` network.
6. **Your files** - `Repos/`, `Packages/` and `storage/`, bind mounted into the containers.

## Runtime - the containers

A typical stack once `ide:spark` is up. Solid boxes start by default (or via `APP`), dashed ones run on demand.

```mermaid
flowchart LR
    browser(["Browser"])

    subgraph net["Docker network: my-sites-ide"]
        subgraph servers["servers"]
            nginx["nginx<br/>https://site.localhost"]
            apache["apache<br/>:8443"]
            caddy["caddy<br/>:9443"]
        end

        subgraph php["preprocessors-php"]
            fpm["fpm :9000"]
            phpcli["cli<br/>artisan · queues · scheduler"]
        end

        subgraph data["databases · caching"]
            mysql[("mysql :3306")]
            mariadb[("mariadb :3306")]
            redis[("redis :6379")]
        end

        mailhog["mailhog<br/>SMTP :1025 · UI :8025"]
        theia["theia<br/>:3001"]

        subgraph build["build"]
            ccomposer["composer"]
            node["node"]
        end

        subgraph monitoring["monitoring"]
            alloy["alloy<br/>OTLP :4318"]
            loki["loki"]
            prometheus["prometheus"]
            tempo["tempo"]
            grafana["grafana<br/>:3000"]
        end

        zap["zaproxy"]
        certbot["certbot-cloudflare"]
    end

    k8s[("Kubernetes<br/>minikube · DOKS")]

    browser --> nginx & apache & caddy
    browser --> mailhog & theia & grafana
    nginx & apache & caddy -- FastCGI --> fpm
    fpm & phpcli --> mysql & mariadb & redis
    fpm & phpcli -- mail --> mailhog
    fpm -- OpenTelemetry --> alloy
    alloy --> loki & prometheus & tempo
    loki & prometheus & tempo --> grafana
    zap -. scans .-> nginx
    certbot -. storage/certificates .-> nginx
    net -. "deploy:k8s-build-push<br/>clusters:minikube-*" .-> k8s

    classDef ondemand stroke-dasharray: 5 5
    class mariadb,theia,ccomposer,node,alloy,loki,prometheus,tempo,grafana,zap,certbot ondemand
```

## Certificates

Sites use the IDE's self-signed certificate (`_dev/environment/servers/ssl/`) unless a certificate plugin issues a real one. Those plugins share one store, `storage/certificates/`, in the Let's Encrypt layout (`live/<domain>/fullchain.pem` linking into `archive/`). The [nginx plugin](https://github.com/yiendos/my-sites-ide-servers-nginx) always mounts it, at `/etc/nginx/ssl/live` and `/etc/nginx/ssl/archive`. Without a certificate plugin it's just empty (`ide:spark` creates it), so nginx starts either way. With [yiendos/my-sites-ide-certificates-certbot-cloudflare](https://github.com/yiendos/my-sites-ide-certificates-certbot-cloudflare) installed, `certificates:certbot-create <domain>` fills it, replacing the old `ide:ssl`.
