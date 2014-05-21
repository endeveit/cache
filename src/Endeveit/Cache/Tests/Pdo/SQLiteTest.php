<?php
namespace Endeveit\Cache\Tests\Pdo;

use Endeveit\Cache\Drivers\Pdo\SQLite as Driver;
use Endeveit\Cache\Tests\Base;

/**
 * Tests for \Endeveit\Cache\Drivers\Pdo\Sqlite.
 */
class SQLiteTest extends Base
{

    /**
     * {@inheritdoc}
     *
     * @return \Endeveit\Cache\Interfaces\Driver
     */
    protected function getDriver()
    {
        $driver = new Driver(new \PDO('sqlite::memory:'), 'PHPUnit_');
        $driver->buildStructure();

        return $driver;
    }
}
