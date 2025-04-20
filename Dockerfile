FROM php:apache-bullseye

RUN a2enmod rewrite
RUN apt update
RUN apt install libzip-dev -y
RUN docker-php-ext-install zip
RUN docker-php-ext-install pdo
RUN docker-php-ext-install pdo_mysql
RUN docker-php-ext-install opcache

# jit isn't particularly useful for our use case.
#RUN echo "opcache.enable=1\n" \
#         "opcache.jit_buffer_size=128M\n" \
#         "opcache.jit=tracing" \
#    > /usr/local/etc/php/conf.d/custom_opcache_jit.ini

RUN apt install git -y

ADD . /var/www/html
RUN chown -R www-data:www-data /var/www/html

COPY entrypoint.sh /entrypoint.sh
ENTRYPOINT ["/bin/sh", "entrypoint.sh"]
CMD 'apache2-foreground'
