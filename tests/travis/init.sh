#!/usr/bin/env bash

DIR=$(readlink -enq $(dirname $0))
PHP_VERSION=$(php -r 'echo PHP_VERSION_ID;')

sudo apt-get -qq update &

# Enable custom APC
if [ $PHP_VERSION -ge 50500 ]; then
    ( pecl install apcu < /dev/null || ( pecl config-set preferred_state beta; pecl install apcu < /dev/null ) && phpenv config-add "$DIR/z-apcu.ini" ) &
else
    ( CFLAGS="-O2 -g3 -fno-strict-aliasing" pecl upgrade apc < /dev/null; phpenv config-add "$DIR/z-apc.ini" ) &
fi

# Enable igbinary for PHP version less than 5.4 and greater than 5.5
if [ $PHP_VERSION -lt 50400 ] || [ $PHP_VERSION -ge 50500 ] ; then
    ( pecl install igbinary < /dev/null ) &
fi

# Enable extensions
phpenv config-add "$DIR/z-travis.ini"

sh -c "/usr/bin/redis-server $DIR/redis-node-1.conf --dir ${DIR}/redis --include ${DIR}/redis-common.conf"
sh -c "/usr/bin/redis-server $DIR/redis-node-2.conf --dir ${DIR}/redis --include ${DIR}/redis-common.conf"
