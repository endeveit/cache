<?php
namespace Endeveit\Cache\Tests;

use Endeveit\Cache\Drivers\Memcache as Driver;

/**
 * Tests for \Endeveit\Cache\Drivers\Memcache.
 */
class MemcacheTest extends Base
{

    /**
     * {@inheritdoc}
     *
     * @return \Endeveit\Cache\Interfaces\Driver
     */
    protected function getDriver()
    {
        $memcache = new \Memcache();
        $memcache->addServer('127.0.0.1');

        return new Driver($memcache, false, 'PHPUnit_');
    }
}
