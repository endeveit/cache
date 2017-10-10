<?php
namespace EndeveitTests\Cache\Drivers;
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Igor Borodikhin <iborodikhin@gmail.com>
 * @license MIT
 */
use Endeveit\Cache\Drivers\Localcache as Driver;

/**
 * Tests for \Endeveit\Cache\Drivers\Localcache.
 */
class LocalcacheTest extends Base
{

    /**
     * {@inheritdoc}
     *
     * @return \Endeveit\Cache\Interfaces\Driver
     */
    protected static function getDriver()
    {
        return new Driver(array('prefix_id' => 'PHPUnit_'));
    }
}
