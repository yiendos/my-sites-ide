## Steps to run on the command line

cd ~/Sites/my-sites-ide

php my-sites-ide ide:zap-context stockman
php my-sites-ide ide:zap-scan https://stockman.test --context=stockman --user=demo --full
docker compose stop zaproxy