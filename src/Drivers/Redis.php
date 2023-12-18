<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace Endeveit\Cache\Drivers;

use Endeveit\Cache\Abstracts\Common;
use Endeveit\Cache\Exception;

/**
 * Driver that stores data in Redis and uses \Redis extension
 * to work with it.
 *
 * The implementation of consistent hashring was taken from Rediska project
 *  https://github.com/Shumkov/Rediska/blob/master/library/Rediska/KeyDistributor/ConsistentHashing.php
 */
class Redis extends Common
{
    const DEFAULT_PORT = 6379;
    const DEFAULT_TIMEOUT = 0.0;
    const DEFAULT_WEIGHT = 1;

    protected $connectionsOptions = array();
    protected $connections = array();

    protected $backendsWeights = array();
    protected $nbBackends = 0;

    protected $hashring = array();
    protected $nbHashrings = 0;

    protected $nativeExpires = false;

    protected $replicas = 256;
    protected $slicesCount = 0;
    protected $slicesHalf = 0;
    protected $slicesDiv = 0;

    protected $localCache = array();
    protected $localCacheCount = 0;
    protected $localCacheSize = 256;

    protected $hashringIsInitialized = false;

    /**
     * {@inheritdoc}
     *
     * Additional options:
     *  "local_cache_size" => the size of local cache
     *  "native_expires"   => use or not native expiration time
     *  "servers"          => array with connections parameters
     *                        array(
     *                          array('host' => '127.0.0.1', 'port' => 6379, 'timeout' => 0.0, 'weight' => 2, 'password' => ''),
     *                          array('host' => '127.0.0.1', 'port' => 6380, 'timeout' => 0.0, 'weight' => 1, 'password' => ''),
     *                          array('host' => '127.0.0.1', 'port' => 6381, 'timeout' => 0.0, 'weight' => 1, 'password' => ''),
     *                        )
     *
     * @codeCoverageIgnore
     * @param  array                     $options
     * @throws \Endeveit\Cache\Exception
     */
    public function __construct(array $options = array())
    {
        if (array_key_exists('local_cache_size', $options)) {
            $this->localCacheSize = intval($options['local_cache_size']);
            unset($options['local_cache_size']);
        }

        if (array_key_exists('native_expires', $options)) {
            $this->nativeExpires = (bool) $options['native_expires'];
            unset($options['native_expires']);
        }

        if (!array_key_exists('servers', $options) || !is_array($options['servers'])) {
            throw new Exception('You must provide option "servers" with array of connections parameters');
        }

        parent::__construct($options);

        foreach ($this->getOption('servers') as $server) {
            if (!array_key_exists('host', $server)) {
                throw new Exception('You must provide host in connection parameters');
            }

            $this->addConnection(
                $server['host'],
                array_key_exists('port', $server) ? intval($server['port']) : self::DEFAULT_PORT,
                array_key_exists('timeout', $server) ? floatval($server['timeout']) : self::DEFAULT_TIMEOUT,
                array_key_exists('weight', $server) ? intval($server['weight']) : self::DEFAULT_WEIGHT,
                array_key_exists('password', $server) ? $server['password'] : ''
            );
        }
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
        $id = $this->getPrefixedIdentifier($id);

        return $this->getConnection($id)->incrBy($id, $value);
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
        $id = $this->getPrefixedIdentifier($id);

        return $this->getConnection($id)->decrBy($id, $value);
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
    protected function addConnection($host, $port, $timeout, $weight, $password)
    {
        $key = crc32(json_encode(array($host, $port)));
        if (isset($this->backendsWeights[$key])) {
            throw new Exception('Connection with the same parameters already exists.');
        }

        $this->backendsWeights[$key] = $weight;
        $this->connectionsOptions[$key] = array($host, $port, $timeout, $password);

        $this->nbBackends++;

        $this->hashringIsInitialized = false;
    }

    /**
     * {@inheritdoc}
     *
     * @param  string      $id
     * @return mixed|false
     */
    protected function doLoad($id)
    {
        $source = $this->getConnection($id)->get($id);

        if (false !== $source) {
            if (is_string($source) && !is_numeric($source)) {
                $source = $this->getSerializer()->unserialize($source);
            }

            return $this->getProcessedLoadedValue($source);
        }

        return false;
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
        $now    = time();

        foreach (array_keys($this->connectionsOptions) as $key) {
            $mGetResult = $this->getRedisObject($key)->mGet($identifiers);

            if ($mGetResult === false) {
                continue;
            }

            foreach ($mGetResult as $i => $row) {
                if (empty($row)) {
                    continue;
                }

                $id = $this->getIdentifierWithoutPrefix($identifiers[$i]);

                if (is_string($row) && !is_numeric($row)) {
                    $source = $this->getSerializer()->unserialize($row);
                } else {
                    $source = array(
                        'data' => $row
                    );
                }

                if (array_key_exists('expiresAt', $source) && ($source['expiresAt'] < $now)) {
                    $result[$id] = false;
                } else {
                    $result[$id] = $source['data'];
                }
            }
        }

        $this->fillNotFoundKeys($result, $identifiers);

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @param  string      $id
     * @return mixed|false
     */
    protected function doLoadRaw($id)
    {
        $result = $this->getConnection($id)->get($id);

        return !empty($result) ? $result : false;
    }

    /**
     * {@inheritdoc}
     *
     * @param  mixed   $data
     * @param  string  $id
     * @param  array   $tags
     * @return boolean
     */
    protected function doSave($data, $id, array $tags = array())
    {
        $conn   = $this->getConnection($id);
        $result = $conn->set($id, $this->getSerializer()->serialize($data));

        if ($this->nativeExpires && array_key_exists('expiresAt', $data) && is_int($data['expiresAt'])) {
            $conn->expireAt($id, $data['expiresAt']);
        }

        if (!empty($tags)) {
            foreach (array_unique($tags) as $tag) {
                $this->getConnection($tag)->sAdd($tag, $id);
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @param  mixed           $data
     * @param  string          $id
     * @param  integer|boolean $lifetime
     * @return boolean
     */
    protected function doSaveScalar($data, $id, $lifetime = false)
    {
        $con    = $this->getConnection($id);
        $result = $con->set($id, $data);

        if (false !== $lifetime) {
            $con->expire($id, $lifetime);
        }

        return $result;
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
            $tag  = $this->getPrefixedTag($tag);
            $con  = $this->getConnection($tag);
            $keys = $con->sMembers($tag);

            if (!empty($keys)) {
                foreach ($keys as $key) {
                    $this->remove($this->getIdentifierWithoutPrefix($key));
                }
            };

            $con->del($tag);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @return boolean
     */
    protected function doFlush()
    {
        foreach (array_keys($this->connectionsOptions) as $key) {
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

        if (null === $return || !array_key_exists($return, $this->connectionsOptions)) {
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
                $this->connectionsOptions[$key][0],
                $this->connectionsOptions[$key][1],
                $this->connectionsOptions[$key][2]
            );

            if (!empty($this->connectionsOptions[$key][3])) {
                $this->connections[$key]->auth($this->connectionsOptions[$key][3]);
            }
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
