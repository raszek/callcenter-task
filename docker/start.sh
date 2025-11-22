#!/bin/sh
set -e

composer install

/var/www/html/app/bin/console cache:clear

chmod 777 /var/www/html/app/var

/root/.symfony5/bin/symfony server:start --allow-all-ip
