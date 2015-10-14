<?php

namespace Muffin\Webservice\Test\TestCase;

use Cake\TestSuite\TestCase;
use Muffin\Webservice\Model\Endpoint;
use Muffin\Webservice\Model\Resource;
use Muffin\Webservice\Query;
use Muffin\Webservice\ResultSet;
use Muffin\Webservice\Webservice\WebserviceInterface;

class TestWebservice implements WebserviceInterface
{

    public function execute(Query $query, array $options = [])
    {
        return new ResultSet([
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
        ], 3);
    }
}

class QueryTest extends TestCase
{

    /**
     * @var Query
     */
    public $query;

    /**
     * @inheritDoc
     */
    public function setUp()
    {
        parent::setUp();

        $this->query = new Query(new TestWebservice(), new Endpoint());
    }

    public function testAction()
    {
        $this->assertNull($this->query->action());

        $this->assertEquals($this->query, $this->query->action(Query::ACTION_READ));
        $this->assertEquals(Query::ACTION_READ, $this->query->action());
    }

    public function testActionMethods()
    {
        $this->assertEquals($this->query, $this->query->create());
        $this->assertEquals(Query::ACTION_CREATE, $this->query->action());

        $this->assertEquals($this->query, $this->query->read());
        $this->assertEquals(Query::ACTION_READ, $this->query->action());

        $this->assertEquals($this->query, $this->query->update());
        $this->assertEquals(Query::ACTION_UPDATE, $this->query->action());

        $this->assertEquals($this->query, $this->query->delete());
        $this->assertEquals(Query::ACTION_DELETE, $this->query->action());
    }

    public function testCountNonReadAction()
    {
        $this->assertEquals(false, $this->query->count());
    }

    public function testCount()
    {
        $this->query->read();

        $this->assertEquals(3, $this->query->count());
    }

    public function testFirst()
    {
        $this->assertEquals(new Resource([
            'id' => 1,
            'title' => 'Hello World'
        ]), $this->query->first());
    }

    public function testApplyOptions()
    {
        $this->assertEquals($this->query, $this->query->applyOptions([
            'page' => 1,
            'limit' => 2,
            'order' => [
                'field' => 'ASC'
            ],
            'customOption' => 'value'
        ]));
        $this->assertEquals(1, $this->query->page());
        $this->assertEquals(2, $this->query->limit());
        $this->assertEquals([
            'field' => 'ASC'
        ], $this->query->clause('order'));
        $this->assertEquals([
            'customOption' => 'value'
        ], $this->query->getOptions());
    }

    /**
     * @expectedException \UnexpectedValueException
     */
    public function testSetInvalidAction()
    {
        $this->query->read();

        $this->query->set([]);
    }

    public function testSet()
    {
        $this->query->update();

        $this->assertEquals($this->query, $this->query->set([
            'field' => 'value'
        ]));
        $this->assertEquals([
            'field' => 'value'
        ], $this->query->set());
    }

    /**
     * @inheritDoc
     */
    public function tearDown()
    {
        parent::tearDown();

        $this->query = null;
    }
}
