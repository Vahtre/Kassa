Kassa
=====

[![CI](https://github.com/zebraf1/Kassa/actions/workflows/ci.yml/badge.svg)](https://github.com/zebraf1/Kassa/actions/workflows/ci.yml)

Inventory system

Requirements
------------

* PHP 8.2 or 8.3 (CI covers both) with the `ctype`, `iconv`, `json`, `mbstring`, `openssl` and
  `pdo_mysql` extensions
* Composer 2
* MariaDB 11.4 - production runs 11.4, and **MySQL 8 will not work**: login hashes passwords with
  the server-side `PASSWORD()` function, which MySQL 8.0 removed
* Node.js - only needed to build the Polymer frontend, not to run or test the API

Setting up from scratch (Windows)
---------------------------------

These steps assume a machine with nothing installed yet. On Linux or macOS they are the same apart
from how PHP, Composer and MariaDB get installed (`apt` / Homebrew).

### 1. Clone the repository

```bash
git clone https://github.com/zebraf1/Kassa.git
cd Kassa
```

### 2. Install PHP

```bash
winget install --id PHP.PHP.8.3
```

The Windows builds ship every extension as a DLL but enable almost none of them. In the PHP install
directory, copy `php.ini-development` to `php.ini` if there is no `php.ini` yet, then uncomment:

```ini
extension=ctype
extension=curl
extension=fileinfo
extension=mbstring
extension=openssl
extension=pdo_mysql
```

Open a new terminal, so the updated `PATH` is picked up, and check the result:

```bash
php -v
php -m
```

### 3. Install Composer

There is no winget package for Composer - use the official Windows installer from
https://getcomposer.org/download/, which detects the PHP you just installed. Then check:

```bash
composer --version
```

### 4. Install MariaDB

Match the major version production runs:

```bash
winget install --id MariaDB.Server --version 11.4.3.0
```

The installer asks for a root password; remember it, step 6 needs it. Then check:

```bash
mariadb --version
```

### 5. Install the PHP dependencies

```bash
composer install
```

### 6. Configure the environment

Copy `.env.local.example` to `.env.local` and fill it in. The application reads three variables:

* `DATABASE_URL` - for example
  `mysql://root:<password>@127.0.0.1:3306/kassa?serverVersion=mariadb-11.4.3`. The `serverVersion`
  must carry the `mariadb-` prefix, otherwise Doctrine generates SQL for the wrong platform.
* `APP_SECRET` - any random string locally.
* `CORS_ALLOW_ORIGIN` - a regex, for example `^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$`.

`.env.local` is gitignored. Never commit real credentials.

### 7. Create the database

```bash
php bin/console doctrine:database:create
```

Create the schema from the Doctrine entity mappings. `migrations/` holds incremental changes
against the legacy schema only - there is no baseline migration that creates the tables - so a
fresh database is built directly from `src/App/Entity`:

```bash
php bin/console doctrine:schema:create
```

`schema:create` already produces everything the existing migrations do, so mark them as applied
instead of running them (running them against a fresh schema would fail on duplicate columns):

```bash
php bin/console doctrine:migrations:version --add --all --no-interaction
```

### 8. Load sample data

```bash
php bin/console hautelook:fixtures:load
```

This loads `fixtures/`, which includes the users the tests log in as.

### 9. Start the application

```bash
php -S 127.0.0.1:8000 -t public public/index.php
```

The app is now on http://127.0.0.1:8000. PHP's built-in server serves the files under `public/`
directly and routes everything else through `public/index.php`, which is all the API needs.

For a production-like setup, point a web server at `public/index.php` instead. On Apache that means
enabling these modules in `httpd.conf` and rewriting all traffic to the front controller:

```
LoadModule alias_module modules/mod_alias.so
LoadModule rewrite_module modules/mod_rewrite.so
```

Running the tests locally
-------------------------

The suite talks to a real database. In the test environment Doctrine appends a `_test` suffix (see
`when@test` in `config/packages/doctrine.yaml`), so a `DATABASE_URL` pointing at `kassa` gives you
`kassa_test` - your development data is not touched.

Create the test database once:

```bash
php bin/console doctrine:database:create --env=test
php bin/console doctrine:schema:create --env=test
php bin/console doctrine:migrations:version --add --all --no-interaction --env=test
```

Then run the suite:

```bash
vendor/bin/phpunit
```

`php bin/phpunit` is equivalent - it delegates to the same PHPUnit once `composer install` has run.

You do not need to seed the test database: the controller tests use Hautelook's
`RefreshDatabaseTrait`, which reloads `fixtures/` for each test class and rolls back afterwards.

A single file, or a single test by name:

```bash
vendor/bin/phpunit tests/Rotalia/API/Controller/ProductsControllerTest.php
vendor/bin/phpunit --filter testHash
```

After changing an entity, rebuild the test schema:

```bash
php bin/console doctrine:schema:drop --force --env=test
php bin/console doctrine:schema:create --env=test
```

The same steps run on every push and pull request against PHP 8.2 and 8.3 - see
`.github/workflows/ci.yml`.

Testing against a production dump
---------------------------------

A dump holds real member data. Keep it out of the repository (`*.sql` is gitignored) and delete it
when you are finished.

Import it into a **separate** database, so your fixture data survives:

```bash
mariadb -u root -p -e "CREATE DATABASE kassa_dump CHARACTER SET utf8mb4"
mariadb -u root -p kassa_dump < dump.sql
```

Point the application at it by editing `DATABASE_URL` in `.env.local`:

```
DATABASE_URL="mysql://root:<password>@127.0.0.1:3306/kassa_dump?serverVersion=mariadb-11.4.3"
```

A dump carries the legacy schema, which is exactly the case the migrations are written for - so
here you run them, rather than creating the schema from the entities:

```bash
php bin/console doctrine:migrations:migrate
```

Check that the entity mappings and the imported schema agree:

```bash
php bin/console doctrine:schema:validate
```

The mapping half must pass. The database half may report leftovers from the pre-Doctrine schema;
read those differences rather than applying them - `doctrine:schema:update` would rewrite tables
the dump depends on.

Then clear the cache and start the server as in step 9:

```bash
php bin/console cache:clear
php -S 127.0.0.1:8000 -t public public/index.php
```

Users from the dump can log in with their real passwords: the hashing is unchanged, and
`PASSWORD()` is evaluated by MariaDB exactly as it is in production.

To run the test suite against dump data instead of fixtures, import into `kassa_dump_test` - the
`_test` suffix Doctrine adds in the test environment - and remove `RefreshDatabaseTrait` from the
test you are debugging, otherwise the fixtures overwrite the imported rows.

Build the frontend
------------------

Bower installs the packages, Polymer builds the code:

```bash
npm install
cd src/Rotalia/FrontendBundle/Resources/source/
bower prune
bower install
bower update
polymer build
```

`polymer-cli` 1.x predates modern Node and fails on current releases. If the build errors out,
switch to Node 10 with [nvm-windows](https://github.com/coreybutler/nvm-windows). Neither the API
nor the test suite needs this step.

Deploy a new version
--------------------

`build.sh` runs `composer install`, clears the production cache, applies pending Doctrine
migrations and rebuilds the frontend:

```bash
sh build.sh
```

Other useful commands
---------------------

Add fields to a model:

```bash
php bin/console make:entity
```

Reload the fixtures (add `--env=test` to target the test database):

```bash
php bin/console hautelook:fixtures:load
```
