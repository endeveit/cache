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
    protected static function getDriver()
    {
        return new Driver(array(
            'servers'          => array(
                array('host' => '127.0.0.1'),
            ),
            'local_cache_size' => 64,
            'prefix_id'        => 'PHPUnit_',
        ));
    }
}
