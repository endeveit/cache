<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace EndeveitTests\Cache\Serializers;

/**
 * Base class for serializers tests.
 */
abstract class Base extends \PHPUnit_Framework_TestCase
{

    /**
     * Serializer object.
     *
     * @var \Endeveit\Cache\Interfaces\Serializer
     */
    protected static $serializer = null;

    public function testNull()
    {
        $this->equals(null);
    }

    public function testBoolean()
    {
        $this->equals(true);
        $this->equals(false);
    }

    public function testInteger()
    {
        $this->equals(0);
        $this->equals(1);
        $this->equals(-1);
        $this->equals(PHP_INT_MAX);
    }

    public function testDouble()
    {
        $this->equals(456.789);
    }

    public function testString()
    {
        $this->equals('');
        $this->equals('foo/bar');
    }

    public function testArray()
    {
        $this->equals(array(1, 2, 3));
        $this->equals(array(array(1, 2, 3), array(4, 5, 6)));
        $this->equals(array('k0' => 'v0', 'k1' => 'v1', 'k2' => 'v2'));
        $this->equals(array(0 => 'v0', 'k1' => 1, 1 => 1));
    }

    public function testObject()
    {
        $o1  = new TestObject1(1, 2, 3);
        $o2  = new TestObject2(4, 5, 6);

        $this->equals($o1);
        $this->assertNotEquals($o2, self::$serializer->unserialize(self::$serializer->serialize($o2)));
    }

    protected function equals($variable)
    {
        $this->assertEquals($variable, self::$serializer->unserialize(self::$serializer->serialize($variable)));
    }

    /**
     * {@inheritdoc}
     */
    public static function setUpBeforeClass()
    {
        self::$serializer = static::getSerializer();

        if (null === self::$serializer) {
            self::markTestIncomplete('Serializer is empty');
        }
    }

    /**
     * Method that returns serializer for testing.
     *
     * @return \Endeveit\Cache\Interfaces\Serializer
     */
    protected static function getSerializer()
    {
        return null;
    }
}
