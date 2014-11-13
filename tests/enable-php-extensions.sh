#!/usr/bin/env bash

DIR=$(readlink -enq $(dirname $0))
PHPINI="~/.phpenv/versions/$(phpenv version-name)/etc/conf.d/travis.ini"

sudo apt-get -qq update &

# Extensions available in Travis
echo "extension=memcache.so" >> $PHPINI
echo "extension=memcached.so" >> $PHPINI
echo "extension=mongo.so" >> $PHPINI
echo "extension=redis.so" >> $PHPINI

# Enable custom APC
if [ "$(php -r 'echo PHP_VERSION_ID;')" -ge 50500 ]; then
    ( pecl install apcu < /dev/null || ( pecl config-set preferred_state beta; pecl install apcu < /dev/null ) && phpenv config-add "$DIR/apcu.ini" ) &
else
    ( CFLAGS="-O2 -g3 -fno-strict-aliasing" pecl upgrade apc < /dev/null; phpenv config-add "$DIR/apc.ini" ) &
fi

# Enable XCache
curl -o xcache-3.2.0.tar.gz http://xcache.lighttpd.net/pub/Releases/3.2.0/xcache-3.2.0.tar.gz
tar -xzf xcache-3.2.0.tar.gz
sh -c "cd xcache-3.2.0 && phpize && ./configure && make && sudo make install && cd .."
rm -rf xcache-3.2.0
rm xcache-3.2.0.tar.gz
echo "extension=xcache.so" >> $PHPINI
echo "xcache.admin.enable_auth=Off" >> $PHPINI
echo "xcache.cacher=Off" >> $PHPINI
echo "xcache.var_size=1M" >> $PHPINI
