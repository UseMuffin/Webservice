<?php
namespace Muffin\Webservice\Test\TestCase;

use Cake\TestSuite\TestCase;
use Muffin\Webservice\Connection;

class ConnectionTest extends TestCase
{

    /**
     * @var Connection
     */
    public $connection;

    public function setUp()
    {
        parent::setUp();

        $this->connection = new Connection([
            'name' => 'test',
            'service' => 'Test',
        ]);
    }

    /**
     * @expectedException \Muffin\Webservice\Exception\MissingDriverException
     */
    public function testConstructorMissingDriver()
    {
        new Connection([
            'name' => 'test',
            'service' => 'MissingDriver',
        ]);
    }

    /**
     * @expectedException \Muffin\Webservice\Exception\MissingConnectionException
     */
    public function testConstructorNoDriver()
    {
        new Connection([
            'name' => 'test',
        ]);
    }
}
