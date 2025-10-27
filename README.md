# my-sites-ide 

Welcome to my-sites-ide, contained within this project is over 8 years of experience working with Docker, condensed, distilled into one lean mean Dev-ops code base. There are many features: 

* Modular - pick which services you require - Apache or Nginx or both, Mysql or Mariab or all  
* Small image size, all images < 200mb - Yet still contain all the php goodies you require for Laravel. 
* Blazingly fast build, CI, deployment of containers - Because of the small image size all waiting times are reduced
* Configurable, the main .env can override the settings of all the docker containers being run
Allowing you full control over how your sites are run, failing that you are free to modify the original Dockerfiles for total configuration-city 

You can run the same images for your local environment, CI/CD/ staging/ and production environments you can be assured of perfect results everytime. 

## installation 

* `git clone git@github.com:yiendos/my-sites-ide.git`

* `cd my-sites-ide`

* `git checkout feature/31-update` 

* `cp env-example .env` 

* `php my-sites-ide ide:build` 

Now you can access your default homepage: 

* https://localhost/ [nginx]

* https://localhost:8443/ [apache]

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
* Apache 
* Mariadb 
* MySQL
* Redis 
* PHP-CLI 
* PHP cron

A default list of Applications are defined in the `./env` file: 

`APP="fpm,nginx,apache,mariadb,redis,cli,cron"`

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

* https://example.localhost:8443 [apache]

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
   │   │   ├── 1-default-apache.conf    //provide a vhost configuration for apache (if you are using)
   │   │   └── 1-default-nginx.conf     //provide a vhost configuration for nginx (if you are using)
   └── Sites                            //where your PHP app should be hosted 
``` 

Remember after each time you clone a repository/ create a new site to `./Repos` you should restart your IDE for these changes to take effect: 

`php my-sites-ide ide:restart`