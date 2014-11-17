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

        $this->driver->save(true, 'id with spaces');
    }

    /**
     * {@inheritdoc}
     *
     * @return \Endeveit\Cache\Interfaces\Driver
     */
    protected function getDriver()
    {
        $memcache = new \Memcache();
        $memcache->addServer('127.0.0.1');

        return new Driver(array('client' => $memcache, 'prefix_id' => 'PHPUnit_'));
    }
}
