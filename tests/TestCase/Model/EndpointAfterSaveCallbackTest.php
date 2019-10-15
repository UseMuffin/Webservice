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
}
