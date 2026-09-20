#!/bin/bash

if which composer; then
    composer install
else
    # Production setup
    php83-cli ../composer.phar install
fi

# TODO: once migrations/ is populated, run `php bin/console doctrine:migrations:migrate --no-interaction`
# here to bring the production schema up to date.
php bin/console cache:clear --env=prod

# shellcheck disable=SC2164
cd src/Rotalia/FrontendBundle/Resources/source/
../../../../../node_modules/bower/bin/bower prune
../../../../../node_modules/bower/bin/bower install
../../../../../node_modules/bower/bin/bower update
../../../../../node_modules/polymer-cli/bin/polymer.js build
