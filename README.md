Cache library
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
* [Redis](http://redis.io) (uses [Predis](https://github.com/nrk/predis))
* Relational databases (uses [PDO SQLite](http://php.net/ref.pdo-sqlite.php) or [PDO MySQL](http://php.net/ref.pdo-mysql.php))
