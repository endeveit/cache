<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace Cache\Abstracts;

use Cache\Interfaces\Driver;

/**
 * Base class for drivers that use max lifetime limit.
 */
abstract class Common implements Driver
{

    /**
     * The max lifetime of the data in cache (31 days).
     *
     * @const integer
     */
    const MAX_LIFETIME = 2678400;

    /**
     * {@inheritdoc}
     *
     * @param  string      $id
     * @return mixed|false Data on success, false on failure
     */
    public function load($id)
    {
        return $this->doLoad($id);
    }

    /**
     * {@inheritdoc}
     *
     * @param  array $identifiers
     * @return array
     */
    public function loadMany(array $identifiers)
    {
        return $this->doLoadMany($identifiers);
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
        return $this->doSave($data, $id, $tags, $lifetime);
    }

    /**
     * {@inheritdoc}
     *
     * @param  string  $id
     * @return boolean
     */
    public function remove($id)
    {
        if (empty($id) || 0 == strlen(trim($id))) {
            return true;
        }

        return $this->doRemove($id);
    }

    /**
     * {@inheritdoc}
     *
     * @param  array   $tags
     * @return boolean
     */
    public function removeByTags(array $tags)
    {
        return $this->doRemoveByTags($tags);
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
        return $this->doTouch($id, $extraLifetime);
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
        $oldValue = $this->getValueForIncrementOrDecrement($id);
        $newValue = $oldValue + intval($value);
        $this->save($newValue, $id);

        return $newValue;
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
        $oldValue = $this->getValueForIncrementOrDecrement($id);
        $newValue = $oldValue - intval($value);
        $this->save($newValue, $id);

        return $newValue;
    }

    /**
     * {@inheritdoc}
     *
     * @param  string  $id
     * @return boolean
     */
    public function contains($id)
    {
        return (false !== $this->load($id)) ? true : false;
    }

    /**
     * {@inheritdoc}
     *
     * @return boolean
     */
    public function flush()
    {
        return $this->doFlush();
    }

    /**
     * Returns final lifetime not greater than self::MAX_LIFETIME.
     *
     * @param  boolean|integer $lifetime
     * @return integer
     */
    protected function getFinalLifetime($lifetime = false)
    {
        if (false !== $lifetime) {
            $lifetime = (integer) $lifetime;
            $lifetime = ($lifetime > self::MAX_LIFETIME
                ? self::MAX_LIFETIME
                : $lifetime);
        } else {
            $lifetime = 0;
        }

        return $lifetime;
    }

    /**
     * Returns a value for incrementing or decrementing key.
     *
     * @param  string  $id
     * @return integer
     */
    protected function getValueForIncrementOrDecrement($id)
    {
        $value = $this->load($id);

        if (!$value || !is_integer($value)) {
            $value = 0;
        }

        return $value;
    }

    /**
     * Fills the not found keys with false in «loadMany» method.
     *
     * @param array $result
     * @param array $identifiers
     */
    protected function fillNotFoundKeys(array &$result, array &$identifiers)
    {
        foreach (array_diff($identifiers, array_keys($result)) as $notExist) {
            $result[$notExist] = false;
        }
    }

    /**
     * Returns an item through selected driver.
     *
     * @param  string      $id
     * @return mixed|false
     */
    abstract protected function doLoad($id);

    /**
     * Returns many items at once through selected driver.
     *
     * @param  array $identifiers
     * @return array
     */
    abstract protected function doLoadMany(array $identifiers);

    /**
     * Store an item through selected driver.
     *
     * @param  mixed           $data
     * @param  string          $id
     * @param  array           $tags
     * @param  integer|boolean $lifetime
     * @return boolean
     */
    abstract protected function doSave($data, $id, array $tags = array(), $lifetime = false);

    /**
     * Remove an item through selected driver.
     *
     * @param  string  $id
     * @return boolean
     */
    abstract protected function doRemove($id);

    /**
     * Remove an items by cache tags through selected driver.
     *
     * @param  array   $tags
     * @return boolean
     */
    abstract protected function doRemoveByTags(array $tags);

    /**
     * Increases lifetime of item through selected driver.
     *
     * @param  string  $id
     * @param  integer $extraLifetime
     * @return boolean
     */
    abstract protected function doTouch($id, $extraLifetime);

    /**
     * Drops all items from cache through selected driver.
     *
     * @return boolean
     */
    abstract protected function doFlush();

}
