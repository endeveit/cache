<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace Endeveit\Cache\Drivers;

use Endeveit\Cache\Abstracts\Redis as AbstractRedis;
use Endeveit\Cache\Exception;

/**
 * Driver that stores data in Redis and uses \Redis extension
 * to work with it.
 *
 * The implementation of consistent hashring taken from Rediska project
 *  https://github.com/Shumkov/Rediska/blob/master/library/Rediska/KeyDistributor/ConsistentHashing.php
 */
class Redis extends AbstractRedis
{

    const DEFAULT_WEIGHT = 1;

    protected $options = array();
    protected $connections = array();

    protected $backendsWeights = array();
    protected $nbBackends = 0;

    protected $hashring = array();
    protected $nbHashrings = 0;

    protected $replicas = 256;
    protected $slicesCount = 0;
    protected $slicesHalf = 0;
    protected $slicesDiv = 0;

    protected $localCache = array();
    protected $localCacheCount = 0;
    protected $localCacheSize = 256;

    protected $hashringIsInitialized = false;

    protected $hIncrByIsSupported = false;

    /**
     * The class constructor.
     *
     * @param  integer         $localCacheSize
     * @throws \LogicException
     */
    public function __construct($localCacheSize = 256)
    {
        if (!extension_loaded('redis')) {
            throw new \LogicException('The redis extension is needed to use this cache adapter');
        }

        $this->localCacheSize = intval($localCacheSize);
        $this->hIncrByIsSupported = version_compare(phpversion('redis'), '2.2.4', '>=');
    }

    /**
     * Adds new connection to connections pool.
     *
     * @param  string                    $host
     * @param  integer                   $port
     * @param  float                     $timeout
     * @param  integer                   $weight
     * @throws \Endeveit\Cache\Exception
     */
    public function addConnection($host, $port = 6379, $timeout = 0.0, $weight = self::DEFAULT_WEIGHT)
    {
        $key = crc32(json_encode(array($host, $port)));
        if (isset($this->backendsWeights[$key])) {
            throw new Exception('Connection with the same parameters already exists.');
        }

        $this->backendsWeights[$key] = $weight;
        $this->options[$key] = array($host, $port, $timeout);

        $this->nbBackends++;

        $this->hashringIsInitialized = false;
    }

    /**
     * {@inheritdoc}
     *
     * @param  string  $id
     * @param  integer $value
     * @return integer
     */
    public function increment($id, $value = 1)
    {
        $connection = $this->getConnection($id);

        if (!$connection->exists($id)) {
            $connection->hSet($id, 'data', $value);

            return $value;
        }

        if ($this->hIncrByIsSupported) {
            $connection->hIncrBy($id, 'data', $value);
        } else {
            return $connection->eval(sprintf("return redis.call('HINCRBY','%s','data',%d)", $id, $value));
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param  string  $id
     * @param  integer $value
     * @return integer
     */
    public function decrement($id, $value = 1)
    {
        $connection = $this->getConnection($id);

        if (!$connection->exists($id)) {
            $value = 0 - $value;
            $connection->hSet($id, 'data', $value);

            return $value;
        }

        if ($this->hIncrByIsSupported) {
            $connection->hDecrBy($id, 'data', $value);
        } else {
            return $connection->eval(sprintf("return redis.call('HDECRBY','%s','data',%d)", $id, $value));
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param  string      $id
     * @return mixed|false
     */
    protected function doLoad($id)
    {
        $result = $this->getConnection($id)->hGet($id, 'data');

        if (!empty($result) && is_string($result) && strlen($result) > 1) {
            if (is_numeric($result)) {
                $result = intval($result);
            } else {
                $result = unserialize($result);
            }
        } else {
            $result = false;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @param  array $identifiers
     * @return array
     */
    protected function doLoadMany(array $identifiers)
    {
        $result = array();

        foreach ($identifiers as $identifier) {
            $row = $this->getConnection($identifier)->hGet($identifier, 'data');
            if (!empty($row) && is_string($row) && strlen($row) > 1) {
                $result[$identifier] = unserialize($row);
            } else {
                $result[$identifier] = false;
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @param  mixed           $data
     * @param  string          $id
     * @param  array           $tags
     * @param  integer|boolean $lifetime
     * @return boolean
     */
    protected function doSave($data, $id, array $tags = array(), $lifetime = false)
    {
        $dataPipe = $this->getConnection($id)->multi();

        // Store the data in redis hash «data» and «tags» fields
        $dataPipe->hSet($id, 'data', serialize($data));
        $dataPipe->hset($id, 'tags', serialize($tags));

        $lifetime = $this->getFinalLifetime($lifetime);
        if ($lifetime > 0) {
            $dataPipe->expire($id, $lifetime);
        } else {
            $dataPipe->expire($id, self::MAX_LIFETIME);
        }

        // Store the tags
        if (!empty($tags) && is_array($tags)) {
            $tags = array_unique($tags);

            foreach ($tags as $tag) {
                $tag     = $this->getTagWithPrefix($tag);
                $tagPipe = $this->getConnection($tag);
                $tagPipe->sAdd($tag, $id);
                $tagPipe->expire($tag, self::MAX_LIFETIME);
                $tagPipe->exec();
            }
        }

        $dataPipe->exec();

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @param  string  $id
     * @return boolean
     */
    protected function doRemove($id)
    {
        $con  = $this->getConnection($id);
        $tags = $con->hGet($id, 'tags');

        if (!empty($tags)) {
            $tags = trim($tags);

            if (0 === strpos($tags, 'a:')) {
                $tags = unserialize($tags);
            } else {
                $tags = (array) $tags;
            }
        } else {
            $tags = array();
        }

        // Remove the identifier from related tags
        foreach ($tags as $tag) {
            $this->getConnection($tag)->sRem($tag, $id);
        }

        // Remove the identifier
        $con->del($id);

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @param  array   $tags
     * @return boolean
     */
    protected function doRemoveByTags(array $tags)
    {
        foreach (array_unique($tags) as $tag) {
            $tag  = $this->getTagWithPrefix($tag);
            $keys = $this->getConnection($tag)->sMembers($tag);

            if (!empty($keys)) {
                foreach ($keys as $key) {
                    $this->remove($key);
                }
            };

            $this->doRemove($tag);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @param  string  $id
     * @param  integer $extraLifetime
     * @return boolean
     */
    protected function doTouch($id, $extraLifetime)
    {
        $con    = $this->getConnection($id);
        $result = $con->ttl($id);

        if ($result && is_integer($result) && $result > 0) {
            $con->expire($id, $result + $extraLifetime);

            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     *
     * @return boolean
     */
    protected function doFlush()
    {
        foreach (array_keys($this->options) as $key) {
            $this->getRedisObject($key)->flushDB();
        }

        return true;
    }

    /**
     * Returns connection by key name.
     *
     * @param  string                    $id
     * @return \Redis
     * @throws \RuntimeException
     * @throws \Endeveit\Cache\Exception
     */
    private function getConnection($id)
    {
        if (0 == $this->nbBackends) {
            throw new Exception('You must add at least one connection.');
        }

        // Initialize the return value.
        $return = null;

        // If we have only one backend, return it.
        if ($this->nbBackends == 1) {
            reset($this->backendsWeights);
            $return = key($this->backendsWeights);
        } else {
            if (!$this->hashringIsInitialized) {
                $this->initializeHashring();
                $this->hashringIsInitialized = true;
            }

            // If the key has already been mapped, return the cached entry.
            if ($this->localCacheSize > 0 && isset($this->localCache[$id])) {
                $return = $this->localCache[$id];
            } else {
                $crc32 = crc32($id);

                // Select the slice to begin with.
                $slice = floor($crc32 / $this->slicesDiv) + $this->slicesHalf;

                // This counter prevents going through more than 1 loop.
                $looped = false;

                while (true) {
                    // Go through the hashring, one slice at a time.
                    foreach ($this->hashring[$slice] as $position => $backend) {
                        // If we have a usable backend, add to the return array.
                        if ($position >= $crc32) {
                            // If $count = 1, no more checks are necessary.
                            $return = $backend;
                            break 2;
                        }
                    }

                    // Continue to the next slice.
                    $slice++;

                    // If at the end of the hashring.
                    if ($slice >= $this->slicesCount) {
                        // If already looped once, something is wrong.
                        if ($looped) {
                            break;
                        }

                        // Otherwise, loop back to the beginning.
                        $crc32 = -2147483648;
                        $slice = 0;
                        $looped = true;
                    }
                }

                // Cache the result for quick retrieval in the future.
                if ($this->localCacheSize > 0) {
                    // Add to internal cache.
                    $this->localCache[$id] = $return;
                    $this->localCacheCount++;

                    // If the cache is getting too big, clear it.
                    if ($this->localCacheCount > $this->localCacheSize) {
                        $this->cleanBackendsCache();
                    }
                }
            }
        }

        if (null === $return || !array_key_exists($return, $this->options)) {
            throw new \RuntimeException('Unable to determine connection or it\'s options.');
        }

        return $this->getRedisObject($return);
    }

    /**
     * Returns \Redis object by key value.
     *
     * @param  integer $key
     * @return \Redis
     */
    private function getRedisObject($key)
    {
        if (!array_key_exists($key, $this->connections)) {
            $this->connections[$key] = new \Redis();
            $this->connections[$key]->connect(
                $this->options[$key][0],
                $this->options[$key][1],
                $this->options[$key][2]
            );
        }

        return $this->connections[$key];
    }

    /**
     * Initialization of hashring.
     */
    private function initializeHashring()
    {
        if ($this->nbBackends < 2) {
            $this->hashring = array();
            $this->nbHashrings = 0;

            $this->slicesCount = 0;
            $this->slicesHalf = 0;
            $this->slicesDiv = 0;
        } else {
            $this->slicesCount = ($this->replicas * $this->nbBackends) / 8;
            $this->slicesHalf = $this->slicesCount / 2;
            $this->slicesDiv = (2147483648 / $this->slicesHalf);

            // Initialize the hashring.
            $this->hashring = array_fill(0, $this->slicesCount, array());

            // Calculate the average weight.
            $avg = round(array_sum($this->backendsWeights) / $this->nbBackends, 2);

            // Interate over the backends.
            foreach ($this->backendsWeights as $backend => $weight) {
                // Adjust the weight.
                $weight = round(($weight / $avg) * $this->replicas);

                // Create as many replicas as $weight.
                for ($i = 0; $i < $weight; $i++) {
                    $position = crc32($backend . ':' . $i);
                    $slice = floor($position / $this->slicesDiv) + $this->slicesHalf;
                    $this->hashring[$slice][$position] = $backend;
                }
            }

            // Sort each slice of the hashring.
            for ($i = 0; $i < $this->slicesCount; $i++) {
                ksort($this->hashring[$i], SORT_NUMERIC);
            }
        }

        $this->cleanBackendsCache();
    }

    /**
     * Cleans up the local cache.
     */
    private function cleanBackendsCache()
    {
        $this->localCache = array();
        $this->localCacheCount = 0;
    }
}
