<?php
namespace Endeveit\Cache\Tests;

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
        $memcache = new \Memcache();
        $memcache->addServer('127.0.0.1');
        $memcache->flush();

        return new Driver(array('client' => $memcache, 'prefix_id' => 'PHPUnit_'));
    }
}
