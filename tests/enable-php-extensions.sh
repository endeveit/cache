#!/usr/bin/env bash

DIR=$(readlink -enq $(dirname $0))

sudo apt-get -qq update &

# Enable custom APC
if [ "$(php -r 'echo PHP_VERSION_ID;')" -ge 50500 ]; then
    ( pecl install apcu < /dev/null || ( pecl config-set preferred_state beta; pecl install apcu < /dev/null ) && phpenv config-add "$DIR/z-apcu.ini" ) &
else
    ( CFLAGS="-O2 -g3 -fno-strict-aliasing" pecl upgrade apc < /dev/null; phpenv config-add "$DIR/z-apc.ini" ) &
fi

# Enable extensions
phpenv config-add "$DIR/z-travis.ini"
