#!/usr/bin/env bash

printf "no\n" | pecl install memcache > /dev/null
pecl install mongo > /dev/null
pecl install redis > /dev/null

echo "extension=memcache.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
echo "extension=mongo.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
echo "extension=redis.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
