<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace EndeveitTests\Cache\Serializers;

use Endeveit\Cache\Serializers\BuiltIn;

/**
 * Tests for built-in serializer.
 */
class BuiltInTest extends Base
{

    /**
     * {@inheritdoc}
     *
     * @return \Endeveit\Cache\Interfaces\Serializer
     */
    protected static function getSerializer()
    {
        return new BuiltIn();
    }
}
