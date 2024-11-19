from php:apache-bullseye

run a2enmod rewrite
run apt update
run apt install libzip-dev -y
run docker-php-ext-install zip
run docker-php-ext-install pdo
run docker-php-ext-install pdo_mysql

# chown on add, and even RUN chown doesn't work
cmd sh -c 'chown -R www-data:www-data /var/www/html/config /var/www/html/mods /var/www/html/others /var/www/html/forges; apache2-foreground'
