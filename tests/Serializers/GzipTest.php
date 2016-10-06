<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Nikita Vershinin <iborodikhin@gmail.com>
 * @license MIT
 */
namespace EndeveitTests\Cache\Serializers;

use Endeveit\Cache\Serializers\Gzip;

/**
 * Tests for gzip serializer.
 */
class GzipTest extends Base
{

    /**
     * {@inheritdoc}
     *
     * @return \Endeveit\Cache\Interfaces\Serializer
     */
    protected static function getSerializer()
    {
        return new Gzip();
    }
}
