<?php
namespace Endeveit\Cache\Tests;

use Endeveit\Cache\Drivers\Apc as Driver;

/**
 * Tests for \Endeveit\Cache\Drivers\Apc.
 */
class ApcTest extends MemcacheTest
{

    /**
     * {@inheritdoc}
     *
     * @return \Endeveit\Cache\Interfaces\Driver
     */
    protected function getDriver()
    {
        return extension_loaded('apc') ? new Driver(array('prefix_id' => 'PHPUnit_')) : null;
    }
}
