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
use Predis\Client;
use Predis\Pipeline\PipelineContext;

/**
 * Driver that stores data in Redis and uses Predis library
 * to work with it.
 */
class Predis extends AbstractRedis
{

    /**
     * Predis object.
     *
     * @var \Predis\Client
     */
    protected $client = null;

    /**
     * The class constructor.
     *
     * @param \Predis\Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
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
        return $this->client->incrby($id, $value);
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
        return $this->client->decrby($id, $value);
    }

    /**
     * {@inheritdoc}
     *
     * @param  string      $id
     * @return mixed|false
     */
    protected function doLoad($id)
    {
        $result = $this->client->hget($id, 'data');

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
     * @param  array $identifiers
     * @return array
     */
    protected function doLoadMany(array $identifiers)
    {
        $result = array();
        $pipe   = $this->client->pipeline();

        foreach ($identifiers as $identifier) {
            $pipe->hget($identifier, 'data');
        }

        foreach ($pipe->execute() as $key => $row) {
            if (is_string($row) && !empty($row) && strlen($row) > 1) {
                $result[$identifiers[$key]] = unserialize($row);
            } else {
                $result[$identifiers[$key]] = false;
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
        $pipe = $this->client->pipeline();

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
     * @param  string                           $id
     * @param  \Predis\Pipeline\PipelineContext $pipe
     * @return boolean
     */
    protected function doRemove($id, PipelineContext $pipe = null)
    {
        // When calling method from removeByTags, don't execute pipeline instantly
        $instantExecute = false;

        if (null === $pipe) {
            $pipe           = $this->client->pipeline();
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
            $pipe->srem($this->getTagWithPrefix($tag), $id);
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
    protected function doRemoveByTags(array $tags)
    {
        $pipe = $this->client->pipeline();

        foreach (array_unique($tags) as $tag) {
            $keys = $this->client->smembers($this->getTagWithPrefix($tag));
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

            $this->doRemove($tag, $pipe);
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
    protected function doTouch($id, $extraLifetime)
    {
        $result = $this->client->ttl($id);

        if ($result && is_integer($result) && $result > 0) {
            $this->client->expire($id, $result + $extraLifetime);

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
        $this->client->flushdb();

        return true;
    }

}
