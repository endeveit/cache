#!/usr/bin/env bash

printf "no\n" | pecl install memcache
pecl install memcached
pecl install mongo
pecl install redis

echo "extension=memcache.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
echo "extension=memcached.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
echo "extension=mongo.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
echo "extension=redis.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
