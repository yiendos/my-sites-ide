### Prerequisites

Here is the list of prerequisites for your host system:

* Docker
* php (not strictly needed, we could organise things to run off docker-compose alone)
* composer (see above)

### Getting started 

1. `cd ~/Sites`
1. `git clone git@github.com:yiendos/my-sites-ide.git`
1. `cd ~/Sites/my-sites-ide`
1. `git checkout feature/16-pages `
1. `php _mysites/console/bin/mysites setup` (slow first time, building base images... much quicker next pages installation)
1. `my-sites-ide spark`

### Welcome to your new play area 

* http://localhost:8080/pages/hello - apache2
* http://localhost:8081/pages/hello - nginx
* http://localhost:8083 - Mailhog
* http://localhost:8084 - phpmyadmin
* http://localhost:3000 - Theia editor - Temporarily switched off for this branch to save memory issues on host

Database available at: -H 127.0.0.1 - P 3306 root:root

We hope you feel at home! 

### Continuous Deployment as Standard 

`my-sites-ide` came from 8 years working with deployment processes (Jenkins, Travis, github actions) and from this the IDE came into being. So going full circle we've included a barebones github actions integration for you. 

You'll see that it's easy to spark your IDE locally, and without even lifting a finger you have github actions CD support as well! 
