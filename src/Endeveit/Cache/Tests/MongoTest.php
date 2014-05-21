<?php
namespace Endeveit\Cache\Tests;

use Endeveit\Cache\Drivers\Mongo as Driver;

/**
 * Tests for \Endeveit\Cache\Drivers\Mongo.
 */
class MongoTest extends Base
{

    /**
     * {@inheritdoc}
     *
     * @return \Endeveit\Cache\Interfaces\Driver
     */
    protected function getDriver()
    {
        $driver = new Driver(new \MongoClient(), 'cache', 'PHPUnit_');
        $driver->ensureIndexes();

        return $driver;
    }
}
