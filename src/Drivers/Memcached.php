<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Igor Borodikhin <iborodikhin@gmail.com>
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace Endeveit\Cache\Drivers;

use Endeveit\Cache\Exception;

/**
 * Driver almost the same as Memcache, but uses \Memcached instead of \Memcache.
 */
class Memcached extends Memcache
{

    /**
     * {@inheritdoc}
     *
     * Additional options:
     *  "client"   => the instance of \Memcached object
     *
     * @param  array                     $options
     * @throws \Endeveit\Cache\Exception
     */
    public function __construct(array $options = array())
    {
        if (!array_key_exists('client', $options)) {
            throw new Exception('You must provide option "client" with \Memcached object');
        }

        parent::__construct($options);
    }

    /**
     * {@inheritdoc}
     *
     * @param  string      $id
     * @return mixed|false
     */
    protected function doLoad($id)
    {
        $result = $this->getOption('client')->get($id);

        if (!empty($result) && is_array($result)) {
            return $result;
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

        foreach ($this->getOption('client')->getMulti($identifiers) as $id => $entry) {
            if (!empty($entry) && is_array($entry)) {
                $result[$this->getIdentifierWithoutPrefix($id)] = $entry['data'];
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
        return $this->getOption('client')->get($id);
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
        $this->validateIdentifier($id);

        if (!empty($tags)) {
            $this->saveTagsForId($id, $tags);
        }

        return $this->getOption('client')->set($id, $data);
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
        return $this->getOption('client')->set($id, $data, $lifetime);
    }
}
