<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Igor Borodikhin <gmail@iborodikhin.net>
 * @license MIT
 */
namespace Cache\Drivers;

/**
 * Driver that stores data in XCache and uses php5-xcache extension.
 */

class XCache extends Memcache
{

    const TAG_SEPARATOR = '|';
    const TAG_NAME_FORMAT = '_tag_%s';

    /**
     * Class constructor to override parent __construct method
     */
    public function __construct()
    {

    }

    /**
     * {@inheritdoc}
     *
     * @param string $id
     * @return bool|mixed
     */
    public function load($id)
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
     * @param array $identifiers
     * @return array
     */
    public function loadMany(array $identifiers)
    {
        $result = array();

        foreach (xcache_get($identifiers, intval($this->flag)) as $identifier => $row) {
            if (is_array($row) && isset($row[0])) {
                $result[$identifier] = $row[0];
            }
        }

        $this->fillNotFoundKeys($result, $identifiers);

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @param mixed $data
     * @param string $id
     * @param array $tags
     * @param bool $lifetime
     * @return bool
     */
    public function save($data, $id, array $tags = array(), $lifetime = false)
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
     * @param string $id
     * @return bool
     */
    public function remove($id)
    {
        if ($this->identifierIsEmpty($id)) {
            return true;
        }

        return xcache_unset($id);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $id
     * @param int $extraLifetime
     * @return bool
     */
    public function touch($id, $extraLifetime)
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

    /**
     * {@inheritdoc}
     *
     * @param string $id
     * @param int $value
     * @return int
     */
    public function increment($id, $value = 1)
    {
        return xcache_inc($id, $value);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $id
     * @param int $value
     * @return int
     */
    public function decrement($id, $value = 1)
    {
        return xcache_dec($id, $value);
    }

}
