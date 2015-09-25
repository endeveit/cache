<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace EndeveitTests\Cache\Drivers;

use Endeveit\Cache\Drivers\Memcached as Driver;

/**
 * Tests for \Endeveit\Cache\Drivers\Memcached.
 */
class MemcachedTest extends MemcacheTest
{

    /**
     * {@inheritdoc}
     *
     * @return \Endeveit\Cache\Interfaces\Driver
     */
    protected static function getDriver()
    {
        if (!class_exists('Memcached')) {
            return null;
        }

        $memcached = new \Memcached();
        $memcached->addServer('127.0.0.1', 11211);
        $memcached->flush();

        return new Driver(array('client' => $memcached, 'prefix_id' => 'PHPUnit_'));
    }
}
