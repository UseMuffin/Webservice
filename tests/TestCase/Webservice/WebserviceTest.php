<?php
declare(strict_types=1);

namespace Muffin\Webservice\Test\TestCase\Webservice;

use Cake\TestSuite\TestCase;
use Muffin\Webservice\Datasource\Query;
use Muffin\Webservice\Model\Endpoint;
use Muffin\Webservice\Model\Exception\MissingEndpointSchemaException;
use Muffin\Webservice\Webservice\Exception\UnimplementedWebserviceMethodException;
use Muffin\Webservice\Webservice\Webservice;
use TestApp\Webservice\Driver\TestDriver;
use TestApp\Webservice\TestWebservice;

class WebserviceTest extends TestCase
{
    /**
     * @var \Muffin\Webservice\Webservice\Webservice|null
     */
    public ?Webservice $webservice;

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->webservice = new TestWebservice([
            'driver' => new TestDriver([]),
        ]);
    }

    /**
     * @inheritDoc
     */
    public function tearDown(): void
    {
        parent::tearDown();

        unset($this->webservice);
    }

    public function testConstructor()
    {
        $testDriver = new TestDriver([]);

        $webservice = new TestWebservice([
            'driver' => $testDriver,
            'endpoint' => 'test',
        ]);

        $this->assertEquals($testDriver, $webservice->getDriver());
        $this->assertEquals('test', $webservice->getEndpoint());
    }

    public function testNestedResources()
    {
        $this->webservice->addNestedResource('/authors/:author_id/articles', [
            'author_id',
        ]);
        $this->webservice->addNestedResource('/articles/:date', [
            'date',
        ]);

        $this->assertEquals('/authors/10/articles', $this->webservice->nestedResource([
            'author_id' => 10,
        ]));
        $this->assertEquals('/articles/16-10-2015', $this->webservice->nestedResource([
            'date' => '16-10-2015',
        ]));
        $this->assertNull($this->webservice->nestedResource([
            'title' => 'hello',
        ]));
    }

    public function testExecuteLoggingWithLogger()
    {
        $logger = $this->getMockBuilder('Cake\Log\Engine\ConsoleLog')
            ->onlyMethods([
                'debug',
            ])
            ->getMock();
        $logger
            ->expects($this->never())
            ->method('debug');

        $this->webservice->getDriver()->setLogger($logger);

        $query = new Query($this->webservice, new Endpoint());

        $this->webservice->execute($query);
    }

    public function testExecuteLoggingWithLoggerEnabled()
    {
        $logger = $this->getMockBuilder('Cake\Log\Engine\ConsoleLog')
            ->onlyMethods([
                'debug',
            ])
            ->getMock();
        $logger
            ->expects($this->once())
            ->method('debug');

        $this->webservice->getDriver()->enableQueryLogging();
        $this->webservice->getDriver()->setLogger($logger);

        $query = new Query($this->webservice, new Endpoint());

        $this->webservice->execute($query);
    }

    public function testExecuteWithoutCreate()
    {
        $this->expectException(UnimplementedWebserviceMethodException::class);
        $this->expectExceptionMessage('Webservice TestApp\Webservice\TestWebservice does not implement _executeCreateQuery');

        $query = new Query($this->webservice, new Endpoint());
        $query->create();

        $this->webservice->execute($query);
    }

    public function testExecuteWithoutRead()
    {
        $this->expectException(UnimplementedWebserviceMethodException::class);
        $this->expectExceptionMessage('Webservice TestApp\Webservice\TestWebservice does not implement _executeReadQuery');

        $query = new Query($this->webservice, new Endpoint());
        $query->read();

        $this->webservice->execute($query);
    }

    public function testExecuteWithoutUpdate()
    {
        $this->expectException(UnimplementedWebserviceMethodException::class);
        $this->expectExceptionMessage('Webservice TestApp\Webservice\TestWebservice does not implement _executeUpdateQuery');

        $query = new Query($this->webservice, new Endpoint());
        $query->update();

        $this->webservice->execute($query);
    }

    public function testExecuteWithoutDelete()
    {
        $this->expectException(UnimplementedWebserviceMethodException::class);
        $this->expectExceptionMessage('Webservice TestApp\Webservice\TestWebservice does not implement _executeDeleteQuery');

        $query = new Query($this->webservice, new Endpoint());
        $query->delete();

        $this->webservice->execute($query);
    }

    public function testCreateResource()
    {
        /** @var \Muffin\Webservice\Model\Resource $resource */
        $resource = $this->webservice->createResource('\Muffin\Webservice\Model\Resource', []);

        $this->assertFalse($resource->isNew());
        $this->assertFalse($resource->isDirty());
    }

    public function testTransformResults()
    {
        $resources = $this->webservice->transformResults(new Endpoint(), [
            [
                'id' => 1,
                'title' => 'Hello World',
                'body' => 'Some text',
            ],
            [
                'id' => 2,
                'title' => 'New ORM',
                'body' => 'Some more text',
            ],
            [
                'id' => 3,
                'title' => 'Webservices',
                'body' => 'Even more text',
            ],
        ]);

        $this->assertIsArray($resources);
        $this->assertInstanceOf('\Muffin\Webservice\Model\Resource', $resources[0]);
    }

    public function testDescribe()
    {
        $service = new TestWebservice();

        $result = $service->describe('test');
        $this->assertInstanceOf('\Muffin\Webservice\Model\Schema', $result);
        $this->assertEquals('Test', $result->name());
    }

    public function testDebugInfo()
    {
        $expected = [
            'driver' => $this->webservice->getDriver(),
            'endpoint' => null,
        ];

        $this->assertEquals($expected, $this->webservice->__debugInfo());
    }

    public function testDescribeException()
    {
        $this->expectException(MissingEndpointSchemaException::class);

        $this->webservice->describe('example');
    }
}
