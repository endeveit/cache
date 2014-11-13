#!/usr/bin/env bash

DIR=$(readlink -enq $(dirname $0))

sudo apt-get -qq update &

# Enable custom APC
if [ "$(php -r 'echo PHP_VERSION_ID;')" -ge 50500 ]; then
    ( pecl install apcu < /dev/null || ( pecl config-set preferred_state beta; pecl install apcu < /dev/null ) && phpenv config-add "$DIR/apcu.ini" ) &
else
    ( CFLAGS="-O2 -g3 -fno-strict-aliasing" pecl upgrade apc < /dev/null; phpenv config-add "$DIR/apc.ini" ) &
fi

# Build XCache
curl -sS -o xcache-3.2.0.tar.gz http://xcache.lighttpd.net/pub/Releases/3.2.0/xcache-3.2.0.tar.gz
tar -xzf xcache-3.2.0.tar.gz
sh -c "cd xcache-3.2.0 && phpize && ./configure && make && sudo make install && cd .."
rm -rf xcache-3.2.0
rm xcache-3.2.0.tar.gz

# Enable extensions
phpenv config-add "$DIR/travis.ini"
