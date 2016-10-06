<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Igor Borodikhin <iborodikhin@gmail.com>
 * @license MIT
 */
namespace Endeveit\Cache\Serializers;

use Endeveit\Cache\Interfaces\Serializer;
/**
 * Serializer that compresses data with gzip.
 */
class Gzip implements Serializer
{

    /**
     * {@inheritdoc}
     *
     * @param  mixed  $value
     * @return string
     */
    public function serialize($value)
    {
        return gzdeflate(serialize($value));
    }

    /**
     * {@inheritdoc}
     *
     * @param  string $str
     * @return mixed
     */
    public function unserialize($str)
    {
        return unserialize(gzinflate($str));
    }
}
