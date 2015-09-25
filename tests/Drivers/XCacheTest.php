<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace EndeveitTests\Cache\Drivers;

use Endeveit\Cache\Drivers\XCache as Driver;

/**
 * Tests for \Endeveit\Cache\Drivers\XCache.
 */
class XCacheTest extends MemcacheTest
{

    /**
     * {@inheritdoc}
     *
     * @return \Endeveit\Cache\Interfaces\Driver
     */
    protected static function getDriver()
    {
        return extension_loaded('xcache') ? new Driver(array('prefix_id' => 'PHPUnit_')) : null;
    }
}
