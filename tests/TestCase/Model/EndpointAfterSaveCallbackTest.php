<?php

namespace Muffin\Webservice\Test\TestCase\Model;

use Cake\TestSuite\TestCase;
use Muffin\Webservice\Connection;
use Muffin\Webservice\Model\Resource;
use Muffin\Webservice\Test\test_app\Model\Endpoint\CallbackEndpoint;

class EndpointAfterSaveCallbackTest extends TestCase
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
        $this->endpoint = new CallbackEndpoint([
            'connection' => $this->connection,
            'primaryKey' => 'id',
            'displayField' => 'title',
            'schema' => [
                'id' => ['type' => 'int'],
                'title' => ['type' => 'string'],
                'body' => ['type' => 'string'],
            ]
        ]);
    }

    /**
     * Test afterSave return altered data
     */
    public function testAfterSave()
    {
        $resource = new Resource([
            'id' => 4,
            'title' => 'Loads of fun',
            'body' => 'Woot'
        ]);

        $savedResource = $this->endpoint->save($resource);
        $this->assertInstanceOf('\Muffin\Webservice\Model\Resource', $savedResource);
        $this->assertEquals([
            'id' => 4,
            'title' => 'Loads of sun',
            'body' => 'Woot'
        ], $savedResource->toArray());

        $newResource = $this->endpoint->get(4);
        $this->assertInstanceOf('\Muffin\Webservice\Model\Resource', $newResource);
        $this->assertEquals([
            'id' => 4,
            'title' => 'Loads of fun',
            'body' => 'Woot'
        ], $newResource->toArray());
    }

    /**
     * Test beforeDelete and afterDelete return altered data
     *
     */
    public function testBeforeAndAfterDelete()
    {
        $resource1 = new Resource([
            'id' => 4,
            'title' => 'Loads of sun',
            'body' => 'Woot'
        ]);
        $resource2 = new Resource([
            'id' => 5,
            'title' => 'I love sun',
            'body' => 'Woot'
        ]);
        $resource3 = new Resource([
            'id' => 6,
            'title' => 'I need sun',
            'body' => 'Woot'
        ]);
        $savedResource = $this->endpoint->save($resource1);
        $this->assertInstanceOf('\Muffin\Webservice\Model\Resource', $savedResource);
        $savedResource = $this->endpoint->save($resource2);
        $this->assertInstanceOf('\Muffin\Webservice\Model\Resource', $savedResource);
        $savedResource = $this->endpoint->save($resource3);
        $this->assertInstanceOf('\Muffin\Webservice\Model\Resource', $savedResource);

        $resource = $this->endpoint->get(4);
        $deletedResource = $this->endpoint->delete($resource);
        $this->assertTrue($deletedResource);

        $editedResource = $this->endpoint->get(5);
        $this->assertEquals([
            'id' => 5,
            'title' => 'I love fun',
            'body' => 'Woot'
        ], $editedResource->toArray());

        $editedResource2 = $this->endpoint->get(6);
        $this->assertEquals([
            'id' => 6,
            'title' => 'I need fun',
            'body' => 'Woot'
        ], $editedResource2->toArray());
    }
}
