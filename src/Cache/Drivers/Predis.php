<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace Cache\Drivers;

use Cache\Abstractions\Common;
use Predis\Client;
use Predis\Pipeline\PipelineContext;

/**
 * Driver that stores data in Redis and uses Predis library
 * to work with it.
 */
class Predis extends Common
{

    /**
     * Predis object.
     *
     * @var \Predis\Client
     */
    protected $predis = null;

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
     * @param  string      $id
     * @return mixed|false Data on success, false on failure
     */
    public function load($id)
    {
        $result = $this->predis->hget($id, 'data');

        if (is_string($result) && !empty($result) && strlen($result) > 1) {
            $result = unserialize($result);
        } else {
            $result = false;
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
    public function save($data, $id, array $tags = array(), $lifetime = false)
    {
        $pipe = $this->predis->pipeline();

        // Store the data in redis hash «data» and «tags» fields
        $pipe->hset($id, 'data', serialize($data));
        $pipe->hset($id, 'tags', serialize($tags));

        $lifetime = $this->getFinalLifetime($lifetime);
        if ($lifetime > 0) {
            $pipe->expire($id, $lifetime);
        } else {
            $pipe->expire($id, self::MAX_LIFETIME);
        }

        // Store the tags
        if (!empty($tags) && is_array($tags)) {
            $tags = array_unique($tags);

            foreach ($tags as $tag) {
                $tag = $this->getTagWithPrefix($tag);
                $pipe->sadd($tag, $id);
                $pipe->expire($tag, self::MAX_LIFETIME);
            }
        }

        $pipe->execute();

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @param  string          $id
     * @param  PipelineContext $pipe
     * @return boolean
     */
    public function remove($id, PipelineContext $pipe = null)
    {
        // When calling method from removeByTags, don't execute pipeline instantly
        $instantExecute = false;

        if (null === $pipe) {
            $pipe           = $this->predis->pipeline();
            $instantExecute = true;
        }

        $tags = $pipe->getClient()->hget($id, 'tags');

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
            $tag = $this->getTagWithPrefix($tag);

            // If identifier in tags set remove it from the set
            if ($this->predis->sismember($tag, $id)) {
                $pipe->srem($tag, $id);
            }

            // If tag becomes empty, remove it
            if (0 == $this->predis->scard($tag)) {
                $pipe->del($tag);
            }
        }

        // Remove the identifier
        $pipe->del($id);

        if ($instantExecute) {
            $pipe->execute();
        }

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @param  array   $tags
     * @return boolean
     */
    public function removeByTags(array $tags)
    {
        $pipe = $this->predis->pipeline();

        foreach (array_unique($tags) as $tag) {
            $keys = $this->predis->smembers($this->getTagWithPrefix($tag));
            if (is_array($keys)) {
                $keys = new \ArrayIterator($keys);
            }

            // Because with Predis we have a great chance to work in cluster
            // we must remove entries one by one
            if (is_object($keys) && ($keys instanceof \Iterator)) {
                foreach ($keys as $key) {
                    $this->remove($key, $pipe);
                }
            };

            $this->remove($tag, $pipe);
        }

        $pipe->execute();

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @param  string  $id
     * @param  integer $extraLifetime
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
     * {@inheritdoc}
     *
     * @param  string  $id
     * @param  integer $value
     * @return integer
     */
    public function increment($id, $value = 1)
    {
        return $this->predis->incrby($id, $value);
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
        return $this->predis->decrby($id, $value);
    }

    /**
     * Returns the identifier for the tag.
     *
     * @param  string $tag
     * @return string
     */
    protected function getTagWithPrefix($tag)
    {
        return $this->tagPrefix . $tag;
    }

}
