#!/bin/bash

if which composer; then
    composer install
else
    # Production setup
    php83-cli ../composer.phar install
fi

php bin/console cache:clear --env=prod

# Bring the production schema up to date. Production carries the legacy schema, so migrations/
# holds incremental changes only - there is no baseline migration that creates the tables.
php bin/console doctrine:migrations:migrate --no-interaction

# shellcheck disable=SC2164
cd src/Rotalia/FrontendBundle/Resources/source/
../../../../../node_modules/bower/bin/bower prune
../../../../../node_modules/bower/bin/bower install
../../../../../node_modules/bower/bin/bower update
../../../../../node_modules/polymer-cli/bin/polymer.js build
