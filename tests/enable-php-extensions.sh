#!/usr/bin/env bash

echo "extension=apc.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
echo "extension=memcache.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
echo "extension=memcached.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
echo "extension=mongo.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
echo "extension=redis.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
