<?php
namespace Endeveit\Cache\Tests;

use Endeveit\Cache\Drivers\Memcached as Driver;

/**
 * Tests for \Endeveit\Cache\Drivers\Memcached.
 */
class MemcachedTest extends Base
{

    /**
     * {@inheritdoc}
     *
     * @return \Endeveit\Cache\Interfaces\Driver
     */
    protected function getDriver()
    {
        $memcached = new \Memcached();
        $memcached->addServer('127.0.0.1', 11211);

        return new Driver($memcached, false, 'PHPUnit_');
    }
}
