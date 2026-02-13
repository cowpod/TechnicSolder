# TechnicSolder
[![License: MIT*](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
![Latest Stable Version](https://img.shields.io/badge/dynamic/json.svg?label=Latest%20Stable%20Version&url=https%3A%2F%2Fraw.githubusercontent.com%2Fcowpod%2FTechnicSolder%2Fmaster%2Fapi%2Fversion.json&query=version&colorB=brightgreen)
![Latest Dev Version](https://img.shields.io/badge/dynamic/json.svg?label=Latest%20Dev%20Version&url=https%3A%2F%2Fraw.githubusercontent.com%2Fcowpod%2FTechnicSolder%2Fdev%2Fapi%2Fversion.json&query=version&colorB=orange)

\* Modified MIT. See the [LICENSE](?tab=License-1-ov-file) file in this repository.

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

TechnicSolder was originaly developed by Technic using the Laravel Framework. However, the application is difficult to install and use. Technic Solder runs on pure PHP with Zip and PDO-SQLite extensions.

If you already have a webserver set up with PHP and ZIP/PDO extensions, simply upload the contents of this repository (eg. Download as ZIP and then extract) to the webserver document root, and then skip to the Configuration section below. This will give you a basic functional installation.

Additional extensions such as Redis, Opcache, and PDO-MySQL are highly recommended for better performance.

## Docker container

Requires Docker and Docker Compose on the host machine.

Also allows using the built-in updater if you're on the `dev` channel
(it will check and pull the newest changes for you using `git`).

Note this container image puts Technic Solder at `/var/www/html`, and not 
`/var/www/TechnicSolder`, as written later in this file.

On the remote machine,
Clone repository to a location of your choice (likely in your home folder)
```bash
git clone https://github.com/cowpod/TechnicSolder TechnicSolder
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

Finally, proceed to the Configuration section below. After that, you have successfully installed and configured TechnicSolder. It's ready to use!

## Manual Installation (SSH/CLI access required)

Manually install TechnicSolder and its requirements.

**1. Install Debian Trixie (https://www.debian.org/), or any other distribution which has a recent (2025 as of writing) version of PHP, SQLite (or MariaDB). Log in to your admin user.**

**2. Install Apache2 and PHP stack**

PHP package names may differ, ie. `php-pdo` or `php8-pdo` instead of `php8.4-pdo`.

```bash
apt -y install apache2 libapache2-mod-php php8.4 php8.4-pdo php8.4-zip libzip-dev
```

You may also want to install MySQL (MariaDB) instead of using SQLite, for better performance.
```bash
apt install -y mariadb-server
```

You may also want to install Redis, for better performance.
```bash
apt install -y redis-server
```

And the appropriate PHP Redis extension.
```bash
apt install -y php8.4-pear
pecl install --onlyreqdeps redis
```

If it's publicly accessible you should also set a Redis password.
```bash
nano /etc/redis/redis.conf
```

And set
```
requirepass YOUR_REDIS_PASSWORD_HERE
```

**3. Enable the following extensions in php.ini**

```php
;extension=zip
;extension=pdo_sqlites
;extension=pdo_mysql ; if you installed MySQL before.
;extension=opcache
;extension=redis ; if you installed Redis before
```
The lines may differ slightly in order or name (like `.so`). Some may already be enabled.

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
Save and close the file.

**5. Clone TechnicSolder repository** 

```bash
cd /var/www/
git clone https://github.com/cowpod/TechnicSolder.git TechnicSolder
```

Make sure it's owned by www-data (or nginx for nginx)
```bash
chmod -R www-data /var/www/TechnicSolder
```

**6. Start services**

First enable them
```bash
systemctl enable apache2
systemctl enable mariadb
systemctl enable redis-server # if you installed redis earlier
```
Then start them
```bash
systemctl start apache2
systemctl start mariadb
systemctl start redis-server # if you installed redis earlier
```

**7. MySQL configuration**

Login to mysql
```bash
mysql # or mariadb
```

Create new user
```sql
CREATE USER 'solder'@'localhost' IDENTIFIED BY 'YOUR MYSQL PASSWORD HERE';
```

Create database solder and grant user `solder` access to it.
```sql
CREATE DATABASE solder;
GRANT ALL ON solder.* TO 'solder'@'localhost';
FLUSH PRIVILEGES;
```

Exit.
```sql
EXIT;
```

Finally, proceed to Configuration section below. After that, you have successfully installed and configured TechnicSolder. It's ready to use!

## Configuration

Configure at `http://your_server_IP_address/configure`.

If you used docker, and filled in all the settings in `compose.yml`, you will just need to click Save.

- Fill in your new admin account credentials and name.
- The Solder API key needs to be retrieved from your [technicpack.net](https://technicpack.net) profile (click Edit Profile, then Solder Configuration).
- The database password is what you set earlier, and the user/database name is `solder`. You can also choose `SQLite` for smaller instances.
- Redis caching is recommended, but isn't necessary. If you installed it earlier, set the host to `localhost`, port to `6379`, and the password to what you set earlier.
- Set the hostname to your public server hostname (accessed by clients/users).
- Set the install directory to the path inside `/var/www/html` or `/var/www/TechnicSolder`. Likely `/`.

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

If you used the docker image, and are on the `dev` channel, you can use the built-in updater. And if necessary, you will then be prompted to upgrade on the web page.

**If you come from version 1.4.0**

1. Install new Technic Solder version. Either `git pull` over SSH, or re-upload the TechnicSolder folder and files; Make sure to preserve your mods, others, forges folders, as well as file functions/config.php
2. Upgrade/switch to PHP8.3 and install and enable the PHP8.4-PDO, PHP8.4_PDO-MYSQL or/and PHP8.4-PDO_SQLITE, and PHP8.4-ZIP extensions.
3. You will then need to visit `/functions/upgrade2.0.php` to modify your existing database.

**If you come from version 1.3.4**

Visit `/functions/upgrade2.0.php`

**If you come from a version before 1.3.4**

See the [1.3.4 release notes](https://raw.githubusercontent.com/cowpod/TheGameSpider/refs/heads/master/api/version.json).

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