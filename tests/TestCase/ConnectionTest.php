<?php
declare(strict_types=1);

namespace Muffin\Webservice\Test\TestCase;

use Cake\TestSuite\TestCase;
use Muffin\Webservice\Datasource\Connection;
use Muffin\Webservice\Datasource\Exception\MissingConnectionException;
use Muffin\Webservice\Webservice\Exception\MissingDriverException;

class ConnectionTest extends TestCase
{
    /**
     * @var Connection|null
     */
    public ?Connection $connection;

    public function setUp(): void
    {
        parent::setUp();

        $this->connection = new Connection([
            'name' => 'test',
            'service' => 'Test',
        ]);
    }

    public function testConstructorMissingDriver()
    {
        $this->expectException(MissingDriverException::class);

        new Connection([
            'name' => 'test',
            'service' => 'Missing',
        ]);
    }

    public function testConstructorNoDriver()
    {
        $this->expectException(MissingConnectionException::class);

        new Connection([
            'name' => 'test',
        ]);
    }

    public function testConfigName()
    {
        $this->assertEquals('test', $this->connection->configName());
    }
}
