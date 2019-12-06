<?php
declare(strict_types=1);

namespace Muffin\Webservice\Test\TestCase\Model;

use BadMethodCallException;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Event\EventManager;
use Cake\TestSuite\TestCase;
use Muffin\Webservice\Connection;
use Muffin\Webservice\Exception\MissingResourceClassException;
use Muffin\Webservice\Exception\UnexpectedDriverException;
use Muffin\Webservice\Model\Endpoint;
use Muffin\Webservice\Model\Resource;
use Muffin\Webservice\Query;
use Muffin\Webservice\Test\test_app\Model\Endpoint\AppEndpoint;
use Muffin\Webservice\Test\test_app\Model\Endpoint\ExampleEndpoint;
use Muffin\Webservice\Test\test_app\Model\Endpoint\TestEndpoint;
use Muffin\Webservice\Test\test_app\Webservice\TestWebservice;
use Muffin\Webservice\Webservice\WebserviceInterface;
use SomeVendor\SomePlugin\Model\Endpoint\PluginEndpoint;

class EndpointTest extends TestCase
{
    /**
     * @var \Muffin\Webservice\Connection
     */
    public $connection;

    /**
     * @var Endpoint
     */
    public $endpoint;

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->connection = new Connection([
            'name' => 'test',
            'service' => 'Test',
        ]);
        $this->endpoint = new TestEndpoint([
            'connection' => $this->connection,
            'primaryKey' => 'id',
            'displayField' => 'title',
        ]);
    }

    public function providerEndpointNames()
    {
        return [
            'No inflector' => ['user-groups', null, 'user_groups'],
            'Dasherize' => ['user-groups', 'dasherize', 'user-groups'],
            'Variable' => ['user-groups', 'variable', 'userGroups'],
        ];
    }

    /**
     * @dataProvider providerEndpointNames
     * @param string $name
     * @param string|null $inflector
     * @param string $expected
     */
    public function testEndpointName($name, $inflector, $expected)
    {
        $endpoint = new Endpoint(['name' => $name, 'inflect' => $inflector]);
        $this->assertSame($expected, $endpoint->getName());
    }

    public function testEndpointNameUsingEndpoint()
    {
        $endpoint = new Endpoint(['endpoint' => 'example']);
        $this->assertSame('example', $endpoint->getName());
    }

    public function testFind()
    {
        $query = $this->endpoint->find();

        $this->assertInstanceOf('\Muffin\Webservice\Query', $query);
    }

    public function testFindByTitle()
    {
        $this->assertEquals(new Resource([
            'id' => 3,
            'title' => 'Webservices',
            'body' => 'Even more text',
        ], [
            'markNew' => false,
            'markClean' => true,
        ]), $this->endpoint->findByTitle('Webservices')->first());
    }

    public function testFindList()
    {
        $this->assertEquals([
            1 => 'Hello World',
            2 => 'New ORM',
            3 => 'Webservices',
        ], $this->endpoint->find('list')->toArray());

        $this->assertEquals([
            'Hello World' => 'Some text',
            'New ORM' => 'Some more text',
            'Webservices' => 'Even more text',
        ], $this->endpoint->find('list', [
            'keyField' => 'title',
            'valueField' => 'body',
        ])->toArray());
    }

    public function testGet()
    {
        $resource = $this->endpoint->get(2);

        $this->assertEquals('New ORM', $resource->title);
    }

    public function testExists()
    {
        $this->assertTrue($this->endpoint->exists(['id' => 1]));

        $this->assertFalse($this->endpoint->exists(['id' => 10]));
    }

    public function testGetNonExisting()
    {
        $this->expectException(RecordNotFoundException::class);

        $this->endpoint->get(10);
    }

    public function testSave()
    {
        $resource = new Resource([
            'id' => 4,
            'title' => 'Loads of fun',
            'body' => 'Woot',
        ]);

        $savedResource = $this->endpoint->save($resource);
        $this->assertInstanceOf('\Muffin\Webservice\Model\Resource', $savedResource);
        $this->assertFalse($savedResource->isNew());

        $newResource = $this->endpoint->get(4);
        $this->assertInstanceOf('\Muffin\Webservice\Model\Resource', $newResource);
        $this->assertEquals([
            'id' => 4,
            'title' => 'Loads of fun',
            'body' => 'Woot',
        ], $newResource->toArray());

        $invalidResource = new Resource([
            'id' => 'Hello',
            'title' => 'Loads of fun',
            'body' => 'Woot',
        ]);

        $savedInvalidResource = $this->endpoint->save($invalidResource);
        $this->assertFalse($savedInvalidResource);
    }

    public function testUpdatingSave()
    {
        $resource = $this->endpoint->get(2);

        $resource->title = 'New ORM for webservices';

        $savedResource = $this->endpoint->save($resource);
        $this->assertInstanceOf('\Muffin\Webservice\Model\Resource', $savedResource);
        $this->assertFalse($savedResource->isNew());

        $newResource = $this->endpoint->get(2);
        $this->assertEquals($newResource->title, 'New ORM for webservices');
    }

    public function testDelete()
    {
        $this->expectException(RecordNotFoundException::class);

        $resource = $this->endpoint->get(2);

        $this->assertTrue($this->endpoint->delete($resource));

        $this->endpoint->get(2);
    }

    public function testDeleteAll()
    {
        $amount = $this->endpoint->deleteAll([
            'id' => [1, 2, 3],
        ]);

        $this->assertEquals(3, $amount);

        $this->assertEquals(0, $this->endpoint->find()->count());
    }

    public function testNewEntity()
    {
        $resource = new Resource([
            'title' => 'New entity',
            'body' => 'New entity body',
        ]);
        $resource->setSource('test');

        $this->assertEquals($resource, $this->endpoint->newEntity([
            'title' => 'New entity',
            'body' => 'New entity body',
        ]));
    }

    public function testNewEntities()
    {
        $resource1 = new Resource([
            'title' => 'New entity',
            'body' => 'New entity body',
        ]);
        $resource1->setSource('test');

        $resource2 = new Resource([
            'title' => 'Second new entity',
            'body' => 'Second new entity body',
        ]);
        $resource2->setSource('test');

        $this->assertEquals([
            $resource1,
            $resource2,
        ], $this->endpoint->newEntities([
            [
                'title' => 'New entity',
                'body' => 'New entity body',
            ],
            [
                'title' => 'Second new entity',
                'body' => 'Second new entity body',
            ],
        ]));
    }

    public function testDefaultConnectionName()
    {
        $this->assertEquals('test_app', AppEndpoint::defaultConnectionName());
        $this->assertEquals('some_plugin', PluginEndpoint::defaultConnectionName());
    }

    /**
     * Test that aliasField() works.
     *
     * @return void
     */
    public function testAliasField()
    {
        $endpoint = new Endpoint(['alias' => 'Users']);
        $this->assertEquals('Users.id', $endpoint->aliasField('id'));
    }

    /**
     * Tests connection method
     *
     * @return void
     */
    public function testConnection()
    {
        $endpoint = new Endpoint(['endpoint' => 'users']);
        $this->assertNull($endpoint->getConnection());
        $endpoint->setConnection($this->connection);
        $this->assertSame($this->connection, $endpoint->getConnection());
    }

    /**
     * Tests inflectionMethod method
     *
     * @return void
     */
    public function testInflectionMethod()
    {
        $endpoint = new Endpoint(['endpoint' => 'users']);
        $this->assertSame('underscore', $endpoint->getInflectionMethod());
        $endpoint->setInflectionMethod('dasherize');
        $this->assertSame('dasherize', $endpoint->getInflectionMethod());
    }

    /**
     * Tests primaryKey method
     *
     * @return void
     */
    public function testPrimaryKey()
    {
        $endpoint = new Endpoint([
            'endpoint' => 'users',
            'schema' => [
                'id' => ['type' => 'integer', 'primaryKey' => true],
            ],
        ]);
        $this->assertEquals('id', $endpoint->getPrimaryKey());
        $endpoint->setPrimaryKey('thingID');
        $this->assertEquals('thingID', $endpoint->getPrimaryKey());

        $endpoint->setPrimaryKey(['thingID', 'user_id']);
        $this->assertEquals(['thingID', 'user_id'], $endpoint->getPrimaryKey());
    }

    /**
     * Tests that name will be selected as a displayField
     *
     * @return void
     */
    public function testDisplayFieldName()
    {
        $endpoint = new Endpoint([
            'endpoint' => 'users',
            'schema' => [
                'foo' => ['type' => 'string'],
                'name' => ['type' => 'string'],
            ],
        ]);
        $this->assertEquals('name', $endpoint->getDisplayField());
    }

    /**
     * Tests that title will be selected as a displayField
     *
     * @return void
     */
    public function testDisplayFieldTitle()
    {
        $endpoint = new Endpoint([
            'endpoint' => 'users',
            'schema' => [
                'foo' => ['type' => 'string'],
                'title' => ['type' => 'string'],
            ],
        ]);
        $this->assertEquals('title', $endpoint->getDisplayField());
    }

    /**
     * Tests that no displayField will fallback to primary key
     *
     * @return void
     */
    public function testDisplayFallback()
    {
        $endpoint = new Endpoint([
            'endpoint' => 'users',
            'schema' => [
                'id' => ['type' => 'string', 'primaryKey' => true],
                'foo' => ['type' => 'string'],
            ],
        ]);
        $this->assertEquals('id', $endpoint->getDisplayField());
    }

    /**
     * Tests that displayField can be changed
     *
     * @return void
     */
    public function testDisplaySet()
    {
        $endpoint = new Endpoint([
            'endpoint' => 'users',
            'schema' => [
                'id' => ['type' => 'string', 'primaryKey' => true],
                'foo' => ['type' => 'string'],
            ],
        ]);
        $this->assertEquals('id', $endpoint->getDisplayField());
        $endpoint->setDisplayField('foo');
        $this->assertEquals('foo', $endpoint->getDisplayField());
    }

    /**
     * Tests schema method
     *
     * @return void
     */
    public function testSchema()
    {
        $endpoint = new Endpoint(['endpoint' => 'another']);
        $schema = ['id' => ['type' => 'integer']];
        $endpoint->setSchema($schema);
        $this->assertEquals(
            new \Muffin\Webservice\Schema('another', $schema),
            $endpoint->getSchema()
        );
    }

    public function testFindWithSelectAndWhere()
    {
        $fields = ['id', 'name', 'avatar', 'biography'];
        $conditions = ['id' => 1];

        $query = $this->endpoint->find()
            ->select($fields)
            ->where($conditions);

        $this->assertInstanceOf('\Muffin\Webservice\Query', $query);
        $this->assertSame($fields, $query->clause('select'));
        $this->assertSame($conditions, $query->clause('where'));
    }

    public function testConstructorEventManager()
    {
        $eventManager = $this->getMockBuilder(EventManager::class)->getMock();
        $endpoint = new Endpoint([
            'endpoint' => 'another',
            'eventManager' => $eventManager,
        ]);

        $this->assertSame($eventManager, $endpoint->getEventManager());
    }

    public function testConstructorResourceClass()
    {
        $endpoint = new Endpoint([
            'name' => 'example',
            'resourceClass' => 'Example',
        ]);

        $this->assertSame('Muffin\Webservice\Test\test_app\Model\Resource\Example', $endpoint->getResourceClass());
    }

    public function testSetResourceMissingClass()
    {
        $this->expectException(MissingResourceClassException::class);

        new Endpoint([
            'name' => 'example',
            'resourceClass' => 'Missing',
        ]);
    }

    public function testHasField()
    {
        $this->assertTrue($this->endpoint->hasField('title'));
    }

    /**
     * Fake an incorrect return of the schema to check the exception
     */
    public function testGetPrimaryKeyException()
    {
        $this->expectException(UnexpectedDriverException::class);

        $endpoint = $this->getMockBuilder(Endpoint::class)
            ->setMethods(['getSchema'])
            ->getMock();

        $endpoint->expects($this->once())
            ->method('getSchema')
            ->willReturn(false);

        $endpoint->getPrimaryKey();
    }

    public function testSetWebservice()
    {
        $testWebservice = new TestWebservice();
        $return = $this->endpoint->setWebservice('test', $testWebservice);

        $this->assertInstanceOf(Endpoint::class, $return);
        $this->assertInstanceOf(WebserviceInterface::class, $this->endpoint->getWebservice());
    }

    public function testSetWebserviceException()
    {
        $this->expectException(UnexpectedDriverException::class);

        $endpoint = $this->getMockBuilder(Endpoint::class)
            ->setMethods(['getConnection'])
            ->getMock();

        $endpoint->expects($this->once())
            ->method('getConnection')
            ->willReturn(false);

        $testWebservice = new TestWebservice();
        $endpoint->setWebservice('test', $testWebservice);
    }

    public function testHasFinder()
    {
        $this->assertTrue($this->endpoint->hasFinder('Examples'));
        $this->assertFalse($this->endpoint->hasFinder('Missing'));
    }

    public function testCallMissingFinder()
    {
        $this->expectException(BadMethodCallException::class);

        $query = $this->getMockBuilder(Query::class)
            ->setConstructorArgs([new TestWebservice(), $this->endpoint])
            ->getMock();

        $this->endpoint->callFinder('Missing', $query);
    }

    public function testDebugInfo()
    {
        $expected = [
            'registryAlias' => 'test',
            'alias' => 'test',
            'endpoint' => 'test',
            'resourceClass' => 'Muffin\\Webservice\\Model\\Resource',
            'defaultConnection' => 'test_app',
            'connectionName' => 'test',
            'inflector' => 'underscore',
        ];
        $result = $this->endpoint->__debugInfo();

        $this->assertEquals($expected, $result);
    }

    public function testGetResourceWithCustomResource()
    {
        $endpoint = new ExampleEndpoint();

        $this->assertEquals('Muffin\Webservice\Test\test_app\Model\Resource\Example', $endpoint->getResourceClass());
    }
}
