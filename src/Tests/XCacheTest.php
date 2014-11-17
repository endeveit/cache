<?php
namespace Endeveit\Cache\Tests;

use Endeveit\Cache\Drivers\XCache as Driver;

/**
 * Tests for \Endeveit\Cache\Drivers\XCache.
 */
class XCacheTest extends MemcacheTest
{

    /**
     * {@inheritdoc}
     *
     * @return \Endeveit\Cache\Interfaces\Driver
     */
    protected function getDriver()
    {
        return extension_loaded('xcache') ? new Driver(array('prefix_id' => 'PHPUnit_')) : null;
    }
}
