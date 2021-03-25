![Screenshot](/screenshot.png?raw=true)

# my-sites-ide

`my-sites-ide` is designed to be an easy way to spin up new php development environments on the fly: 

`composer global require yiendos/my-sites-ide`

Create new play areas, or you can integrate the IDE into existing projects. Simply navigate to the folder of your choice and run: 

`mysites setup`

Followed by: 

`[project-name] spark` 

To launch your new play area... See `Your project path is important` below for further details.

---

### Prerequisites

Here is the list of prerequisites for your host system:

* Docker
* php 
* composer

### Initial setup 

Now you are going to need to add the global `~./composer/vendor/bin` to your path if you haven't already done this. 

To do this temporarily for the session of your terminal screen: 

```
export PATH=$PATH:~/.composer/vendor/bin
````

Or a more permanent solution: 

```
echo 'export PATH="$PATH:$HOME/.composer/vendor/bin"' >> ~/.bashrc

source ~/.bashrc
```

Or if you use zshrc: 

```
echo 'export PATH="$PATH:$HOME/.composer/vendor/bin"' >> ~/.zshrc

source ~/.zshrc && 
```
### Your project path is important

Where you choose to install the `my-sites-ide` is important. Let's say you wanted to host sites(s) at: `~/Sites/new-site`: 

* This would translate to project path of:  
`/Users/somebody/Sites/new-site` 

* With the project name of 
`new-site`

So you would therefore install the `my-site-ide` by issuing the following commands: 

* Create the folder if it doesn't already exist: 
```
mkdir -p ~/Sites/new-site && cd ~/Sites/new-site
```

* Install the IDE 
```
mysites setup
```

* Launch the IDE 
```
new-site spark
```

This can take a while for the first time, we are building apache, nginx, php base images for you. The next time you come to use `mysites setup` for a new project the installation time will be super speedy. 

Then you are able to control the docker instances through commands we've created under the project namespace. So if your project is `new-sites` typing this into the terminal will give you access to these commands. See `Controlling your my-sites-ide` section below.


### Apple M1/ ARM64 chip support 

Note if you want to use this on Arm64 chips, we need to handle mysql differently. Currently only mysql:8.0 is supported: 

`mysites setup -f docker-compose-arm.yml`

### Controlling your my-sites-ide 

We've made working with your my-sites-ide as easy as possible, as part of the `mysites setup` process we also created these commands under the project namespace. 

So once again if your site is hosted at `new-site`, then your new terminal commands will be available under the `new-site` namespace: 

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
  spark    Fire up your mysites IDE
  status   See the status of mysites containers
 wordpress
  wordpress:create  Create a WordPress site
  wordpress:delete  Nuke an existing site
  wordpress:vhost   Creates a new Apache2 and/or Nginx virtual host
``` 

### Welcome to your new play area 

* http://localhost:8080 - apache2
* http://localhost:8081 - nginx
* http://localhost:8083 - Mailhog
* http://localhost:8084 - phpmyadmin
* http://localhost:3000 - Theia editor

Database available at: -H 127.0.0.1 - P 3306 root:root

We hope you feel at home! 


### Continuous Deployment as Standard 

`my-sites-ide` came from 8 years working with deployment processes (Jenkins, Travis, github actions) and from this the IDE came into being. So going full circle we've included a barebones github actions integration for you. 

You'll see that it's easy to spark your IDE locally, and without even lifting a finger you have github actions CD support as well! 
