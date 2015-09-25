<?php
/**
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Nikita Vershinin <endeveit@gmail.com>
 * @license MIT
 */
namespace EndeveitTests\Cache\Drivers;

use Endeveit\Cache\Drivers\Predis as Driver;
use Predis\Client;

/**
 * Tests for \Endeveit\Cache\Drivers\Predis.
 */
class PredisTest extends Base
{

    /**
     * {@inheritdoc}
     *
     * @return \Endeveit\Cache\Interfaces\Driver
     */
    protected static function getDriver()
    {
        $client = new Client(array(
            array('host' => '127.0.0.1', 'port' => 63791),
            array('host' => '127.0.0.1', 'port' => 63792),
        ));

        return new Driver(array('client' => $client, 'prefix_id' => 'PHPUnit_'));
    }
}
