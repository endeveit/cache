<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace Endeveit\Cache\Drivers;

/**
 * Driver that stores data in APC and uses php5-apc extension.
 */
class Apc extends Memcache
{

    /**
     * {@inheritdoc}
     *
     * @param array $options
     */
    public function __construct(array $options = array())
    {
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
        $id     = $this->getPrefixedIdentifier($id);
        $result = apc_inc($id, $value);

        if (false === $result) {
            if (true === apc_store($id, $value)) {
                return $value;
            }
        }

        return $result;
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
        $id     = $this->getPrefixedIdentifier($id);
        $result = apc_dec($id, $value);

        if (false === $result) {
            if (true === apc_store($id, -$value)) {
                return -$value;
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @param  string        $id
     * @return boolean|mixed
     */
    protected function doLoad($id)
    {
        $result = apc_fetch($this->getPrefixedIdentifier($id));

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
        $result = array();

        foreach ($identifiers as $id) {
            $data = apc_fetch($id);
            if (is_array($data) && isset($data[0])) {
                $result[$this->getIdentifierWithoutPrefix($id)] = $data[0];
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
        return apc_fetch($id);
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

        return apc_store(
            $this->getPrefixedIdentifier($id),
            array($data)
        );
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
        return apc_store($id, $data, $lifetime);
    }

    /**
     * {@inheritdoc}
     *
     * @return boolean
     */
    protected function doFlush()
    {
        return apc_clear_cache('user');
    }
}
