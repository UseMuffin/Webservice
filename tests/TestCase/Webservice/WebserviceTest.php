<?php

namespace Muffin\Webservice\Test\TestCase\Webservice;

use Cake\Log\Engine\ConsoleLog;
use Cake\TestSuite\TestCase;
use Muffin\Webservice\Model\Endpoint;
use Muffin\Webservice\Query;
use Muffin\Webservice\Test\TestCase\Model\TestDriver;
use Muffin\Webservice\Webservice\Webservice;

class TestWebservice extends Webservice
{

    public function createResource($resourceClass, array $properties = [])
    {
        return $this->_createResource($resourceClass, $properties);
    }

    public function transformResults(array $results, $resourceClass)
    {
        return $this->_transformResults($results, $resourceClass);
    }
}

class WebserviceTest extends TestCase
{

    /**
     * @var \Muffin\Webservice\Webservice\Webservice
     */
    public $webservice;

    /**
     * @inheritDoc
     */
    public function setUp()
    {
        parent::setUp();

        $this->webservice = new TestWebservice([
            'driver' => new TestDriver([])
        ]);
    }

    public function testConstructor()
    {
        $testDriver = new TestDriver([]);

        $webservice = new TestWebservice([
            'driver' => $testDriver,
            'endpoint' => 'test'
        ]);

        $this->assertEquals($testDriver, $webservice->driver());
        $this->assertEquals('test', $webservice->endpoint());
    }

    public function testNestedResources()
    {
        $this->webservice->addNestedResource('/authors/:author_id/articles', [
            'author_id'
        ]);
        $this->webservice->addNestedResource('/articles/:date', [
            'date'
        ]);

        $this->assertEquals('/authors/10/articles', $this->webservice->nestedResource([
            'author_id' => 10
        ]));
        $this->assertEquals('/articles/16-10-2015', $this->webservice->nestedResource([
            'date' => '16-10-2015'
        ]));
        $this->assertFalse($this->webservice->nestedResource([
            'title' => 'hello'
        ]));
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessage No driver has been defined
     */
    public function testExecuteWithoutDriver()
    {
        $webservice = new TestWebservice();

        $query = new Query($webservice, new Endpoint());

        $webservice->execute($query);
    }

    public function testExecuteLoggingWithoutLogger()
    {
        $query = new Query($this->webservice, new Endpoint());

        $this->webservice->execute($query);
    }

    public function testExecuteLoggingWithLogger()
    {
        $this->webservice->driver()->setLogger(new ConsoleLog());

        $query = new Query($this->webservice, new Endpoint());

        $this->webservice->execute($query);
    }

    public function testExecuteLoggingWithLoggerEnabled()
    {
        $this->webservice->driver()->logQueries(true);
        $this->webservice->driver()->setLogger(new ConsoleLog());

        $query = new Query($this->webservice, new Endpoint());

        $this->webservice->execute($query);
    }

    /**
     * @expectedException \Muffin\Webservice\Exception\UnimplementedWebserviceMethodException
     * @expectedExceptionMessage Webservice Muffin\Webservice\Test\TestCase\Webservice\TestWebservice does not implement _executeCreateQuery
     */
    public function testExecuteWithoutCreate()
    {
        $query = new Query($this->webservice, new Endpoint());
        $query->create();

        $this->webservice->execute($query);
    }

    /**
     * @expectedException \Muffin\Webservice\Exception\UnimplementedWebserviceMethodException
     * @expectedExceptionMessage Webservice Muffin\Webservice\Test\TestCase\Webservice\TestWebservice does not implement _executeReadQuery
     */
    public function testExecuteWithoutRead()
    {
        $query = new Query($this->webservice, new Endpoint());
        $query->read();

        $this->webservice->execute($query);
    }

    /**
     * @expectedException \Muffin\Webservice\Exception\UnimplementedWebserviceMethodException
     * @expectedExceptionMessage Webservice Muffin\Webservice\Test\TestCase\Webservice\TestWebservice does not implement _executeUpdateQuery
     */
    public function testExecuteWithoutUpdate()
    {
        $query = new Query($this->webservice, new Endpoint());
        $query->update();

        $this->webservice->execute($query);
    }

    /**
     * @expectedException \Muffin\Webservice\Exception\UnimplementedWebserviceMethodException
     * @expectedExceptionMessage Webservice Muffin\Webservice\Test\TestCase\Webservice\TestWebservice does not implement _executeDeleteQuery
     */
    public function testExecuteWithoutDelete()
    {
        $query = new Query($this->webservice, new Endpoint());
        $query->delete();

        $this->webservice->execute($query);
    }

    public function testCreateResource()
    {
        /* @var \Muffin\Webservice\Model\Resource $resource */
        $resource = $this->webservice->createResource('\Muffin\Webservice\Model\Resource', []);

        $this->assertInstanceOf('\Muffin\Webservice\Model\Resource', $resource);
        $this->assertFalse($resource->isNew());
        $this->assertFalse($resource->dirty());
    }

    public function testTransformResults()
    {
        $resources = $this->webservice->transformResults([
            [
                'id' => 1,
                'title' => 'Hello World',
                'body' => 'Some text'
            ],
            [
                'id' => 2,
                'title' => 'New ORM',
                'body' => 'Some more text'
            ],
            [
                'id' => 3,
                'title' => 'Webservices',
                'body' => 'Even more text'
            ]
        ], '\Muffin\Webservice\Model\Resource');

        $this->assertInternalType('array', $resources);
        $this->assertInstanceOf('\Muffin\Webservice\Model\Resource', $resources[0]);
    }

    public function testDebugInfo()
    {
        $this->assertEquals([
            'driver' => $this->webservice->driver(),
            'endpoint' => $this->webservice->endpoint()
        ], $this->webservice->__debugInfo());
    }

    /**
     * @inheritDoc
     */
    public function tearDown()
    {
        parent::tearDown();

        unset($this->webservice);
    }
}
