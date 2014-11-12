#!/usr/bin/env bash

DIR=$(readlink -enq $(dirname $0))

sudo apt-get -qq update &

# Enable APC
if [ "$(php -r 'echo PHP_VERSION_ID;')" -ge 50500 ]; then
    ( pecl install apcu < /dev/null || ( pecl config-set preferred_state beta; pecl install apcu < /dev/null ) && phpenv config-add "$DIR/apcu.ini" ) &
else
    ( CFLAGS="-O2 -g3 -fno-strict-aliasing" pecl upgrade apc < /dev/null; phpenv config-add "$DIR/apc.ini" ) &
fi

echo "extension=memcache.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
echo "extension=memcached.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
echo "extension=mongo.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
echo "extension=redis.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
