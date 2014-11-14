<?php
namespace Endeveit\Cache\Tests;

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
    protected function getDriver()
    {
        $client = new Client(array(
            'host' => '127.0.0.1'
        ));

        return new Driver(array('client' => $client, 'prefix_id' => 'PHPUnit_'));
    }
}
