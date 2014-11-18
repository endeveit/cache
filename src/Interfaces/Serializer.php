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
 * The interface for serializers.
 */
interface Serializer
{

    /**
     * Returns the serialized value.
     *
     * @param  mixed  $value
     * @return string
     */
    public function serialize($value);

    /**
     * Unserializes the string and returns value.
     *
     * @param  string $str
     * @return mixed
     */
    public function unserialize($str);
}
