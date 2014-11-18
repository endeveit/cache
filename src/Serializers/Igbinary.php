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
 * Serializer that uses igbinary extension.
 * @link https://github.com/igbinary/igbinary
 */
class Igbinary implements Serializer
{

    /**
     * {@inheritdoc}
     *
     * @param  mixed  $value
     * @return string
     */
    public function serialize($value)
    {
        return igbinary_serialize($value);
    }

    /**
     * {@inheritdoc}
     *
     * @param  string $str
     * @return mixed
     */
    public function unserialize($str)
    {
        return igbinary_unserialize($str);
    }
}
