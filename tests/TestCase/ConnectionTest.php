<?php
namespace Muffin\Webservice\Test\TestCase;

use Cake\TestSuite\TestCase;
use Muffin\Webservice\Connection;
use Muffin\Webservice\Exception\MissingConnectionException;
use Muffin\Webservice\Exception\MissingDriverException;

class ConnectionTest extends TestCase
{

    /**
     * @var Connection
     */
    public $connection;

    public function setUp(): void
    {
        parent::setUp();

        $this->connection = new Connection([
            'name' => 'test',
            'service' => 'Test'
        ]);
    }

    public function testConstructorMissingDriver()
    {
        $this->expectException(MissingDriverException::class);

        new Connection([
            'name' => 'test',
            'service' => 'MissingDriver'
        ]);
    }

    public function testConstructorNoDriver()
    {
        $this->expectException(MissingConnectionException::class);

        new Connection([
            'name' => 'test',
        ]);
    }
}
