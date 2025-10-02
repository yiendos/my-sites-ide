include .env

# The are a number of default varibles 
# ${APP} and ${NAMESPACE} to name but a few 
# These can be over-ridden by providing the name on the command line 
# make build-wait APP=fpm nginx
GREEN=\033[0;32m
RED=\033[0;31m
NC=\033[0m
# a set of commands for updating a project in production
update-project: pull composer-install db-migrate build-front rm-images build-prod restart
# a set of commands to initialize a project locally
init: build composer-install build-front key-generate storage-link db-migrate seed restart build-wait
# a set of commands for initializing a project on production
init-prod: build-prod composer-install build-front key-generate storage-link db-migrate seed restart build-prod	

start-minikube: up start-minikube minikube-storage install-certificates set-context minkube-nextsteps

deploy-minikube: build-production minikube-load deploy-site

restart:

	@echo "Restart the existing containers - after a configuration change" 

	@docker compose restart 
	
clone: 

	@echo "first clone the repository and --recurse-submodules into Sites/${NAME}" 
	git clone ${REPO} Repos/${NAME} --recurse-submodules 

	@echo "We need to make a few default directories" 

	@echo  Repos/${NAME}/Sites/storage/framework/sessions

	mkdir -p Repos/${NAME}/Sites/storage/framework/cache/data
	mkdir -p Repos/${NAME}/Sites/storage/framework/sessions
	mkdir -p Repos/${NAME}/Sites/storage/framework/testing 
	mkdir -p Repos/${NAME}/Sites/storage/framework/views 
	mkdir -p Repos/${NAME}/Sites/public

	@echo "" 
	@echo "We need to create the following: Repos/${NAME}/Sites/public/index.php" 

	wget ${LARAVEL_INDEX} -O Repos/${NAME}/Sites/public/index.php
#
#

#	as a new site has been added, we should reload the servers nginx | apache
	@echo "Now restart our docker services"
	@docker compose restart

	@echo ""
	@echo "To finish you will need to run the following commands:" 
	@echo ""
	@echo "./mysites composer SITE=${NAME}"
	@echo "" 
	@echo "./mysites assets SITE=${NAME}"
	@echo "./mysites migration SITE=${NAME}"

#This should be the first run
#It will build all the images that are required
build:

	@echo "Building containers"
	docker compose --env-file .env up -d composer node fpm nginx apache mariadb redis cli cron  --build
	
build-wait:

	@echo "Building containers"
	@docker compose --env-file .env up -d ${APP} --build --wait

up:

	@echo ""
	@echo "${GREEN}Starting containers${NC}"
	@echo "" 

	docker compose --env-file .env up -d ${APP}  --remove-orphans

down: 

	@echo "Stopping contianers" 
	@docker compose --env-file .env down --remove-orphans

status: 

	@echo "Lets see the status of running containers" 
	@docker container list 

exec:

	@docker exec -it $(docker ps -q -f name=${NAMESPACE}_fpm) /bin/sh

code-check:

	@echo "Perform a static analysis of the code base"
	@DOCKER_CLI_HINTS=false docker exec -it $$(docker ps -q -f name=${NAMESPACE}_fpm) vendor/bin/phpstan analyse --memory-limit=2G
	@echo "Perform a code rector"
	@DOCKER_CLI_HINTS=false docker exec -it $$(docker ps -q -f name=${NAMESPACE}_fpm) composer cs-rector
	@echo "Perform a code style check"
	@DOCKER_CLI_HINTS=false docker exec -it $$(docker ps -q -f name=${NAMESPACE}_fpm) composer cs-check

rector-fix:

	@echo "Fix code with rector"
	@DOCKER_CLI_HINTS=false docker exec -it $$(docker ps -q -f name=${NAMESPACE}_fpm) composer cs-rector-fix

code-baseline:

	@echo "Perform phpstan generate-baseline"
	@DOCKER_CLI_HINTS=false docker exec -it $$(docker ps -q -f name=${NAMESPACE}_fpm) vendor/bin/phpstan analyse --generate-baseline --memory-limit=2G

composer:

	@echo "Running composer install"
	@docker compose run --rm composer --working-dir=${SITE}/Sites install --no-scripts --ignore-platform-reqs --no-autoloader --prefer-dist
	@docker compose run --rm composer --working-dir=${SITE}/Sites install --ignore-platform-reqs --prefer-dist

assets:
    
	@echo "Building frontend for production"
	@docker compose run --rm node /usr/local/bin/npm --prefix ${SITE}/Sites install
	@docker compose run --rm node /usr/local/bin/npm --prefix ${SITE}/Sites run prod

migrate:
	
	@echo "Running database migrations"
	@docker exec -i $$(docker ps -q -f name=${NAMESPACE}_fpm) php ${SITE}/Sites/artisan migrate --force

#pull:
    
#	@echo "Updating project from git and rebuild"
#	@git pull

rm-images:
    
	@echo "Delete extra images"
	@docker system prune -f

key-generate:
    
	@echo "Key generate"
	@docker exec -i $$(docker ps -q -f name=${NAMESPACE}_fpm) php ${SITE}/Sites/artisan key:generate

storage-link:
    
	@echo "Storage Link"
	@docker exec -i $$(docker ps -q -f name=${NAMESPACE}_fpm) php ${SITE}/Sites/artisan storage:link

seed:
    
	@echo "Db Seed"
	@docker exec -i $$(docker ps -q -f name=${NAMESPACE}_fpm) php ${SITE}/Sites/artisan db:seed

