# TechnicSolder
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
![Latest Stable Version](https://img.shields.io/badge/dynamic/json.svg?label=Latest%20Stable%20Version&url=https%3A%2F%2Fraw.githubusercontent.com%2FTheGameSpider%2FTechnicSolder%2Fmaster%2Fapi%2Fversion.json&query=version&colorB=brightgreen)
![Latest Dev Version](https://img.shields.io/badge/dynamic/json.svg?label=Latest%20Dev%20Version&url=https%3A%2F%2Fraw.githubusercontent.com%2FTheGameSpider%2FTechnicSolder%2FDev%2Fapi%2Fversion.json&query=version&colorB=orange)

>TechnicSolder is an API that sits between a modpack repository and the Technic Launcher. 
It allows you to easily manage multiple modpacks in one single location.

>Using Solder also means your packs will download each mod individually. This means the 
launcher can check MD5's against each version of a mod and if it hasn't changed, use 
the cached version of the mod instead. What does this mean? Small incremental updates 
to your modpack doesn't mean redownloading the whole thing every time!

>Solder also interfaces with the Technic Platform using an API key you can generate 
through your account there. When Solder has this key it can directly interact with 
your Platform account. When creating new modpacks you will be able to import any packs
you have registered in your Solder install. It will also create detailed mod lists on 
your Platform page! (assuming you have the respective data filled out in Solder) Neat huh?

-- Technic

TechnicSolder was originaly developed by Technic using the Laravel Framework. However, 
the application is difficult to install and use. Technic Solder - Solder.cf by TheGameSpider 
runs on pure PHP with zip and MySQL extensions and it's very easy to use. To install, you 
just need to install zip extension, setup MySQL database and download Solder to your server 
(No composer needed). And the usage is even easier! Just Drag n' Drop your mods.

## Docker installation (requires Docker + Docker Compose and SSH access)

Easiest method, but requires ssh, docker, and docker-compose on the host machine.
Also allows using the built-in updater if you're on the `dev` channel
(it will check and pull the newest changes for you using `git`).

Note this container image puts Technic Solder at `/var/www/html`, and not 
`/var/www/TechnicSolder`, as written later in this file.

On the remote machine,
Clone repository to a location of your choice (likely in your home folder)
```bash
git clone https://github.com/TheGameSpider/TechnicSolder TechnicSolder
```
change-directory to cloned folder
```bash
cd TechnicSolder
```
Open the compose.yaml file in vim, nano, or any other CLI/GUI editor
```
vim compose.yaml
```
Set `MYSQL_PASSWORD` to something secure, such as
```
      - MYSQL_PASSWORD=put_secure_password_here
```
Save and exit. You can also make any other changes you'd like such as 
setting up HTTPS or changing the port or other here.

Now build the image, bring it up, and detach (as opposed to keeping it running in your 
terminal window)
```bash
docker-compose up --build -d
```

Now open your server address, ie. ``http://localhost`` if running on a your local machine, 
and follow the set-up prompt there. 
- If using MySQL, set the user of the database to ``solder``, database name to ``solder``, 
host to ``db`` (or the hostname/ip of the database container), and the ```MYSQL_PASSWORD``` you created earlier. 
- If using SQLite, simply set the type to SQLite.
- For the Solder API key, go to [https://technicpack.net](https://technicpack.net), log 
in/create an account, go to my settings/profile, and click on "solder" on the left menu.

By default, the MySQL login details are:
- host: docker-db-1 (or just db)
- database: solder
- username: solder
- password: solder (which you changed previously)

## Installation overview (shared host)
If you are using a shared host, or for some reason don't have access to the command-line 
interface.

Requires some sort of configuration tool like cPanel.

- Set PHP version to 8.4. 
- Install the PHP ZIP, PDO, PDO_MYSQL extensions (requires at least SQLite version 3.45).
    - Enable one or both of pdo_mysql, pdo_sqlite 
- In Apache2 settings, enable RewriteEngine, and the PHP 8.4 module.
- Upload the contents of this git to your document root, such that index.php is directly in 
your document root folder. This is usually ``/var/www/html/index.php``
- Using phpMyAdmin, or any built-in cPanel MySQL editor, Create a new user ``solder``, 
database ``solder``, and grant the user access to the database. Make sure you write down your password.

Now open your server address, ie. ``http://localhost`` if running on a your local machine, 
and follow the set-up prompt there. 
- If using MySQL, set the user of the database to ``solder``, database name to ``solder``, 
host to either ``localhost`` (if the database is on the same machine and network as the web 
server) or the IP address of your database, and the MySQL password you created earlier. 
- If using SQLite, simply set the type to SQLite.

## Detailed Installation (SSH/CLI access required)
Manually install TechnicSolder and it's requirements.

**1. Install Debian Trixie (https://www.debian.org/), or any other distribution which has a recent (2025 as of writing) version of SQLite, PHP, and MariaDB. Log in to your admin user.** <br/>

**2. Install Apache2 and PHP stack**<br />
```bash
apt -y install apache2 libapache2-mod-php php8.4 php8.4-pdo php8.4-zip libzip-dev mariadb-server
```

**3. Enable the following extensions in php.ini**<br/>
```php
;extension=zip
;extension=pdo_sqlite
;extension=pdo_mysql
```

**4. Enable RewriteEngine, Configure Apache**<br />
```bash
a2enmod rewrite
cp /etc/apache2/sites-available/000-default.conf /etc/apache2/sites-available/TechnicSolder.conf
```
Enable the site
```bash
a2ensite TechnicSolder
```
Edit site configuration file with nano (or some other editor)
```bash
nano /etc/apache2/sites-enabled/TechnicSolder.conf
```

Add the following above the `DocumentRoot` line:
```apache
ServerName <yourSolderDomainHere>
```

Change the `DocumentRoot` line to:
```apache
DocumentRoot /var/www/TechnicSolder
```

Add this before `</VirtualHost>` close tag:
```apache
    DirectoryIndex index.php index.html
    <Directory /var/www/TechnicSolder>
        Options Indexes FollowSymLinks MultiViews
        AllowOverride All
        Require all granted
    </Directory>
```
Save and close the file and restart Apache:
```bash
service apache2 restart
```

**5. Clone TechnicSolder repository** <br/>
```bash
cd /var/www/
git clone https://github.com/TheGameSpider/TechnicSolder.git TechnicSolder
```

Make sure it's owned by www-data (or nginx for nginx)
```bash
chmod -R www-data /var/www/TechnicSolder
```

**6. MySQL configuration** <br/>

Login to mysql
```bash
mysql
```

Create new user
```sql
CREATE USER 'solder'@'localhost' IDENTIFIED BY 'YOUR MYSQL PASSWORD HERE';
```

<br />

Create database solder and grant user *solder* access to it.
```sql
CREATE DATABASE solder;
GRANT ALL ON solder.* TO 'solder'@'localhost';
FLUSH PRIVILEGES;
```

```sql
EXIT;
```

**7. Configure TechnicSolder** <br />

Configure the installation at `http://your_server_IP_address`.

The MySQL database password is whatever you set earlier, and the user/database is `solder`.

That's it. You have successfully installed and configured TechnicSolder. It's ready to use!


## If you are using Nginx instead of Apache

Here is an incomplete example for nginx configuration. 

For a complete (but unrelated) example, see [https://nginx.org/en/docs/example.html](https://nginx.org/en/docs/example.html).

For https/SSL, see [https://nginx.org/en/docs/http/configuring_https_servers.html](https://nginx.org/en/docs/http/configuring_https_servers.html).

You will also need to configure a PHP server seperately, eg. PHP-FPM, and make it available 
at `/run/php/php8.4-fpm.sock` or update the nginx configuration accordingly.

 ```nginx
    listen 80; 

    root /var/www/TechnicSolder;

    client_max_body_size 1G;

    location / {
        try_files   $uri $uri/ /index.php?$query_string;
    }

    location /api/ {
        try_files   $uri $uri/ /api/index.php?$query_string;
    }

    location ~* \.php$ {
        fastcgi_pass                    unix:/run/php/php8.4-fpm.sock;
        fastcgi_index                   index.php;
        fastcgi_split_path_info         ^(.+\.php)(.*)$;
        include                         fcgi.conf;
        fastcgi_param PATH_INFO         $fastcgi_path_info;
        fastcgi_param SCRIPT_FILENAME   $document_root$fastcgi_script_name;
        fastcgi_request_buffering off;
        fastcgi_max_temp_file_size 0;
    }

    location /config/ {
        deny all;
    }
    location /Dockerfile {
        deny all;
    }
    location /compose.yaml {
        deny all;
    }
    location ~ /\.ht {
        deny all;
    }
    location = /config/db\.sqlite$ {
                deny all;
    }
    location = /config/config\.json$ {
                deny all;
    }
    location ~ .*/\. {
        return 403;
    }

    error_page 403 /403.html;

 ```

## Caching

The docker containers in compose.yaml has redis already set up.

You can manually set up redis by installing and starting the redis server, and then setting the appropriate environment variables or configuration variables.

## Configuration file `config/config.json`
May not be up-to-date.
```json
{
    "configured": true,
    "db-type": "sqlite",
    "db-host": "db",
    "db-user": "solder",
    "db-pass": "solder",
    "db-name": "solder",
    "cache": "redis",
    "redis-host": "redis",
    "redis-port": 6379, 
    "redis-password": "",
    "host": "localhost",
    "protocol": "http",
    "dir": "/",
    "config_version": 2,
    "fabric_integration": "on",
    "forge_integration": "on",
    "neoforge_integration": "on",
    "modrinth_integration": "on",
    "api_key": "YOUR_TECHNICPACK_API_KEY_HERE",
    "dev_builds": "on",
    "enable_self_updater": "on"
}
```

Settings which are not exposed in the GUI:
- `configured`: Determine if the server has been configured. Don't change.
- `protocol`: Override which of `http` or `https` protocol is used.
- `config_version`: What version the config file is. Don't change.

## Environment variables

```
CACHE: "redis|none"
REDIS_HOST: "ip|hostname|path"
REDIS_PORT: port
REDIS_PASSWORD: "password"
```

## Updating

If you used the docker image, and are on the `dev` channel, you can use the built-in updater.

** If you come from version 1.4.0 **

Upgrade instructions
    1. Install new Technic Solder version. Either `git pull` over SSH, or re-upload the TechnicSolder folder and files; Make sure to preserve your mods, others, forges folders, as well as file functions/config.php
    2. Upgrade/switch to PHP8.3 and install and enable the PHP8.4-PDO, PHP8.4_PDO-MYSQL or/and PHP8.4-PDO_SQLITE, and PHP8.4-ZIP extensions.
    3. You will then need to run /functions/upgrade2.0.php to modify your existing database.

** If you come from version 1.3.4 **
Visit `/functions/upgrade2.0.php`

** If you come from a version before 1.3.4 **
Upgrade to 1.3.4 as per the [1.3.4 release notes](https://raw.githubusercontent.com/cowpod/TechnicSolder/refs/heads/master/api/version.json).

## Upload larger files > 1GB

Nextcloud has a great guide on this [here](https://docs.nextcloud.com/server/stable/admin_manual/configuration_files/big_file_upload_configuration.html).

Essentially, you'll want to update PHP's `.user.ini` file to something higher.
```php
upload_max_filesize=10G  
post_max_size=10G 
```

And then relevant settings in nginx/apache2, eg. for nginx
```nginx
client_max_body_size 10G;
```
And for apache whatever relevant setting.