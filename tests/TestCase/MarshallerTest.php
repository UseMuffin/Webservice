<?php
declare(strict_types=1);

namespace Muffin\Webservice\Test\TestCase;

use Cake\Datasource\EntityInterface;
use Cake\ORM\Entity;
use Cake\TestSuite\TestCase;
use Muffin\Webservice\Datasource\Connection;
use Muffin\Webservice\Datasource\Marshaller;
use Muffin\Webservice\Model\Resource;
use TestApp\Model\Endpoint\TestEndpoint;

class MarshallerTest extends TestCase
{
    /**
     * @var Marshaller|null
     */
    private ?Marshaller $marshaller;

    /**
     * Create a marshaller instance for testing
     *
     * @return void
     */
    public function setUp(): void
    {
        $connection = new Connection([
            'name' => 'test',
            'service' => 'Test',
        ]);
        $endpoint = new TestEndpoint([
            'connection' => $connection,
            'primaryKey' => 'id',
            'displayField' => 'title',
            'alias' => 'TestEndpoint',
        ]);

        $this->marshaller = new Marshaller($endpoint);
    }

    public function testOne()
    {
        $result = $this->marshaller->one(
            [
                'title' => 'Testing one',
                'body' => 'Testing the marshaller',
            ],
        );

        $this->assertInstanceOf(Resource::class, $result);
        $this->assertEquals('Testing one', $result->get('title'));
        $this->assertEquals('Testing the marshaller', $result->get('body'));
    }

    public function testOneWithFieldList()
    {
        $result = $this->marshaller->one(
            [
                'id' => '',
                'title' => 'Testing one',
                'body' => 'Testing the marshaller',
            ],
            [
                'fieldList' => ['title'],
                'validate' => false,
            ]
        );

        $this->assertInstanceOf(Resource::class, $result);
        $this->assertEquals('Testing one', $result->get('title'));
        $this->assertNull($result->get('body'));
    }

    public function testOneWithAccessibleFields()
    {
        $result = $this->marshaller->one(
            [
                'title' => 'Testing one',
                'body' => 'Testing the marshaller',
            ],
            [
                'accessibleFields' => ['body' => false],
                'validate' => false,
            ]
        );

        $this->assertInstanceOf(Resource::class, $result);
        $this->assertEquals('Testing one', $result->get('title'));
        $this->assertNull($result->get('body'));
    }

    public function testOneWithNoFields()
    {
        $result = $this->marshaller->one(
            [
                'title' => 'Testing one',
                'body' => 'Testing the marshaller',
            ],
            [
                'fieldList' => [],
                'validate' => false,
            ]
        );

        $this->assertInstanceOf(Resource::class, $result);
        $this->assertNull($result->get('title'));
        $this->assertNull($result->get('body'));
    }

    public function testOneWithNoAccessible()
    {
        $result = $this->marshaller->one(
            [
                'title' => 'Testing one',
                'body' => 'Testing the marshaller',
            ],
            [
                'accessibleFields' => ['title' => false, 'body' => false],
                'validate' => false,
            ]
        );

        $this->assertInstanceOf(Resource::class, $result);
        $this->assertNull($result->get('title'));
        $this->assertNull($result->get('body'));
    }

    /**
     * If the `fieldList` option is set, it should take precedence over the `accessibleFields` option
     *
     * @return void
     */
    public function testOneEnsuringFieldListBeforeAccessible()
    {
        $result = $this->marshaller->one(
            [
                'title' => 'Testing one',
                'body' => 'Testing the marshaller',
            ],
            [
                'fieldList' => ['title', 'body'],
                'accessibleFields' => ['title' => false, 'body' => false],
                'validate' => false,
            ]
        );

        $this->assertInstanceOf(Resource::class, $result);
        $this->assertEquals('Testing one', $result->get('title'));
        $this->assertEquals('Testing the marshaller', $result->get('body'));
    }

    public function testOneWithFailedValidation()
    {
        $result = $this->marshaller->one(
            [
                'title' => 'Testing one',
                'body' => 'Foo',
            ]
        );

        $this->assertInstanceOf(Resource::class, $result);
        $this->assertEquals('Testing one', $result->get('title'));
        $this->assertNull($result->get('body'));
        $this->assertEquals(
            ['body' => ['minLength' => 'Must be 5 characters or longer']],
            $result->getErrors()
        );
        $this->assertEquals('Foo', $result->getInvalidField('body'));
    }

    /**
     * Note that the request data is an array of arrays
     *
     * @return void
     */
    public function testMany()
    {
        $result = $this->marshaller->many([
            [
                'title' => 'First',
                'body' => 'First body',
            ],
            [
                'title' => 'Second',
                'body' => 'Second body',
            ],
            'Non array value',
        ]);

        $this->assertNotEmpty($result);
        $this->assertCount(2, $result);
        $this->assertArrayHasKey(0, $result);
        $this->assertInstanceOf(Resource::class, $result[0]);
        $this->assertEquals('First', $result[0]->get('title'));
        $this->assertEquals('First body', $result[0]->get('body'));

        $this->assertArrayHasKey(1, $result);
        $this->assertInstanceOf(Resource::class, $result[1]);
        $this->assertEquals('Second', $result[1]->get('title'));
        $this->assertEquals('Second body', $result[1]->get('body'));
    }

    public function testMerge()
    {
        $entity = new Entity([
            'id' => 1,
            'title' => 'Testing',
            'body' => 'The test body',
        ]);

        $data = [
            'title' => 'Changed the title',
            'body' => 'Changed the body',
        ];

        $result = $this->marshaller->merge($entity, $data);

        $this->assertInstanceOf(EntityInterface::class, $result);
        $this->assertEquals('Changed the title', $entity->get('title'));
        $this->assertEquals('Changed the body', $entity->get('body'));
    }

    public function testMergeWithValidationErrors()
    {
        $entity = new Entity([
            'id' => 1,
            'title' => 'Testing',
            'body' => 'Longer body',
        ]);
        $entity->setNew(false);

        $data = [
            'title' => 'Changed the title',
            'body' => 'Foo',
        ];

        $result = $this->marshaller->merge($entity, $data);

        $this->assertInstanceOf(EntityInterface::class, $result);
        $this->assertEquals('Changed the title', $entity->get('title'));
        $this->assertEquals('Longer body', $entity->get('body'));
        $this->assertEquals(
            ['body' => ['minLength' => 'Must be 5 characters or longer']],
            $result->getErrors()
        );
        $this->assertEquals('Foo', $result->getInvalidField('body'));
    }

    public function testMergeWithOptions()
    {
        $entity = new Entity([
            'id' => 1,
            'title' => 'Testing',
            'body' => 'Longer body',
        ]);

        $data = [
            'title' => 'Changed the title',
            'body' => 'Changed the body',
        ];

        $result = $this->marshaller->merge(
            $entity,
            $data,
            [
                'fieldList' => ['title'],
                'accessibleFields' => ['title' => true, 'body' => false],
                'validate' => false,
            ]
        );

        $this->assertInstanceOf(EntityInterface::class, $result);
        $this->assertEquals('Changed the title', $entity->get('title'));
        $this->assertEquals('Longer body', $entity->get('body'));
    }

    public function testMergeMany()
    {
        $entities = [
            new Entity(['id' => 1, 'title' => 'First', 'body' => 'First body']),
            new Entity(['id' => 2, 'title' => 'Second', 'body' => 'Second body']),
            new Entity(['id' => 3, 'title' => 'Not matching', 'body' => 'An entity which does not match data']),
        ];

        $data = [
            ['id' => 1, 'title' => 'Changed first', 'body' => 'First body'],
            ['id' => 2, 'title' => 'Changed second', 'body' => 'Second body'],
        ];

        $result = $this->marshaller->mergeMany($entities, $data);

        $this->assertCount(2, $result);
        $this->assertEquals('Changed first', $result[0]->get('title'));
        $this->assertEquals('Changed second', $result[1]->get('title'));
    }
}
