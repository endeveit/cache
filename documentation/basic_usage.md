Basic usage
=============

First of all please be sure satisfying requirements described in README.md
You'll need composer (http://getcomposer.org/download/) to start.
    $ composer.phar install

This will create directory vendor with some stuff inside. In your application write
    require "cache/vendor/autoload.php";
    $memcache = new Memcache();
    $memcache->connect("10.0.0.104", 11211);
    $cache = new Cache\Drivers\Memcache($memcache);
    $cache->save($someData, $someKey, $tagsList, $lifeTime);
    var_dump($cache->load($someKey));

List of currently available drivers you can find in directory src/Cache/Drivers/.
Each driver requires at least connection to caching backend as a constructor parameter and Mongo driver requires additionally database name.
Methods of caching interface are described below.

load($id)
---------

Loads previously saved data from cache and returns it. Takes mandatory argument $id which identifies value in cache storage.

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

Extends lifetime of item identified by $id addind $extraLifetime to it.
Total lifetime cannot be more than 31 days.


Notes on caching backend drivers
================================

For some caching backend drivers there are some limitation described in this chapter.
In case of PDO backend identifiers (item id or any tag) cannot be more than 255 bytes length.
When using Memcache backend identifiers should match ^[a-zA-Z0-9_]+$ regexp pattern.
Mongo backend provides ensureIndex() method which creates index on collection for fast search by tags and identifiers and it should be invoked at least once at first use (it's better to invoke it every time).
