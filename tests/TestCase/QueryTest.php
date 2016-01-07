<?php

namespace Muffin\Webservice\Test\TestCase;

use Cake\TestSuite\TestCase;
use Muffin\Webservice\Model\Endpoint;
use Muffin\Webservice\Model\Resource;
use Muffin\Webservice\Query;
use Muffin\Webservice\Test\test_app\Webservice\StaticWebservice;

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

        $this->query = new Query(new StaticWebservice(), new Endpoint());
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

    public function testAliasField()
    {
        $this->assertEquals(['field' => 'field'], $this->query->aliasField('field'));
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

    public function testFind()
    {
        $this->query->endpoint()->primaryKey('id');
        $this->query->endpoint()->displayField('title');

        $this->assertEquals($this->query, $this->query->find('list'));

        $debugInfo = $this->query->__debugInfo();

        $this->assertInternalType('callable', $debugInfo['formatters'][0]);
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

    public function testPage()
    {
        $this->assertEquals($this->query, $this->query->page(10));

        $this->assertEquals(10, $this->query->clause('page'));
    }

    public function testPageWithLimit()
    {
        $this->assertEquals($this->query, $this->query->page(10, 20));

        $this->assertEquals(10, $this->query->clause('page'));
        $this->assertEquals(20, $this->query->clause('limit'));
    }

    public function testOffset()
    {
        $this->assertEquals($this->query, $this->query->offset(10));

        $this->assertEquals(10, $this->query->clause('offset'));
    }

    public function testOrder()
    {
        $this->assertEquals($this->query, $this->query->order([
            'field' => 'ASC'
        ]));

        $this->assertEquals([
            'field' => 'ASC'
        ], $this->query->clause('order'));
    }

    public function testDebugInfo()
    {
        $this->assertEquals([
            '(help)' => 'This is a Query object, to get the results execute or iterate it.',
            'action' => null,
            'formatters' => [],
            'offset'  => null,
            'page' => null,
            'limit' => null,
            'set' => [],
            'sort' => [],
            'extraOptions' => [],
            'conditions' => [],
            'repository' => new Endpoint(),
            'webservice' => new StaticWebservice()
        ], $this->query->__debugInfo());
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
