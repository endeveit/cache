<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace Endeveit\Cache\Tests\Serializers;

use Endeveit\Cache\Serializers\Igbinary;

/**
 * Tests for igbinary serializer.
 */
class IgbinaryTest extends Base
{

    /**
     * {@inheritdoc}
     *
     * @return \Endeveit\Cache\Interfaces\Serializer
     */
    protected static function getSerializer()
    {
        return extension_loaded('igbinary') ? new Igbinary() : null;
    }
}
