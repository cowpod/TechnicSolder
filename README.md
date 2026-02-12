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
You may also want to set the other empty variables `SOLDER_API_KEY`,`ADMIN_EMAIL`,`ADMIN_PASSWORD`,`ADMIN_NAME`,`HOST`. See the Environment section below.

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

## Detailed Installation (SSH/CLI access required)
Manually install TechnicSolder and it's requirements.

**1. Install Debian Trixie (https://www.debian.org/), or any other distribution which has a recent (2025 as of writing) version of SQLite, PHP, and MariaDB. Log in to your admin user.**

**2. Install Apache2 and PHP stack**

```bash
apt -y install apache2 libapache2-mod-php php8.4 php8.4-pdo php8.4-zip libzip-dev mariadb-server
```

**3. Enable the following extensions in php.ini**

```php
;extension=zip
;extension=pdo_sqlite
;extension=pdo_mysql
;extension=redis
;extension=opcache
```
The lines may differ slightly (eg. `.so`). Some may already be enabled.

**4. Enable RewriteEngine, Configure Apache**

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

**5. Clone TechnicSolder repository** 

```bash
cd /var/www/
git clone https://github.com/TheGameSpider/TechnicSolder.git TechnicSolder
```

Make sure it's owned by www-data (or nginx for nginx)
```bash
chmod -R www-data /var/www/TechnicSolder
```

**6. MySQL configuration**

Login to mysql
```bash
mysql
```

Create new user
```sql
CREATE USER 'solder'@'localhost' IDENTIFIED BY 'YOUR MYSQL PASSWORD HERE';
```

Create database solder and grant user *solder* access to it.
```sql
CREATE DATABASE solder;
GRANT ALL ON solder.* TO 'solder'@'localhost';
FLUSH PRIVILEGES;
```

```sql
EXIT;
```

**7. Configure TechnicSolder**

Configure the installation at `http://your_server_IP_address`.

The MySQL database password is what you set earlier, and the user/database is `solder`.

That's it. You have successfully installed and configured TechnicSolder. It's ready to use!

## Caching

If you used docker, redis caching is already set up.

You can manually set up redis by installing and starting a redis server, and then setting the appropriate environment variables or configuration variables.

## Configuration file `config/config.json`
May not be up-to-date.
```json
{
    "configured": true,
    "db-type": "sqlite",
    "db-host": "",
    "db-user": "",
    "db-pass": "",
    "db-name": "",
    "cache": "none",
    "redis-host": "",
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
      - SOLDER_API_KEY=
      - ADMIN_EMAIL=
      - ADMIN_PASSWORD=
      - ADMIN_NAME=
      - HOST=
      - DIR=/
      - DB_TYPE=mysql
      - MYSQL_HOST=db
      - MYSQL_NAME=solder
      - MYSQL_USER=solder
      - MYSQL_PASSWORD=solder
      - CACHE=redis
      - REDIS_HOST=redis
      - REDIS_PORT=6379


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