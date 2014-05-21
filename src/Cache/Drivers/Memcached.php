<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace Cache\Drivers;

use Cache\Drivers\Memcache;
use Cache\Exception;

/**
 * Driver almost the same as Memcache, but uses \Memcached instead of \Memcache.
 */
class Memcached extends Memcache
{
    /**
     * \Memcached connection object.
     *
     * @var \Memcached
     */
    protected $client;

    /**
     * {@inheritdoc}
     *
     * @param  \Memcached       $client
     * @param  boolean          $compress
     * @param  string           $prefix
     * @throws \Cache\Exception
     */
    public function __construct(\Memcached $client, $compress = false, $prefix = '')
    {
        $this->client = $client;

        if (!empty($prefix)) {
            try {
                $this->validateIdentifier($prefix);

                $this->prefix = $prefix;
            } catch (Exception $e) {
                throw new Exception('Invalid prefix');
            }
        }

        if ($compress) {
            $this->client->setOption(\Memcached::OPT_COMPRESSION, true);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param  string $id
     * @return mixed|false
     */
    protected function doLoad($id)
    {
        $result = $this->client->get($this->getPrefixedIdentifier($id));

        if (is_array($result) && isset($result[0])) {
            return $result[0];
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
        $result   = array();
        $prefixed = array_map(array($this, 'getPrefixedIdentifier'), $identifiers);

        foreach ($this->client->getMulti($prefixed) as $identifier => $row) {
            if (is_array($row) && isset($row[0])) {
                $result[$this->getIdentifierWithoutPrefix($identifier)] = $row[0];
            }
        }

        $this->fillNotFoundKeys($result, $identifiers);

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
        $this->validateIdentifier($id);

        if (!empty($tags)) {
            $this->saveTagsForId($id, $tags);
        }

        return $this->client->set(
            $this->getPrefixedIdentifier($id),
            array($data, time(), $lifetime),
            (integer) $lifetime
        );
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
        $tmp = $this->client->get($this->getPrefixedIdentifier($id));

        if (is_array($tmp)) {
            list($data, $mtime, $lifetime) = $tmp;

            // Calculate new lifetime
            $newLT = $lifetime - (time() - $mtime) + $extraLifetime;

            if ($newLT <= 0) {
                return false;
            }

            $data = array($data, time(), $newLT);

            // We try replace() first because set() seems to be slower
            $result = $this->client->replace($this->getPrefixedIdentifier($id), $data, $newLT);

            if (!$result) {
                $result = $this->client->set($this->getPrefixedIdentifier($id), $data, $newLT);
            }

            return $result;
        }

        return false;
    }

}
