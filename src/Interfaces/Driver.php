<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace Endeveit\Cache\Interfaces;

/**
 * The interface that all driver classes must implement.
 */
interface Driver
{

    /**
     * Returns an item.
     * If $lockTimeout is provided, library will check for lock related to key $id.
     * If lock is found, library will return old data. If not then library will set lock and returns false.
     *
     * @param  string       $id
     * @param  integer|null $lockTimeout
     * @return mixed|false  Data on success, false on failure
     */
    public function load($id, $lockTimeout = null);

    /**
     * Returns many items at once.
     *
     * @param  array $identifiers
     * @return array
     */
    public function loadMany(array $identifiers);

    /**
     * Store an item.
     *
     * @param  mixed           $data
     * @param  string          $id
     * @param  array           $tags
     * @param  integer|boolean $lifetime
     * @return boolean
     */
    public function save($data, $id, array $tags = array(), $lifetime = false);

    /**
     * Remove an item.
     *
     * @param  string  $id
     * @return boolean
     */
    public function remove($id);

    /**
     * Remove an items by cache tags.
     *
     * @param  array   $tags
     * @return boolean
     */
    public function removeByTags(array $tags);

    /**
     * Increases lifetime of item.
     *
     * @param  string  $id
     * @param  integer $extraLifetime
     * @return boolean
     */
    public function touch($id, $extraLifetime);

    /**
     * Increases a value.
     * Returns item's new value on success or false on failure.
     *
     * @param  string  $id
     * @param  integer $value
     * @return integer
     */
    public function increment($id, $value = 1);

    /**
     * Test if an entry exists in the cache.
     *
     * @param  string  $id
     * @return boolean
     */
    public function contains($id);

    /**
     * Decreases a value.
     * Returns item's new value on success or false on failure.
     *
     * @param  string  $id
     * @param  integer $value
     * @return integer
     */
    public function decrement($id, $value = 1);

    /**
     * Drops all items from cache.
     *
     * @return boolean
     */
    public function flush();
}
