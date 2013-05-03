<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Igor Borodikhin <gmail@iborodikhin.net>
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace Cache\Drivers;

/**
 * Driver that stores data in XCache and uses php5-xcache extension.
 */
class XCache extends Memcache
{

    /**
     * Class constructor to override parent __construct method
     */
    public function __construct()
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
        return xcache_inc($id, $value);
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
        return xcache_dec($id, $value);
    }

    /**
     * {@inheritdoc}
     *
     * @param  string        $id
     * @return boolean|mixed
     */
    protected function doLoad($id)
    {
        $result = xcache_get($id);

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

        foreach ($identifiers as $identifier) {
            $data = xcache_get($identifier);
            if (is_array($data) && isset($data[0])) {
                $result[$identifier] = $data[0];
            }
        }

        $this->fillNotFoundKeys($result, $identifiers);

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @param  mixed   $data
     * @param  string  $id
     * @param  array   $tags
     * @param  boolean $lifetime
     * @return boolean
     */
    protected function doSave($data, $id, array $tags = array(), $lifetime = false)
    {
        $this->validateIdentifier($id);

        if (!empty($tags)) {
            $this->saveTagsForId($id, $tags);
        }

        return xcache_set(
            $id,
            array($data, time(), $lifetime),
            (integer) $lifetime
        );
    }

    /**
     * {@inheritdoc}
     *
     * @param  string  $id
     * @return boolean
     */
    protected function doRemove($id)
    {
        return xcache_unset($id);
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
        $tmp = xcache_get($id);

        if (is_array($tmp)) {
            list($data, $mtime, $lifetime) = $tmp;

            // Calculate new lifetime
            $newLT = $lifetime - (time() - $mtime) + $extraLifetime;

            if ($newLT <= 0) {
                return false;
            }

            $data = array($data, time(), $newLT);

            $result = xcache_set($id, $data, $this->flag, $newLT);

            return $result;
        }

        return false;
    }

}
