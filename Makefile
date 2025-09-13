

local: docker-local

docker-local: 

	docker build . --no-cache -f _dev/environment/build/local/Dockerfile --target fpm_server -t my_site_fpm