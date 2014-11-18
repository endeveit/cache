<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace Endeveit\Cache\Serializers;

use Endeveit\Cache\Interfaces\Serializer;

/**
 * Standard serializer that uses built-in "serialize" and "unserialize" functions.
 */
class BuiltIn implements Serializer
{

    /**
     * {@inheritdoc}
     *
     * @param  mixed  $value
     * @return string
     */
    public function serialize($value)
    {
        return serialize($value);
    }

    /**
     * {@inheritdoc}
     *
     * @param  string $str
     * @return mixed
     */
    public function unserialize($str)
    {
        return unserialize($str);
    }
}
