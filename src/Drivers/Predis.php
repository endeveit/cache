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
use Predis\Client;
use Predis\Connection\Aggregate\PredisCluster;
use Predis\Response\Status;

/**
 * Driver that stores data in Redis and uses Predis library
 * to work with it.
 */
class Predis extends Common
{

    /**
     * {@inheritdoc}
     *
     * Additional options:
     *  "client"   => the instance of \Predis\Client object
     *
     * @codeCoverageIgnore
     * @param  array                     $options
     * @throws \Endeveit\Cache\Exception
     */
    public function __construct(array $options = array())
    {
        if (!array_key_exists('client', $options) || !($options['client'] instanceof Client)) {
            throw new Exception('You must provide option "client" with \Predis\Client object');
        }

        parent::__construct($options);
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
        return $this->getOption('client')->incrby($this->getPrefixedIdentifier($id), $value);
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
        return $this->getOption('client')->decrby($this->getPrefixedIdentifier($id), $value);
    }

    /**
     * {@inheritdoc}
     *
     * @param  string      $id
     * @return mixed|false
     */
    protected function doLoad($id)
    {
        $result = false;
        $source = $this->getOption('client')->get($id);

        if (!empty($source) && is_string($source)) {
            $result = $this->getSerializer()->unserialize($source);
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
        $pipe   = $this->getOption('client')->pipeline();
        $now    = time();

        foreach ($identifiers as $id) {
            $pipe->get($id);
        }

        foreach ($pipe->execute() as $key => $row) {
            $source = $this->getSerializer()->unserialize($row);
            $id     = $this->getIdentifierWithoutPrefix($identifiers[$key]);

            if (!empty($source) && is_array($source) && array_key_exists('data', $source)) {
                if (array_key_exists('expiresAt', $source) && ($source['expiresAt'] < $now)) {
                    $result[$id] = false;
                } else {
                    $result[$id] = $source['data'];
                }
            } else {
                $result[$id] = false;
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
        $result = $this->getOption('client')->get($id);

        return !empty($result) ? $this->getSerializer()->unserialize($result) : false;
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
        $pipe = $this->getOption('client')->pipeline();

        $pipe->set($id, $this->getSerializer()->serialize($data));

        // Store the tags
        if (!empty($tags)) {
            foreach (array_unique($tags) as $tag) {
                $pipe->sadd($tag, $id);
            }
        }

        $pipe->execute();

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @param  scalar          $data
     * @param  string          $id
     * @param  integer|boolean $lifetime
     * @return boolean
     */
    protected function doSaveScalar($data, $id, $lifetime = false)
    {
        $result = false;

        try {
            $result = $this->isStatusOk($this->getOption('client')->set($id, $this->getSerializer()->serialize($data)));

            if (false !== $lifetime) {
                $result = $this->isStatusOk($this->getOption('client')->expire($id, $lifetime));
            }
        } catch (\Exception $e) {
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
        $pipe = $this->getOption('client')->pipeline();

        foreach (array_unique($tags) as $tag) {
            $tag         = $this->getPrefixedTag($tag);
            $identifiers = $this->getOption('client')->smembers($tag);

            if (is_array($identifiers)) {
                $identifiers = new \ArrayIterator($identifiers);
            }

            // Because with Predis we have a great chance to work in cluster
            // we must remove entries one by one
            if (is_object($identifiers) && ($identifiers instanceof \Iterator)) {
                foreach ($identifiers as $id) {
                    $this->remove($this->getIdentifierWithoutPrefix($id));
                }
            };

            $pipe->del($tag);
        }

        $pipe->execute();

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @return boolean
     */
    protected function doFlush()
    {
        $result = true;

        if ($this->getOption('client')->getConnection() instanceof PredisCluster) {
            /** @var \ArrayIterator $iterator */
            $iterator = $this->getOption('client')->getConnection()->getIterator();

            while ($iterator->valid()) {
                if (!$this->isStatusOk($this->getOption('client')->getClientFor($iterator->key())->flushdb())) {
                    $result = false;
                }

                $iterator->next();
            }
        } else {
            $result = $this->isStatusOk($this->getOption('client')->flushdb());
        }

        return $result;
    }

    /**
     * Checks that response status is «OK».
     *
     * @param  \Predis\Response\Status $status
     * @return boolean
     */
    private function isStatusOk(Status $status)
    {
        return 0 === strcasecmp($status->getPayload(), 'ok');
    }
}
