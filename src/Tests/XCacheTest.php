<?php
namespace Endeveit\Cache\Tests;

use Endeveit\Cache\Drivers\XCache as Driver;

/**
 * Tests for \Endeveit\Cache\Drivers\XCache.
 */
class XCacheTest extends Base
{

    /**
     * {@inheritdoc}
     *
     * @return \Endeveit\Cache\Interfaces\Driver
     */
    protected function getDriver()
    {
        return new Driver(array('prefix_id' => 'PHPUnit_'));
    }
}
