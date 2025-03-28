#!/bin/sh

chown -R www-data:www-data /var/www/html

# exec runuser -u www-data -- "$@"
# apache uses user www-data for us.
exec $@