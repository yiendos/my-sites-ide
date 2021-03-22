## my-sites-ide

Checkout this repository: 
`git clone git@github.com:yiendos/my-sites-ide.git ~/Sites/my-sites-ide`

To test out ARM 64 support checkout the corresponding branch: 
`git checkout feature/3-arm`

Navigate to where you have checked out the repository: 

`cd ~/Sites/my-sites-ide` 

Followed by:

`php _mysites/console/bin/mysites setup`

Note if you want to use this on Arm64 chips, we need to handle mysql differently. Currently only mysql:8.0 is supported: 

`php _mysites/console/bin/mysites setup -f docker-compose-arm.yml`

### Controlling your my-sites-ide 

We've made working with your my-sites-ide as easy as possible, as part of the `mysites setup` process we also created these commands under the project namespace. 

So once again if your site is hosted at `my-sites-ide`, then your terminal commands are available under the `my-sites-ide` namespace: 

```
my-sites-ide

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


