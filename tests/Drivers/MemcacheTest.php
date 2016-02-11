<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace EndeveitTests\Cache\Drivers;

use Endeveit\Cache\Drivers\Memcache as Driver;

/**
 * Tests for \Endeveit\Cache\Drivers\Memcache.
 */
class MemcacheTest extends Base
{

    /**
     * @see \Endeveit\Cache\Drivers\Memcache::validateIdentifier()
     */
    public function testValidateIdentifier()
    {
        $this->setExpectedException('Endeveit\Cache\Exception');

        self::$driver->save(true, 'id with spaces');
    }

    /**
     * {@inheritdoc}
     *
     * @return \Endeveit\Cache\Interfaces\Driver
     */
    protected static function getDriver()
    {
        if (!class_exists('Memcache')) {
            return null;
        }

        $memcache = new \Memcache();
        $memcache->addServer('127.0.0.1');
        $memcache->flush();

        return new Driver(array('client' => $memcache, 'prefix_id' => 'PHPUnit_'));
    }
}
