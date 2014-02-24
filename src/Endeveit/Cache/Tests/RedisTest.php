<?php
namespace Endeveit\Cache\Tests;

use Endeveit\Cache\Drivers\Redis as Driver;

/**
 * Tests for \Endeveit\Cache\Drivers\Redis.
 */
class RedisTest extends Base
{

    /**
     * {@inheritdoc}
     *
     * @return \Endeveit\Cache\Interfaces\Driver
     */
    protected function getDriver()
    {
        $driver = new Driver(64, 'PHPUnit_');
        $driver->addConnection('127.0.0.1');

        return $driver;
    }
}
