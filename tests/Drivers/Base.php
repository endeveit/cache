<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace EndeveitTests\Cache\Drivers;

/**
 * Base class for drivers tests.
 */
abstract class Base extends \PHPUnit_Framework_TestCase
{
    /**
     * Driver object.
     *
     * @var \Endeveit\Cache\Interfaces\Driver
     */
    protected static $driver = null;

    /**
     * Entries lifetime.
     *
     * @var integer
     */
    protected static $lifetime = 2;

    /**
     * Array with random generated identifiers.
     *
     * @var array
     */
    protected static $cacheIdentifiers = array();

    /**
     * Array with cache tags for generated identifiers.
     *
     * @var array
     */
    protected static $cacheIdentifiersTags = array();

    /**
     * @see \Endeveit\Cache\Interfaces\Driver::load()
     */
    public function testLoad()
    {
        $identifier = self::getRandomIdentifier();

        $this->assertTrue(self::saveInCache($identifier));
        $this->assertEquals(self::getDataForIdentifier($identifier), self::$driver->load($identifier));
    }

    /**
     * @see \Endeveit\Cache\Interfaces\Driver::load()
     */
    public function testExpiredNoLock()
    {
        $identifier = self::getRandomIdentifier();

        $this->assertTrue(self::saveInCache($identifier));

        sleep(self::$lifetime + 1);

        $this->assertFalse(self::$driver->load($identifier));
    }

    /**
     * @see \Endeveit\Cache\Interfaces\Driver::load()
     */
    public function testExpiredWithLock()
    {
        $identifier = self::getRandomIdentifier();

        $this->assertTrue(self::saveInCache($identifier));

        sleep(self::$lifetime + 1);

        $this->assertFalse(self::$driver->load($identifier, 10));
        $this->assertEquals(self::getDataForIdentifier($identifier), self::$driver->load($identifier, 10));
    }

    /**
     * @see \Endeveit\Cache\Interfaces\Driver::loadMany()
     */
    public function testLoadMany()
    {
        $identifiers = self::getRandomIdentifiers();

        foreach ($identifiers as $identifier) {
            self::saveInCache($identifier);
        }

        $this->assertFalse(array_search(false, self::$driver->loadMany($identifiers)));
    }

    /**
     * @see \Endeveit\Cache\Interfaces\Driver::loadMany()
     */
    public function testLoadManyExpired()
    {
        $identifiers = self::getRandomIdentifiers();

        foreach ($identifiers as $identifier) {
            self::saveInCache($identifier);
        }

        sleep(self::$lifetime + 1);

        $this->assertNotFalse(array_search(false, self::$driver->loadMany($identifiers), true));
    }

    /**
     * @see \Endeveit\Cache\Interfaces\Driver::loadMany()
     */
    public function testLoadManyWithEmpty()
    {
        $identifiers = self::getRandomIdentifiers();

        foreach ($identifiers as $identifier) {
            self::saveInCache($identifier);
        }

        $nbFalseKeys = 0;

        foreach (range(1, rand(2, 4)) as $i) {
            $identifiers[] = 'not_existed_id_' . $i;
            ++$nbFalseKeys;
        }

        $falseKeys = array_keys(self::$driver->loadMany($identifiers), false);

        $this->assertNotEmpty($falseKeys);
        $this->assertEquals($nbFalseKeys, count($falseKeys));
    }

    /**
     * @see \Endeveit\Cache\Interfaces\Driver::remove()
     */
    public function testRemove()
    {
        $identifier = self::getRandomIdentifier();

        $this->assertTrue(self::saveInCache($identifier));
        $this->assertTrue(self::$driver->remove($identifier));
    }

    /**
     * @see \Endeveit\Cache\Interfaces\Driver::removeByTags()
     */
    public function testRemoveByTags()
    {
        $identifier = self::getRandomIdentifier();

        $this->assertTrue(self::saveInCache($identifier));
        $this->assertTrue(self::$driver->removeByTags(array(self::$cacheIdentifiersTags[$identifier])));
        $this->assertFalse(self::$driver->load($identifier));
    }

    /**
     * @see \Endeveit\Cache\Interfaces\Driver::touch()
     */
    public function testTouch()
    {
        $now        = time();
        $touchTime  = 300;
        $identifier = self::getRandomIdentifier();

        $this->assertTrue(self::saveInCache($identifier));
        $this->assertTrue(self::$driver->touch($identifier, $touchTime));

        $obj     = new \ReflectionObject(self::$driver);
        $method0 = $obj->getMethod('doLoadRaw');
        $method1 = $obj->getMethod('getPrefixedIdentifier');
        $method2 = $obj->getMethod('getSerializer');
        $method0->setAccessible(true);
        $method1->setAccessible(true);
        $method2->setAccessible(true);

        $cacheData = $method0->invoke(self::$driver, $method1->invoke(self::$driver, $identifier));

        if (is_string($cacheData)) {
            $cacheData = $method2->invoke(self::$driver)->unserialize($cacheData);
        }

        // Assume delay can be in range 0 < $delay < 5
        $range = $cacheData['expiresAt'] - $touchTime - $now;
        $this->assertInternalType('array', $cacheData);
        $this->assertArrayHasKey('expiresAt', $cacheData);
        $this->assertGreaterThanOrEqual(0, $range);
        $this->assertLessThanOrEqual(5, $range);
    }

    /**
     * @see \Endeveit\Cache\Interfaces\Driver::increment()
     */
    public function testIncrement()
    {
        $identifier = 'not_existed_inc';

        $this->assertEquals(1, self::$driver->increment($identifier));
        $this->assertEquals(3, self::$driver->increment($identifier, 2));
        $this->assertEquals(3, self::$driver->load($identifier));
    }

    /**
     * @see \Endeveit\Cache\Interfaces\Driver::decrement()
     */
    public function testDecrement()
    {
        $identifier = 'not_existed_dec';

        $this->assertEquals(-10, self::$driver->decrement($identifier, 10));
        $this->assertEquals(5, self::$driver->increment($identifier, 15));
        $this->assertEquals(2, self::$driver->decrement($identifier, 3));
        $this->assertEquals(2, self::$driver->load($identifier));
    }

    /**
     * @see \Endeveit\Cache\Interfaces\Driver::contains()
     */
    public function testContains()
    {
        $identifier = self::getRandomIdentifier();

        $this->assertTrue(self::saveInCache($identifier));
        $this->assertTrue(self::$driver->contains($identifier));
    }

    /**
     * @see \Endeveit\Cache\Interfaces\Driver::flush()
     */
    public function testFlush()
    {
        $this->assertTrue(self::$driver->flush());
    }

    /**
     * {@inheritdoc}
     */
    public static function setUpBeforeClass()
    {
        self::$driver = static::getDriver();

        if (null === self::$driver) {
            self::markTestIncomplete('Cache driver is empty');
        } else {
            // Run tests with igbinary serialization if it available
            if (extension_loaded('igbinary')) {
                $obj      = new \ReflectionObject(self::$driver);
                $property = $obj->getProperty('options');
                $property->setAccessible(true);

                $options = $property->getValue(self::$driver);
                $property->setValue(self::$driver, array_merge($options, array('serializer' => 'Igbinary')));
            }

            self::generateIdentifiers();
        }
    }

    /**
     * Returns data for provided identifier.
     *
     * @param  string $identifier
     * @return string
     */
    protected static function getDataForIdentifier($identifier)
    {
        return 'data_' . $identifier;
    }

    /**
     * Returns random cache identifier.
     *
     * @return string
     */
    protected static function getRandomIdentifier()
    {
        return self::$cacheIdentifiers[array_rand(self::$cacheIdentifiers)];
    }

    /**
     * Returns array with random identifiers.
     *
     * @return array
     */
    protected static function getRandomIdentifiers()
    {
        $identifiers   = array();
        $nbIdentifiers = rand(1, floor(count(self::$cacheIdentifiers) / 2));

        while ($nbIdentifiers > 0) {
            $identifier = self::getRandomIdentifier();

            if (!in_array($identifier, $identifiers)) {
                $identifiers[] = $identifier;
                --$nbIdentifiers;
            }
        }

        return $identifiers;
    }

    /**
     * Saves entry in cache.
     *
     * @param  string  $identifier
     * @return boolean
     */
    protected static function saveInCache($identifier)
    {
        return self::$driver->save(
            self::getDataForIdentifier($identifier),
            $identifier,
            array(self::$cacheIdentifiersTags[$identifier]),
            self::$lifetime
        );
    }

    /**
     * Generates list of random cache identifiers.
     */
    protected static function generateIdentifiers()
    {
        for ($i = 0; $i < rand(10, 20); $i++) {
            $id              = sprintf('%04d', rand(1, 9999));
            $cacheIdentifier = 'cache_' . $id;

            self::$cacheIdentifiers[]                     = $cacheIdentifier;
            self::$cacheIdentifiersTags[$cacheIdentifier] = 'tag_' . $id;
        }
    }

    /**
     * Method that returns driver for testing.
     *
     * @return \Endeveit\Cache\Interfaces\Driver
     */
    protected static function getDriver()
    {
        return null;
    }
}
