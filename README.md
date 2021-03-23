# my-sites-ide

`my-sites-ide` is designed to be an easy way to spin up new development environments; via way of a global composer dependency: 

`composer require yiendos/my-sites-ide`; 

once installed globally, you create as many new `my-sites-ide` hosted sites as you wish. 

`mysites setup`

### Apple M1/ ARM64 chip support 

Note if you want to use this on Arm64 chips, we need to handle mysql differently. Currently only mysql:8.0 is supported: 

`php _mysites/console/bin/mysites setup -f docker-compose-arm.yml`

### Your project path is important

Where you choose to install the `my-sites-ide` is important. Let's say you wanted to host sites(s) at: `~/Sites/new-site`: 

* This would translate to project path of:  
`/Users/somebody/Sites/new-site` 

* With the project name of 
`new-site`.

So you would therefore install the `my-site-ide` by issuing the following command from within the `new-site` folder:

`mysites setup`

### Controlling your my-sites-ide 

We've made working with your my-sites-ide as easy as possible, as part of the `mysites setup` process we also created these commands under the project namespace. 

So once again if your site is hosted at `new-site`, then your terminal commands are available under the `new-site` namespace: 

```
new-site 

Console Tool

Usage:
  command [options] [arguments]

Options:
  -h, --help            Display help for the given command. When no command is given display help for the list command
  -q, --quiet           Do not output any message
  -V, --version         Display this application version
      --ansi            Force ANSI output
      --no-ansi         Disable ANSI output
  -n, --no-interaction  Do not ask any interactive question
  -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Available commands:
  douse    Temporarily pause your sites
  export   Export a database(s)
  help     Displays help for a command
  list     Lists commands
  panic    Kindle won't launch? This is our troubleshooting command
  restart  Made changes locally? Restart the corresponding docker container
  setup    Create the initial mysites  configuration file
  spark    Fire up your mysites  IDE
  status   See the status of mysites  containers
``` 

### Welcome to your new project 

* http://localhost:8080 - apache2
* http://localhost:8081 - nginx
* http://localhost:8083 - Mailhog
* http://localhost:8084 - phpmyadmin
* http://localhost:3000 - Theia editor

Database available at: -H 127.0.0.1 - P 3306 root:root

We hope you feel at home! 