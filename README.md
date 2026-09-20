Kassa
=====

Inventory system

Clone Repository
----------------

```bash
git clone https://github.com/zebraf1/Kassa.git
```

Installation
------------

Vendors are installed via composer. See https://getcomposer.org/download/ for updated installation guide.

Install vendors for project:
```bash
php composer.phar install
```

Note: if you install composer to a runnable directory (ie --install-dir=/usr/bin) and set --filename=composer:
```bash
composer install
```

At the end of the installation fill in parameters (saved to .env.local)

Install nodejs packages
```bash
npm install bower polymer-cli
```

App is started from public/index.php. Ensure this is the entry point configured by web server.
For Apache there is a public/.htaccess file which configures all traffic to index.php.
ModRewrite must be enabled. Find your Apache config file (httpd.conf) and make sure to have these enabled:
```
LoadModule alias_module modules/mod_alias.so
LoadModule rewrite_module modules/mod_rewrite.so
```

Database
--------

Create the database:
```bash
php bin/console doctrine:database:create
```

Create the schema from the Doctrine entity mappings. There are no migrations on this branch yet
(see `migrations/`), so the schema is created directly from `src/App/Entity`:
```bash
php bin/console doctrine:schema:create
```

Once migrations exist, use those instead to keep the schema up to date:
```bash
php bin/console doctrine:migrations:migrate
```

Build frontend - bower installs packages, polymer builds code
```bash
cd src/Rotalia/FrontendBundle/Resources/source/
bower prune
bower install
bower update
polymer build
```

Deploy new version: composer install, cache clear, bower install, polymer build
```bash
sh build.sh
```

Testing
-------

Run unit and functional tests with PHPUnit

```bash
php bin/phpunit
```

Add fields to a model:
```bash
php bin/console make:entity
```

Import fixtures to local database (add --env=test if needed):
```bash
php bin/console hautelook:fixtures:load
```
