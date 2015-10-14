<?php

namespace Muffin\Webservice\Test\TestCase\Model;

use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\TestSuite\TestCase;
use Muffin\Webservice\AbstractDriver;
use Muffin\Webservice\Connection;
use Muffin\Webservice\Model\Endpoint;
use Muffin\Webservice\Model\Resource;
use Muffin\Webservice\Query;
use Muffin\Webservice\ResultSet;
use Muffin\Webservice\Webservice\Webservice;

class TestDriver extends AbstractDriver
{

    /**
     * Initialize is used to easily extend the constructor.
     *
     * @return void
     */
    public function initialize()
    {
    }

    /**
     * @inheritDoc
     */
    protected function _createWebservice($className, array $options = [])
    {
        return new EndpointTestWebservice($options);
    }
}

class EndpointTestWebservice extends Webservice
{

    public $resources;

    public function initialize()
    {
        parent::initialize();

        $this->resources = [
            new Resource([
                'id' => 1,
                'title' => 'Hello World',
                'body' => 'Some text'
            ], [
                'markNew' => false,
                'markClean' => true
            ]),
            new Resource([
                'id' => 2,
                'title' => 'New ORM',
                'body' => 'Some more text'
            ], [
                'markNew' => false,
                'markClean' => true
            ]),
            new Resource([
                'id' => 3,
                'title' => 'Webservices',
                'body' => 'Even more text'
            ], [
                'markNew' => false,
                'markClean' => true
            ])
        ];
    }


    protected function _executeCreateQuery(Query $query, array $options = [])
    {
        $fields = $query->set();

        if (!is_numeric($fields['id'])) {
            return false;
        }

        $this->resources[] = new Resource($fields, [
            'markNew' => false,
            'markClean' => true
        ]);

        return true;
    }

    protected function _executeReadQuery(Query $query, array $options = [])
    {
        if (!empty($query->where()['id'])) {
            $index = $this->conditionsToIndex($query->where());

            if (!isset($this->resources[$index])) {
                return new ResultSet([], 0);
            }

            return new ResultSet([
                $this->resources[$index]
            ], 1);
        }

        return new ResultSet($this->resources, count($this->resources));
    }

    protected function _executeUpdateQuery(Query $query, array $options = [])
    {
        $this->resources[$this->conditionsToIndex($query->where())]->set($query->set());

        $this->resources[$this->conditionsToIndex($query->where())]->clean();

        return 1;
    }

    protected function _executeDeleteQuery(Query $query, array $options = [])
    {
        $conditions = $query->where();

        if (is_int($conditions['id'])) {
            $exists = isset($this->resources[$this->conditionsToIndex($conditions)]);

            unset($this->resources[$this->conditionsToIndex($conditions)]);

            return ($exists) ? 1 : 0;
        } elseif (is_array($conditions['id'])) {
            $deleted = 0;

            foreach ($conditions['id'] as $id) {
                if (!isset($this->resources[$id - 1])) {
                    continue;
                }

                $deleted++;
                unset($this->resources[$id - 1]);
            }

            return $deleted;
        }

        return 0;
    }

    public function conditionsToIndex(array $conditions)
    {
        return $conditions['id'] - 1;
    }
}

class EndpointTest extends TestCase
{

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

        $this->endpoint = new Endpoint([
            'connection' => new Connection([
                'name' => 'test',
                'driver' => '\Muffin\Webservice\Test\TestCase\Model\TestDriver'
            ]),
            'primaryKey' => 'id',
            'displayField' => 'title'
        ]);
    }

    public function testFind()
    {
        $query = $this->endpoint->find();

        $this->assertInstanceOf('\Muffin\Webservice\Query', $query);
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
}
