<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace Cache\Interfaces;

/**
 * The interface that all driver classes must implement.
 */
interface Driver
{

    /**
     * Returns an item.
     *
     * @abstract
     * @param string $id
     * @return mixed|false Data on success, false on failure
     */
    public function load($id);

    /**
     * Store an item.
     *
     * @abstract
     * @param mixed $data
     * @param string $id
     * @param array $tags
     * @param integer|boolean $lifetime
     * @return boolean
     */
    public function save($data, $id, array $tags = array(), $lifetime = false);

    /**
     * Remove an item.
     *
     * @abstract
     * @param string $id
     * @return boolean
     */
    public function remove($id);

    /**
     * Remove an items by cache tags.
     *
     * @abstract
     * @param array $tags
     * @return boolean
     */
    public function removeByTags(array $tags);

    /**
     * Increases lifetime of item.
     *
     * @abstract
     * @param string $id
     * @param integer $extraLifetime
     * @return boolean
     */
    public function touch($id, $extraLifetime);

}