<?php

namespace Muffin\Webservice\Test\TestCase;

use Cake\TestSuite\TestCase;
use Muffin\Webservice\Model\Resource;
use Muffin\Webservice\ResultSet;

class ResultSetTest extends TestCase
{

    /**
     * @var ResultSet
     */
    public $resultSet;

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->resultSet = new ResultSet([
            new Resource([
                'id' => 1,
                'title' => 'Hello World'
            ]),
            new Resource([
                'id' => 2,
                'title' => 'New ORM'
            ]),
            new Resource([
                'id' => 3,
                'title' => 'Webservices'
            ])
        ], 6);
    }

    public function testCount()
    {
        $this->assertEquals(3, $this->resultSet->count());
    }

    public function testTotal()
    {
        $this->assertEquals(6, $this->resultSet->total());
    }

    public function testSerialize()
    {
        $this->assertIsString(serialize($this->resultSet));
    }

    public function testUnserialize()
    {
        $unserialized = unserialize(serialize($this->resultSet));

        $this->assertInstanceOf('\Muffin\Webservice\ResultSet', $unserialized);
    }

    /**
     * @inheritDoc
     */
    public function tearDown(): void
    {
        parent::tearDown();

        $this->resultSet = null;
    }
}
