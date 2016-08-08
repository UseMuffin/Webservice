<?php

namespace Muffin\Webservice\Test\TestCase;

use ArrayIterator;
use Cake\TestSuite\TestCase;
use Muffin\Webservice\Model\Endpoint;
use Muffin\Webservice\Model\Resource;
use Muffin\Webservice\Query;
use Muffin\Webservice\ResultSet;
use Muffin\Webservice\Test\test_app\Webservice\StaticWebservice;

class ResultSetTest extends TestCase
{
    /**
     * @var ResultSet
     */
    public $resultSet;

    /**
     * @inheritDoc
     */
    public function setUp()
    {
        parent::setUp();

        $webservice = new StaticWebservice();
        $query = new Query($webservice, new Endpoint([
            'webservice' => $webservice,
            'alias' => 'Test'
        ]));

        $this->resultSet = new ResultSet($query, new ArrayIterator([
            [
                $query->endpoint()->alias() . '__id' => 1,
                $query->endpoint()->alias() . '__title' => 'Hello World'
            ],
            [
                $query->endpoint()->alias() . '__id' => 2,
                $query->endpoint()->alias() . '__title' => 'New ORM'
            ],
            [
                $query->endpoint()->alias() . '__id' => 3,
                $query->endpoint()->alias() . '__title' => 'Webservices'
            ]
        ]), 6);
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
        $this->assertInternalType('string', serialize($this->resultSet));
    }

    public function testUnserialize()
    {
        $unserialized = unserialize(serialize($this->resultSet));

        $this->assertInstanceOf('\Muffin\Webservice\ResultSet', $unserialized);
    }

    /**
     * @inheritDoc
     */
    public function tearDown()
    {
        parent::tearDown();

        $this->resultSet = null;
    }
}
