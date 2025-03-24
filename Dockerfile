FROM php:apache-bullseye

RUN a2enmod rewrite
RUN apt update
RUN apt install libzip-dev -y
RUN docker-php-ext-install zip
RUN docker-php-ext-install pdo
RUN docker-php-ext-install pdo_mysql
RUN apt install git -y

ADD . /var/www/html
RUN chown -R www-data:www-data /var/www/html

CMD 'apache2-foreground'
