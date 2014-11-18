Basic usage
===========

First of all please be sure satisfying requirements described in README.md
You'll need [Composer](http://getcomposer.org/download/) to start.

    $ composer.phar install

This will create directory vendor with some stuff inside. In your application write

```php
require "cache/vendor/autoload.php";
$memcache = new Memcache();
$memcache->addServer('10.0.0.1', 11211);
$memcache->addServer('10.0.0.2', 11211);
$cache = new Endeveit\Cache\Drivers\Memcache(array('client' => $memcache));
$cache->save($someData, $someKey, $tagsList, $lifeTime);
var_dump($cache->load($someKey));
```

List of currently available drivers you can find in directory src/Cache/Drivers/.

Each driver requires at least connection to caching backend as a constructor parameter and Mongo driver requires additionally database name.

Methods of caching interface are described below.

load($id, $lockTimeout = null)
------------------------------

Loads previously saved data from cache and returns it. Takes mandatory argument $id which identifies value in cache storage.

If $lockTimeout is provided, library will check for lock related to key $id.

If lock is found, library will return old data. If not then library will set lock and returns false.

save($data, $id, array $tags = array(), $lifetime = false)
----------------------------------------------------------

Saves data in cache assotiating $id to it and optionally tagging it with $tags and setting max lifetime with $lifetime.

Takes mandatory arguments $data (data which you desire to cache itself) and $id (unique identifier in cache storage) and also optional arguments $tags (array of string-valued tags to mark item in cache with) and $lifetime (number of seconds item would live in cache).

If specified $lifetime and it's more than 0 the item would be automatically removed from cache after specified number of seconds and would be available during this time interval.

Lifetime cannot be more than 31 days.

remove($id)
-----------

Deletes item identified by $id from cache storage.

removeByTags(array $tags)
-------------------------

Deletes all items marked with $tags from cache storage.
If empty array is given does nothing.

touch($id, $extraLifetime)
--------------------------

Extends lifetime of item identified by $id adding $extraLifetime to it.
Total lifetime cannot be more than 31 days.

contains($id)
--------------------------

Test if an entry exists in the cache.

flush()
-------------------------

Drops all items from cache.


Notes on caching backend drivers
================================

For some caching backend drivers there are some limitation described in this chapter.

When using Memcache backend identifiers should match ^[\S]+$ regexp pattern.


Serializers
===========

You can change serialization method with constructor option "serializer".
Currently available serializers are "BuiltIn" and "Igbinary".
