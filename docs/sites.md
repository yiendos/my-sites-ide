# Sites

## New site configuration

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
   │   │   └── 1-default-nginx.conf     //provide a vhost configuration for nginx (if you are using the nginx plugin)
   └── deploy                           //where your PHP app should be hosted (IDE_APP_DIR)
```

### Where your app lives

The IDE expects each site's application code in the same folder inside its repository, set by `IDE_APP_DIR` in `.env`:

| `IDE_APP_DIR` | App code in | |
|---|---|---|
| `deploy` | `Repos/<site>/deploy/` | the default |
| `Sites` | `Repos/<site>/Sites/` | the older layout |
| `.` | `Repos/<site>/` | an app at the root of the repository |

`ide:create-site` and `ide:repo-clone --laravel` create the app there, and plugins work there too - composer and npm installs, `artisan`, and the document root (`<app>/public`) in the vhosts web server plugins write for new sites. Existing vhosts in `Repos/<site>/_build/config/` aren't rewritten, so if you change it, update their paths by hand. It's one setting for every site.

Remember after each time you clone a repository/ create a new site to `./Repos` you should restart your IDE for these changes to take effect:

`php my-sites-ide ide:restart`

## Multiple installations of my-sites-ide

You can use my-sites-ide as many times as you like locally, because the system creates images and containers based off the `.env` NAMESPACE variable.

Therefore for different organisations/ projects you just need to provide a unique NAMESPACE variable.

Then when you build your images `php my-sites-ide ide:build` this unique NAMESPACE variable will create corresponding images.

For example: If you named one project `yiendos` via the `.env` NAMESPACE variable, images would be named `yiendos_fpm` etc

For another project: if you named this project `paul` via the `.env` NAMESPACE variable, images would be named `paul_fpm` etc

This way your projects are sandboxed.
