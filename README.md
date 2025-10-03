![Screenshot](https://raw.githubusercontent.com/yiendos/my-sites-ide/master/screenshot.png?raw=true)

# my-sites-ide

Is designed to be a modular approach to docker containerisation for PHP applications, only use what you want. Further the base images created are production ready and can be deployed via any containerisation technology.

These images can be used as part of a Continuous Integration/ deployment strategy also. Therefore by running the exact same images for your local, CI/CD/ staging/ production environments you can be assured of perfect results everytime. 

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

```
Available commands for the "ide" namespace:
  ide:build    Build the containers you wish
  ide:douse    Finished, until next time? Bring the containers down
  ide:restart  Cloned a new site, or made configuration changes? Restart the IDE
  ide:spark    Spark your creativity to life, by bringing the IDE up
```

## Hosting Repositories 

my-sites-ide can handle as many github repositories or individual projects you can through at them, some default structure should be applied. First clone any projects to the IDE under the `./Repos` folder, and each project should contain the following folder structure 

```
 PROJECT NAME                           //name of the project/ repository
   ├── _build
   │   ├── config
   │   │   ├── 1-default-apache.conf    //provide a vhost configuration for apache (if you are using)
   │   │   └── 1-default-nginx.conf     //provide a vhost configuration for nginx (if you are using)
   └── Sites                            //where your PHP app should be hosted 
``` 

After each time you clone a repository to `./Repos` you should restart your IDE: 

`php my-sites-ide ide:restart`