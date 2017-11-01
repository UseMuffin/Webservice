<?php

namespace Muffin\Webservice\Test\TestCase\Model;

use Cake\TestSuite\TestCase;
use Muffin\Webservice\Connection;
use Muffin\Webservice\Model\Endpoint;
use Muffin\Webservice\Model\Resource;
use Muffin\Webservice\Test\test_app\Model\Endpoint\AppEndpoint;
use Muffin\Webservice\Test\test_app\Model\Endpoint\TestEndpoint;
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
    public function setUp()
    {
        parent::setUp();

        $this->connection = new Connection([
            'name' => 'test',
            'service' => 'Test'
        ]);
        $this->endpoint = new TestEndpoint([
            'connection' => $this->connection,
            'primaryKey' => 'id',
            'displayField' => 'title'
        ]);
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
            'body' => 'Even more text'
        ], [
            'markNew' => false,
            'markClean' => true
        ]), $this->endpoint->findByTitle('Webservices')->first());
    }

    public function testFindList()
    {
        $this->assertEquals([
            1 => 'Hello World',
            2 => 'New ORM',
            3 => 'Webservices'
        ], $this->endpoint->find('list')->toArray());

        $this->assertEquals([
            'Hello World' => 'Some text',
            'New ORM' => 'Some more text',
            'Webservices' => 'Even more text'
        ], $this->endpoint->find('list', [
            'keyField' => 'title',
            'valueField' => 'body'
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

    /**
     * @expectedException \Cake\Datasource\Exception\RecordNotFoundException
     */
    public function testGetNonExisting()
    {
        $this->endpoint->get(10);
    }

    public function testSave()
    {
        $resource = new Resource([
            'id' => 4,
            'title' => 'Loads of fun',
            'body' => 'Woot'
        ]);

        $savedResource = $this->endpoint->save($resource);
        $this->assertInstanceOf('\Muffin\Webservice\Model\Resource', $savedResource);
        $this->assertFalse($savedResource->isNew());

        $newResource = $this->endpoint->get(4);
        $this->assertInstanceOf('\Muffin\Webservice\Model\Resource', $newResource);
        $this->assertEquals([
            'id' => 4,
            'title' => 'Loads of fun',
            'body' => 'Woot'
        ], $newResource->toArray());

        $invalidResource = new Resource([
            'id' => 'Hello',
            'title' => 'Loads of fun',
            'body' => 'Woot'
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

    /**
     * @expectedException \Cake\Datasource\Exception\RecordNotFoundException
     */
    public function testDelete()
    {
        $resource = $this->endpoint->get(2);

        $this->assertTrue($this->endpoint->delete($resource));

        $this->endpoint->get(2);
    }

    public function testDeleteAll()
    {
        $amount = $this->endpoint->deleteAll([
            'id' => [1, 2, 3]
        ]);

        $this->assertEquals(3, $amount);

        $this->assertEquals(0, $this->endpoint->find()->count());
    }

    public function testNewEntity()
    {
        $this->assertEquals(new Resource([
            'title' => 'New entity',
            'body' => 'New entity body'
        ]), $this->endpoint->newEntity([
           'title' => 'New entity',
            'body' => 'New entity body'
        ]));
    }

    public function testNewEntities()
    {
        $this->assertEquals([
            new Resource([
                'title' => 'New entity',
                'body' => 'New entity body'
            ]),
            new Resource([
                'title' => 'Second new entity',
                'body' => 'Second new entity body'
            ])
        ], $this->endpoint->newEntities([
            [
                'title' => 'New entity',
                'body' => 'New entity body'
            ],
            [
                'title' => 'Second new entity',
                'body' => 'Second new entity body'
            ]
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
        $this->assertNull($endpoint->connection());
        $endpoint->connection($this->connection);
        $this->assertSame($this->connection, $endpoint->connection());
    }

    /**
     * Tests inflect method
     *
     * @return void
     */
    public function testInflect()
    {
        $endpoint = new Endpoint(['endpoint' => 'users']);
        $this->assertSame('underscore', $endpoint->inflect());
        $endpoint->inflect('dasherize');
        $this->assertSame('dasherize', $endpoint->inflect());
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
            ]
        ]);
        $this->assertEquals('id', $endpoint->primaryKey());
        $endpoint->primaryKey('thingID');
        $this->assertEquals('thingID', $endpoint->primaryKey());

        $endpoint->primaryKey(['thingID', 'user_id']);
        $this->assertEquals(['thingID', 'user_id'], $endpoint->primaryKey());
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
                'name' => ['type' => 'string']
            ]
        ]);
        $this->assertEquals('name', $endpoint->displayField());
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
                'title' => ['type' => 'string']
            ]
        ]);
        $this->assertEquals('title', $endpoint->displayField());
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
            ]
        ]);
        $this->assertEquals('id', $endpoint->displayField());
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
            ]
        ]);
        $this->assertEquals('id', $endpoint->displayField());
        $endpoint->displayField('foo');
        $this->assertEquals('foo', $endpoint->displayField());
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
        $endpoint->schema($schema);
        $this->assertEquals(
            new \Muffin\Webservice\Schema('another', $schema),
            $endpoint->schema()
        );
    }
}
