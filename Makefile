

local: docker-local

docker-local: 

	docker build . -f _dev/environment/build/local/Dockerfile --build-arg SITE=joomla --target composer_base -t my_site_composer
	docker build . -f _dev/environment/build/local/Dockerfile --build-arg SITE=laravel --target frontend -t my_site_frontend
	docker build . -f _dev/environment/build/local/Dockerfile --target fpm_server -t my_site_fpm
	docker build . -f _dev/environment/build/local/Dockerfile --target nginx -t my_site_nginx
	docker build . -f _dev/environment/build/local/Dockerfile --target apache -t my_site_apache