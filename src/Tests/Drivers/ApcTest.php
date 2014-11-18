<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace Endeveit\Cache\Tests\Drivers;

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
    protected static function getDriver()
    {
        return extension_loaded('apc') ? new Driver(array('prefix_id' => 'PHPUnit_')) : null;
    }
}
