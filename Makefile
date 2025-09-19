include .env

# The are a number of default varibles 
# ${APP} and ${NAMESPACE} to name but a few 
# These can be over-ridden by providing the name on the command line 
# make build-wait APP=fpm nginx

word: 

	@echo "the application consists of" ${APP}

# a set of commands for updating a project in production
update-project: pull composer-install db-migrate build-front rm-images build-prod restart
# a set of commands to initialize a project locally
init: build composer-install build-front key-generate storage-link db-migrate seed restart build-wait
# a set of commands for initializing a project on production
init-prod: build-prod composer-install build-front key-generate storage-link db-migrate seed restart build-prod

docker-local: 

	docker build . -f ${DOCKER_FILE} --target composer_base -t my_site_composer
	docker build . -f ${DOCKER_FILE} --target frontend -t my_site_frontend
	docker build . -f ${DOCKER_FILE} --target fpm_server -t my_site_fpm
	docker build . -f ${DOCKER_FILE} --target nginx -t my_site_nginx
	docker build . -f ${DOCKER_FILE} --target apache -t my_site_apache

build:

	@echo "Building containers"
	@docker compose --env-file .env up -d --build
	
build-wait:

	@echo "Building containers"
	@docker compose --env-file .env up -d ${APP} --build --wait

up:

	@echo "Starting containers"
	@docker compose --env-file .env up -d ${APP}  --remove-orphans

down: 

	@echo "Stopping contianers" 
	@docker compose --env-file .env down --remove-orphans

#build-prod:

#	@echo "Building containers"
#	@docker compose -f docker-compose.yml -f docker-compose.prod.yml --env-file .env up -d --wait --build

#up-prod:

#	@echo "Starting containers"
#	@docker compose -f docker-compose.yml -f docker-compose.prod.yml --env-file .env up -d --wait --remove-orphans

exec:

	@docker exec -it $$(docker ps -q -f name=${NAMESPACE}_fpm) /bin/sh

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

composer-install:

	@echo "Running composer install"
	@docker compose run --rm composer --working-dir=${SITE}/deploy install --no-scripts --ignore-platform-reqs --no-autoloader --prefer-dist
#	@docker compose run --rm composer --working-dir=${SITE} install --ignore-platform-reqs --prefer-dist

build-front:
    
	@echo "Building frontend for production"
	@docker compose run --rm node /usr/local/bin/npm --prefix ${SITE}/deploy install
	@docker compose run --rm node /usr/local/bin/npm --prefix ${SITE}/deploy run prod

db-migrate:
	
	@echo "Running database migrations"
	@docker exec -i $$(docker ps -q -f name=${NAMESPACE}_fpm) php ${SITE}/deploy/artisan migrate --force

#pull:
    
#	@echo "Updating project from git and rebuild"
#	@git pull

rm-images:
    
	@echo "Delete extra images"
	@docker system prune -f

key-generate:
    
	@echo "Key generate"
	@docker exec -i $$(docker ps -q -f name=${NAMESPACE}_fpm) php ${SITE}/deploy/artisan key:generate

storage-link:
    
	@echo "Storage Link"
	@docker exec -i $$(docker ps -q -f name=${NAMESPACE}_fpm) php ${SITE}/deploy/artisan storage:link

seed:
    
	@echo "Db Seed"
	@docker exec -i $$(docker ps -q -f name=${NAMESPACE}_fpm) php ${SITE}/deploy/artisan db:seed

restart:
    
	@echo "restart container"
	@docker restart ${NAMESPACE}_fpm

