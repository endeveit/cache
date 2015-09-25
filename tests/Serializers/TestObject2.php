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
 * Simple object for tests.
 */
class TestObject2
{

    public $a = null;
    protected $b = null;
    protected $c = null;

    public function __construct($a = null, $b = null, $c = null)
    {
        $this->a = $a;
        $this->b = $b;
        $this->c = $c;
    }

    public function __sleep()
    {
        return array(
            'a',
            'b',
        );
    }

    public function __wakeup()
    {
        $this->c = 100;
    }
}
