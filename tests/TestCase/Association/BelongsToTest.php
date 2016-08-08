<?php

namespace Muffin\Webservice\Test\TestCase\Association;

use Cake\Database\Expression\IdentifierExpression;
use Cake\Database\Expression\QueryExpression;
use Cake\Database\TypeMap;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use Muffin\Webservice\Association\BelongsTo;
use Muffin\Webservice\Model\EndpointRegistry;
use Muffin\Webservice\Model\Resource;

/**
 * Tests BelongsTo class
 *
 */
class BelongsToTest extends TestCase
{

    /**
     * Fixtures to use.
     *
     * @var array
     */
    public $fixtures = [
        'plugin.muffin/webservice.articles',
        'plugin.muffin/webservice.authors',
        'plugin.muffin/webservice.comments'
    ];

    /**
     * Set up
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->company = EndpointRegistry::get('Companies', [
            'schema' => [
                'id' => ['type' => 'integer', 'primaryKey' => true],
                'company_name' => ['type' => 'string'],
            ],
        ]);
        $this->client = EndpointRegistry::get('Clients', [
            'schema' => [
                'id' => ['type' => 'integer', 'primaryKey' => true],
                'client_name' => ['type' => 'string'],
                'company_id' => ['type' => 'integer'],
            ],
        ]);
        $this->companiesTypeMap = new TypeMap([
            'Companies.id' => 'integer',
            'id' => 'integer',
            'Companies.company_name' => 'string',
            'company_name' => 'string',
            'Companies__id' => 'integer',
            'Companies__company_name' => 'string'
        ]);
    }

    /**
     * Tear down
     *
     * @return void
     */
    public function tearDown()
    {
        parent::tearDown();
        EndpointRegistry::clear();
    }

    /**
     * Test that foreignKey generation ignores database names in target table.
     *
     * @return void
     */
    public function testForeignKey()
    {
        $this->company->endpoint('schema.companies');
        $this->client->endpoint('schema.clients');
        $assoc = new BelongsTo('Companies', [
            'sourceRepository' => $this->client,
            'targetRepository' => $this->company,
        ]);
        $this->assertEquals('company_id', $assoc->foreignKey());
    }

    /**
     * Tests that the alias set on associations is actually on the Resource
     *
     * @return void
     */
    public function testCustomAlias()
    {
        $table = EndpointRegistry::get('Articles', [
            'className' => 'TestPlugin.Articles',
        ]);
        $table->addAssociations([
            'belongsTo' => [
                'FooAuthors' => ['className' => 'TestPlugin.Authors', 'foreignKey' => 'author_id']
            ]
        ]);
        $article = $table->find()->contain(['FooAuthors'])->first();

        $this->assertTrue(isset($article->foo_author));
        $this->assertEquals($article->foo_author->name, 'mariano');
        $this->assertNull($article->Authors);
    }

    /**
     * Tests that the correct join and fields are attached to a query depending on
     * the association config
     *
     * @return void
     */
    public function testAttachTo()
    {
        $config = [
            'foreignKey' => 'company_id',
            'sourceRepository' => $this->client,
            'targetRepository' => $this->company,
            'conditions' => ['Companies.is_active' => true]
        ];
        $association = new BelongsTo('Companies', $config);
        $query = $this->client->query();
        $association->attachTo($query);

//        $this->assertEquals(
//            'integer',
//            $query->typeMap()->type('Companies__id'),
//            'Associations should map types.'
//        );
    }

    /**
     * Tests that using belongsto with a table having a multi column primary
     * key will work if the foreign key is passed
     *
     * @return void
     */
    public function testAttachToMultiPrimaryKey()
    {
        $this->company->primaryKey(['id', 'tenant_id']);
        $config = [
            'foreignKey' => ['company_id', 'company_tenant_id'],
            'sourceRepository' => $this->client,
            'targetRepository' => $this->company,
            'conditions' => ['Companies.is_active' => true]
        ];
        $association = new BelongsTo('Companies', $config);
        $query = $this->client->query();
        $association->attachTo($query);
    }

    /**
     * Tests that using belongsto with a table having a multi column primary
     * key will work if the foreign key is passed
     *
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Cannot match provided foreignKey for "Companies", got "(company_id)" but expected foreign key for "(id, tenant_id)"
     * @return void
     */
    public function testAttachToMultiPrimaryKeyMismatch()
    {
        $this->company->primaryKey(['id', 'tenant_id']);
        $query = $this->client->query();
        $config = [
            'foreignKey' => 'company_id',
            'sourceRepository' => $this->client,
            'targetRepository' => $this->company,
            'conditions' => ['Companies.is_active' => true]
        ];
        $association = new BelongsTo('Companies', $config);
        $association->attachTo($query);
    }

    /**
     * Test the cascading delete of BelongsTo.
     *
     * @return void
     */
    public function testCascadeDelete()
    {
        $mock = $this->getMock('Cake\ORM\Table', [], [], '', false);
        $config = [
            'sourceRepository' => $this->client,
            'targetRepository' => $mock,
        ];
        $mock->expects($this->never())
            ->method('find');
        $mock->expects($this->never())
            ->method('delete');

        $association = new BelongsTo('Companies', $config);
        $entity = new Resource(['company_name' => 'CakePHP', 'id' => 1]);
        $this->assertTrue($association->cascadeDelete($entity));
    }

    /**
     * Test that saveAssociated() ignores non entity values.
     *
     * @return void
     */
    public function testSaveAssociatedOnlyEntities()
    {
        $mock = $this->getMock('Cake\ORM\Table', ['saveAssociated'], [], '', false);
        $config = [
            'sourceRepository' => $this->client,
            'targetRepository' => $mock,
        ];
        $mock->expects($this->never())
            ->method('saveAssociated');

        $entity = new Resource([
            'title' => 'A Title',
            'body' => 'A body',
            'author' => ['name' => 'Jose']
        ]);

        $association = new BelongsTo('Authors', $config);
        $result = $association->saveAssociated($entity);
        $this->assertSame($result, $entity);
        $this->assertNull($entity->author_id);
    }

    /**
     * Tests that property is being set using the constructor options.
     *
     * @return void
     */
    public function testPropertyOption()
    {
        $config = ['propertyName' => 'thing_placeholder'];
        $association = new BelongsTo('Thing', $config);
        $this->assertEquals('thing_placeholder', $association->property());
    }

    /**
     * Test that plugin names are omitted from property()
     *
     * @return void
     */
    public function testPropertyNoPlugin()
    {
        $mock = $this->getMock('Muffin\Webservice\Model\Endpoint', [], [], '', false);
        $config = [
            'sourceRepository' => $this->client,
            'targetRepository' => $mock,
        ];
        $association = new BelongsTo('Contacts.Companies', $config);
        $this->assertEquals('company', $association->property());
    }

    /**
     * Tests that attaching an association to a query will trigger beforeFind
     * for the target table
     *
     * @return void
     */
    public function testAttachToBeforeFind()
    {
        $config = [
            'foreignKey' => 'company_id',
            'sourceRepository' => $this->client,
            'targetRepository' => $this->company
        ];
        $listener = $this->getMock('stdClass', ['__invoke']);
        $this->company->eventManager()->attach($listener, 'Model.beforeFind');
        $association = new BelongsTo('Companies', $config);
        $listener->expects($this->once())->method('__invoke')
            ->with(
                $this->isInstanceOf('\Cake\Event\Event'),
                $this->isInstanceOf('\Muffin\Webservice\Query'),
                $this->isInstanceOf('\ArrayObject'),
                false
            );
        $association->attachTo($this->client->query());
    }

    /**
     * Tests that attaching an association to a query will trigger beforeFind
     * for the target table
     *
     * @return void
     */
    public function testAttachToBeforeFindExtraOptions()
    {
        $config = [
            'foreignKey' => 'company_id',
            'sourceRepository' => $this->client,
            'targetRepository' => $this->company
        ];
        $listener = $this->getMock('stdClass', ['__invoke']);
        $this->company->eventManager()->attach($listener, 'Model.beforeFind');
        $association = new BelongsTo('Companies', $config);
        $options = new \ArrayObject(['something' => 'more']);
        $listener->expects($this->once())->method('__invoke')
            ->with(
                $this->isInstanceOf('\Cake\Event\Event'),
                $this->isInstanceOf('\Muffin\Webservice\Query'),
                $options,
                false
            );
        $query = $this->client->query();
        $association->attachTo($query, ['queryBuilder' => function ($q) {
            return $q->applyOptions(['something' => 'more']);
        }]);
    }
}
