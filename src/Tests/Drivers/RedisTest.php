<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace Endeveit\Cache\Tests\Drivers;

use Endeveit\Cache\Drivers\Redis as Driver;

/**
 * Tests for \Endeveit\Cache\Drivers\Redis.
 */
class RedisTest extends Base
{

    /**
     * Reflection object used to test protected and private methods and properties.
     *
     * @var \ReflectionObject
     */
    protected static $driverReflection = null;

    /**
     * {@inheritdoc}
     */
    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        self::$driverReflection = new \ReflectionObject(self::$driver);

    }

    /**
     * @see \Endeveit\Cache\Drivers\Redis::addConnection()
     */
    public function testAddDuplicateConnection()
    {
        $method = self::$driverReflection->getMethod('addConnection');
        $method->setAccessible(true);

        $this->setExpectedException('Endeveit\Cache\Exception');

        $method->invoke(
            self::$driver,
            '127.0.0.1',
            63791,
            Driver::DEFAULT_TIMEOUT,
            Driver::DEFAULT_WEIGHT
        );
    }

    /**
     * @see \Endeveit\Cache\Drivers\Redis::getConnection()
     */
    public function testGetConnection()
    {
        $method = self::$driverReflection->getMethod('getConnection');
        $method->setAccessible(true);

        $connections = array();
        $nbKeys      = 7;

        for ($i = 0; $i < $nbKeys; $i++) {
            $connections[$i] = $method->invoke(self::$driver, 'key_' . $i);
        }

        for ($i = 0; $i < $nbKeys; $i++) {
            $this->assertEquals($connections[$i], $method->invoke(self::$driver, 'key_' . $i));
        }
    }

    /**
     * @see \Endeveit\Cache\Drivers\Redis::getRedisObject()
     */
    public function testGetRedisObject()
    {
        $method = self::$driverReflection->getMethod('getRedisObject');
        $method->setAccessible(true);

        $property = self::$driverReflection->getProperty('connectionsOptions');
        $property->setAccessible(true);

        $options = $property->getValue(self::$driver);

        $this->assertCount(2, $options);

        foreach (array_keys($options) as $key) {
            $this->assertInstanceOf('Redis', $method->invoke(self::$driver, $key));
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return \Endeveit\Cache\Interfaces\Driver
     */
    protected static function getDriver()
    {
        return new Driver(array(
            'servers'          => array(
                array('host' => '127.0.0.1', 'port' => 63791),
                array('host' => '127.0.0.1', 'port' => 63792),
            ),
            'local_cache_size' => 64,
            'prefix_id'        => 'PHPUnit_',
        ));
    }
}
