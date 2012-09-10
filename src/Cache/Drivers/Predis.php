<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace Cache\Drivers;

use Cache\Interfaces\Driver as DriverInterface;
use Predis\Client;
use Predis\Network\PredisCluster;

/**
 * Driver that stores data in Redis and uses Predis library
 * to work with it.
 */
class Predis implements DriverInterface
{

    /**
     * The max lifetime of the data in cache (2 days).
     *
     * @const integer
     */
    const MAX_LIFETIME = 172800;

    /**
     * Predis object.
     *
     * @var \Predis\Client
     */
    protected $predis = null;

    /**
     * Used when deleting entries by keys.
     * If false don't delete keys immediately, instead store
     * the keys and then delete them in a single request.
     *
     * @var boolean
     */
    protected $forceDelete = true;

    /**
     * The keys that would be deleted in a single request.
     *
     * @var array
     */
    protected $forceDeleteKeys = array();

    /**
     * The keys that would be deleted from tags.
     *
     * @var array
     */
    protected $forceDeleteSrem = array();

    /**
     * Prefix for entries that stores tags.
     *
     * @var string
     */
    protected $tagPrefix = 'tags:';

    /**
     * The class constructor.
     *
     * @param \Predis\Client $predis
     */
    public function __construct(Client $predis)
    {
        $this->predis = $predis;
    }

    /**
     * {@inheritdoc}
     *
     * @param string $id
     * @return mixed|false Data on success, false on failure
     */
    public function load($id)
    {
        $result = @$this->predis->hgetall($id);

        if (is_array($result) && !empty($result['data'])) {
            $result = unserialize($result['data']);
        } else {
            $result = false;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @param mixed $data
     * @param string $id
     * @param array $tags
     * @param integer|boolean $lifetime
     * @return boolean
     */
    public function save($data, $id, array $tags = array(), $lifetime = false)
    {
        // Store the data in redis hash «data» and «tags» fields
        $r0 = $this->predis->hset($id, 'data', serialize($data));
        $r1 = $this->predis->hset($id, 'tags', serialize($tags));

        // The lifetime cannot be bigger than MAX_LIFETIME
        if (false !== $lifetime) {
            $lifetime = (integer) $lifetime;
            $this->predis->expire($id, ($lifetime > self::MAX_LIFETIME
                ? self::MAX_LIFETIME
                : $lifetime));
        } else {
            $this->predis->expire($id, self::MAX_LIFETIME);
        }

        // Store the tags
        if (!empty($tags) && is_array($tags)) {
            $tags = array_unique($tags);

            foreach ($tags as $tag) {
                $tag = $this->getTagWithPrefix($tag);
                $this->predis->sadd($tag, $id);
                $this->predis->expire($tag, self::MAX_LIFETIME);
            }
        }

        return $r0 && $r1;
    }

    /**
     * {@inheritdoc}
     *
     * @param string $id
     * @return boolean
     */
    public function remove($id)
    {
        $result = @$this->predis->hgetall($id);

        if (is_array($result) && !empty($result)) {
            // Remove the identifier from related tags
            $tags = (!empty($result['tags'])
                ? unserialize($result['tags'])
                : array());

            if (!empty($tags) && is_array($tags)) {
                $tags = array_unique($tags);
                foreach ($tags as $tag) {
                    $tag = $this->getTagWithPrefix($tag);

                    // Check if identifier in tags set
                    if ($this->predis->sismember($tag, $id)) {
                        // Remove the identifier from the set
                        if (!$this->forceDelete) {
                            if (!array_key_exists($tag, $this->forceDeleteSrem)) {
                                $this->forceDeleteSrem[$tag] = array();
                            }

                            $this->forceDeleteSrem[$tag][] = $id;
                        } else {
                            $this->predis->srem($tag, $id);
                        }
                    }

                    // If tag becomes empty, remove it
                    if ($this->forceDelete && 0 == $this->predis->scard($tag)) {
                        $this->predis->del($tag);
                    }
                }
            }
        }

        // Remove the identifier
        if (!$this->forceDelete) {
            $this->forceDeleteKeys[] = $id;
        } else {
            $this->predis->del($id);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @param array $tags
     * @return boolean
     */
    public function removeByTags(array $tags)
    {
        $this->forceDelete     = false;
        $this->forceDeleteKeys = array();
        $this->forceDeleteSrem = array();

        $tags = array_unique($tags);

        foreach ($tags as $tag) {
            $keys = $this->predis->smembers($this->getTagWithPrefix($tag));

            // Because with Predis we have a great chance to work in cluster
            // we must remove entries one by one
            if (is_array($keys)) {
                $keys = array_unique($keys);
                foreach ($keys as $key) {
                    $this->remove($key);
                }
            }

            $this->remove($tag);
        }

        if (!empty($this->forceDeleteSrem)) {
            foreach ($this->forceDeleteSrem as $tag => $identifiers) {
                $this->predis->srem($tag, $identifiers);
                if (0 == $this->predis->scard($tag)) {
                    $this->forceDeleteKeys[] = $tag;
                }
            }
        }

        if (!empty($this->forceDeleteKeys)) {
            $toDelete = array_values(array_unique($this->forceDeleteKeys));
            $cluster  = &$this->predis->getOptions()->cluster;

            if ($cluster && is_object($cluster) && ($cluster instanceof PredisCluster)) {
                $connections  = array();
                $keysToDelete = array();

                // Group keys by connections to which they related
                foreach ($toDelete as $key) {
                    $connection = &$cluster->getConnection(
                        $this->predis->createCommand('del', array($key))
                    );
                    $conName    = (string) $connection;

                    if (!array_key_exists($conName, $connections)) {
                        $connections[$conName] = &$connection;
                    }

                    if (!array_key_exists($conName, $keysToDelete)) {
                        $keysToDelete[$conName] = array();
                    }

                    $keysToDelete[$conName][] = $key;
                }

                // Remove the keys related to each connection
                foreach ($keysToDelete as $conName => $values) {
                    if (!empty($values)) {
                        $connections[$conName]->writeCommand(
                            $this->predis->createCommand('del', $values)
                        );
                    }
                }
            } else {
                $this->predis->del($toDelete);
            }
        }

        $this->forceDeleteSrem = array();
        $this->forceDeleteKeys = array();
        $this->forceDelete     = true;

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @param string $id
     * @param integer $extraLifetime
     * @return boolean
     */
    public function touch($id, $extraLifetime)
    {
        $result = $this->predis->ttl($id);

        if ($result && is_integer($result) && $result > 0) {
            $this->predis->expire($id, $result + $extraLifetime);

            return true;
        }

        return false;
    }

    /**
     * Returns the identifier for the tag.
     *
     * @param string $tag
     * @return string
     */
    protected function getTagWithPrefix($tag)
    {
        return $this->tagPrefix . $tag;
    }

}
