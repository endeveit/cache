Cache library [![Build Status](https://travis-ci.org/endeveit/cache.png?branch=v0.2)](https://travis-ci.org/endeveit/cache)
=============

Simple caching library with support for tags.

Requirements
------------

* PHP 5.3 and up
* PSR-0 autoloading

Storages
--------
* [Memcached](http://memcached.org/) (uses [Memcache PHP Driver](http://php.net/book.memcache.php))
* [mongoDB](http://www.mongodb.org/) (uses [MongoDB PHP Driver](http://php.net/book.mongo.php))
* [Redis](http://redis.io) (uses [Redis](https://github.com/nicolasff/phpredis/) extension or [Predis](https://github.com/nrk/predis) library)
* Relational databases (uses [PDO SQLite](http://php.net/ref.pdo-sqlite.php) or [PDO MySQL](http://php.net/ref.pdo-mysql.php))
* [XCache](http://xcache.lighttpd.net/)

Documentation
-------------

Some docs are in the «[documentation](https://github.com/endeveit/cache/tree/master/documentation)» directory.

There is also a simple wiki [here](https://github.com/endeveit/cache/wiki).
