from php:apache-bullseye

run rm -rf /var/www/html
add --chown=www-data . /var/www/html

run a2enmod rewrite
run apt update
run apt install libzip-dev -y
run docker-php-ext-install zip
run docker-php-ext-install pdo

# chown on add, and even RUN chown doesn't work
cmd sh -c 'chown -R www-data:www-data /var/www/html; apache2-foreground'
