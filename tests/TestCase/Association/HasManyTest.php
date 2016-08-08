<?php

namespace Muffin\Webservice\Test\TestCase\Association;

use Cake\Database\Expression\OrderByExpression;
use Cake\Database\Expression\QueryExpression;
use Cake\Database\Expression\TupleComparison;
use Cake\Database\IdentifierQuoter;
use Cake\Database\TypeMap;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\Entity;
use Cake\TestSuite\TestCase;
use Muffin\Webservice\Association\HasMany;
use Muffin\Webservice\Model\EndpointRegistry;

/**
 * Tests HasMany class
 *
 */
class HasManyTest extends TestCase
{
    /**
     * Fixtures
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
        $this->author = EndpointRegistry::get('Authors');
        $connection = ConnectionManager::get('test');
        $this->article = $this->getMock(
            'Muffin\Webservice\Model\Endpoint',
            ['find', 'deleteAll', 'delete'],
            [['alias' => 'Articles', 'endpoint' => 'articles', 'connection' => $connection]]
        );
        $this->articlesTypeMap = new TypeMap([
            'Articles.id' => 'integer',
            'id' => 'integer',
            'Articles.title' => 'string',
            'title' => 'string',
            'Articles.author_id' => 'integer',
            'author_id' => 'integer',
            'Articles__id' => 'integer',
            'Articles__title' => 'string',
            'Articles__author_id' => 'integer',
        ]);
        $this->autoQuote = false;
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
     * Test that foreignKey generation ignores database names in target endpoint.
     *
     * @return void
     */
    public function testForeignKey()
    {
        $this->author->endpoint('schema.authors');
        $assoc = new HasMany('Articles', [
            'sourceRepository' => $this->author
        ]);
        $this->assertEquals('author_id', $assoc->foreignKey());
    }

    /**
     * Tests that the association reports it can be joined
     *
     * @return void
     */
    public function testCanBeJoined()
    {
        $assoc = new HasMany('Test');
        $this->assertFalse($assoc->canBeJoined());
    }

    /**
     * Tests sort() method
     *
     * @return void
     */
    public function testSort()
    {
        $assoc = new HasMany('Test');
        $this->assertNull($assoc->sort());
        $assoc->sort(['id' => 'ASC']);
        $this->assertEquals(['id' => 'ASC'], $assoc->sort());
    }

    /**
     * Tests requiresKeys() method
     *
     * @return void
     */
    public function testRequiresKeys()
    {
        $assoc = new HasMany('Test');
        $this->assertTrue($assoc->requiresKeys());

//        $assoc->strategy(HasMany::STRATEGY_SUBQUERY);
//        $this->assertFalse($assoc->requiresKeys());
//
//        $assoc->strategy(HasMany::STRATEGY_SELECT);
//        $this->assertTrue($assoc->requiresKeys());
    }

    /**
     * Test the eager loader method with no extra options
     *
     * @return void
     */
    public function testEagerLoader()
    {
        $config = [
            'sourceRepository' => $this->author,
            'targetRepository' => $this->article,
            'strategy' => 'query'
        ];
        $association = new HasMany('Articles', $config);
        $query = $this->article->query()->read();
        $this->article->method('find')
            ->with('all')
            ->will($this->returnValue($query));
        $keys = [1, 2, 3, 4];

        $callable = $association->eagerLoader(compact('keys', 'query'));
        $row = ['Authors__id' => 1];

        $result = $callable($row);
        $this->assertArrayHasKey('Articles', $result);
        $this->assertEquals($row['Authors__id'], $result['Articles'][0]->author_id);
        $this->assertEquals($row['Authors__id'], $result['Articles'][1]->author_id);

        $row = ['Authors__id' => 2];
        $result = $callable($row);
        $this->assertArrayNotHasKey('Articles', $result);

        $row = ['Authors__id' => 3];
        $result = $callable($row);
        $this->assertArrayHasKey('Articles', $result);
        $this->assertEquals($row['Authors__id'], $result['Articles'][0]->author_id);

        $row = ['Authors__id' => 4];
        $result = $callable($row);
        $this->assertArrayNotHasKey('Articles', $result);
    }

    /**
     * Test the eager loader method with default query clauses
     *
     * @return void
     */
    public function testEagerLoaderWithDefaults()
    {
        $config = [
            'sourceRepository' => $this->author,
            'targetRepository' => $this->article,
            'conditions' => ['Articles.published' => 'Y'],
            'sort' => ['id' => 'ASC'],
            'strategy' => 'query'
        ];
        $association = new HasMany('Articles', $config);
        $keys = [1, 2, 3, 4];

        $query = $this->article->query()->read();
        $this->article->method('find')
            ->with('all')
            ->will($this->returnValue($query));

        $association->eagerLoader(compact('keys', 'query'));

        $expected = ['Articles.published' => 'Y', 'Articles.author_id' => $keys];
        $this->assertWhereClause($expected, $query);

        $expected = ['id' => 'ASC'];
        $this->assertOrderClause($expected, $query);
    }

    /**
     * Test the eager loader method with overridden query clauses
     *
     * @return void
     */
    public function testEagerLoaderWithOverrides()
    {
        $config = [
            'sourceRepository' => $this->author,
            'targetRepository' => $this->article,
            'conditions' => ['Articles.published' => 'Y'],
            'sort' => ['id' => 'ASC'],
            'strategy' => 'query'
        ];
        $this->article->hasMany('Comments');

        $association = new HasMany('Articles', $config);
        $keys = [1, 2, 3, 4];
        $query = $this->article->query()->read();
//        $query->addDefaultTypes($this->article->Comments->source());

        $this->article->method('find')
            ->with('all')
            ->will($this->returnValue($query));

        $association->eagerLoader([
            'conditions' => ['Articles.id !=' => 3],
            'sort' => ['title' => 'DESC'],
            'fields' => ['title', 'author_id'],
            'contain' => ['Comments' => ['fields' => ['comment', 'article_id']]],
            'keys' => $keys,
            'query' => $query
        ]);
//        $expected = [
//            'Articles__title' => 'Articles.title',
//            'Articles__author_id' => 'Articles.author_id'
//        ];
//        $this->assertSelectClause($expected, $query);

        $expected = [
            'Articles.published' => 'Y',
            'Articles.id !=' => 3,
            'Articles.author_id' => $keys
        ];
        $this->assertWhereClause($expected, $query);

        $expected = ['title' => 'DESC'];
        $this->assertOrderClause($expected, $query);
        $this->assertArrayHasKey('Comments', $query->contain());
    }

    /**
     * Tests that eager loader accepts a queryBuilder option
     *
     * @return void
     */
    public function testEagerLoaderWithQueryBuilder()
    {
        $config = [
            'sourceRepository' => $this->author,
            'targetRepository' => $this->article,
            'strategy' => 'query'
        ];
        $association = new HasMany('Articles', $config);
        $keys = [1, 2, 3, 4];
        $query = $this->article->query()->read();
        $this->article->method('find')
            ->with('all')
            ->will($this->returnValue($query));

        $association->eagerLoader(compact('keys', 'query'));

        $expected = [
            'Articles.author_id' => $keys,
        ];
        $this->assertWhereClause($expected, $query);
    }

    /**
     * Test the eager loader method with no extra options
     *
     * @return void
     */
    public function testEagerLoaderMultipleKeys()
    {
        $config = [
            'sourceRepository' => $this->author,
            'targetRepository' => $this->article,
            'strategy' => 'query',
            'foreignKey' => ['author_id', 'site_id']
        ];

        $this->author->primaryKey(['id', 'site_id']);
        $association = new HasMany('Articles', $config);
        $keys = [[1, 10], [2, 20], [3, 30], [4, 40]];
        $query = $this->getMock('Muffin\Webservice\Query', ['all'], [$this->author->webservice(), $this->author]);
        $this->article->method('find')
            ->with('all')
            ->will($this->returnValue($query));

        $results = [
            ['id' => 1, 'title' => 'article 1', 'author_id' => 2, 'site_id' => 10],
            ['id' => 2, 'title' => 'article 2', 'author_id' => 1, 'site_id' => 20]
        ];
        $query->method('all')
            ->will($this->returnValue($results));

//        $tuple = new TupleComparison(
//            ['Articles.author_id', 'Articles.site_id'],
//            $keys,
//            [],
//            'IN'
//        );
//        $query->expects($this->once())->method('andWhere')
//            ->with($tuple)
//            ->will($this->returnSelf());

        $callable = $association->eagerLoader(compact('keys', 'query'));
        $row = ['Authors__id' => 2, 'Authors__site_id' => 10, 'username' => 'author 1'];
        $result = $callable($row);
        $row['Articles'] = [
            ['id' => 1, 'title' => 'article 1', 'author_id' => 2, 'site_id' => 10]
        ];
        $this->assertEquals($row, $result);

        $row = ['Authors__id' => 1, 'username' => 'author 2', 'Authors__site_id' => 20];
        $result = $callable($row);
        $row['Articles'] = [
            ['id' => 2, 'title' => 'article 2', 'author_id' => 1, 'site_id' => 20]
        ];
        $this->assertEquals($row, $result);
    }

    /**
     * Test cascading deletes.
     *
     * @return void
     */
    public function testCascadeDelete()
    {
        $config = [
            'dependent' => true,
            'sourceRepository' => $this->author,
            'targetRepository' => $this->article,
            'conditions' => ['Articles.is_active' => true],
        ];
        $association = new HasMany('Articles', $config);

        $this->article->expects($this->once())
            ->method('deleteAll')
            ->with([
                'Articles.is_active' => true,
                'author_id' => 1
            ]);

        $entity = new Entity(['id' => 1, 'name' => 'PHP']);
        $association->cascadeDelete($entity);
    }

    /**
     * Test cascading delete with has many.
     *
     * @return void
     */
    public function testCascadeDeleteCallbacks()
    {
        $articles = EndpointRegistry::get('Articles');
        $config = [
            'dependent' => true,
            'sourceRepository' => $this->author,
            'targetRepository' => $articles,
            'conditions' => ['Articles.published' => 'Y'],
            'cascadeCallbacks' => true,
        ];
        $association = new HasMany('Articles', $config);

        $author = new Entity(['id' => 1, 'name' => 'mark']);
        $this->assertTrue($association->cascadeDelete($author));

        $query = $articles->query()->read()->where(['author_id' => 1]);
        $this->assertEquals(0, $query->count(), 'Cleared related rows');

        $query = $articles->query()->read()->where(['author_id' => 3]);
        $this->assertEquals(1, $query->count(), 'other records left behind');
    }

    /**
     * Test that saveAssociated() ignores non entity values.
     *
     * @return void
     */
    public function testSaveAssociatedOnlyEntities()
    {
        $mock = $this->getMock('Muffin\Webservice\Model\Endpoint', ['saveAssociated'], [], '', false);
        $config = [
            'sourceRepository' => $this->author,
            'targetRepository' => $mock,
        ];

        $entity = new Entity([
            'username' => 'Mark',
            'email' => 'mark@example.com',
            'articles' => [
                ['title' => 'First Post'],
                new Entity(['title' => 'Second Post']),
            ]
        ]);

        $mock->expects($this->never())
            ->method('saveAssociated');

        $association = new HasMany('Articles', $config);
        $association->saveAssociated($entity);
    }

    /**
     * Tests that property is being set using the constructor options.
     *
     * @return void
     */
    public function testPropertyOption()
    {
        $config = ['propertyName' => 'thing_placeholder'];
        $association = new HasMany('Thing', $config);
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
            'sourceRepository' => $this->author,
            'targetRepository' => $mock,
        ];
        $association = new HasMany('Contacts.Addresses', $config);
        $this->assertEquals('addresses', $association->property());
    }

    /**
     * Assertion method for order by clause contents.
     *
     * @param array $expected The expected join clause.
     * @param \Muffin\Webservice\Query $query The query to check.
     * @return void
     */
    protected function assertJoin($expected, $query)
    {
        if ($this->autoQuote) {
            $driver = $query->connection()->driver();
            $quoter = new IdentifierQuoter($driver);
            foreach ($expected as &$join) {
                $join['endpoint'] = $driver->quoteIdentifier($join['endpoint']);
                if ($join['conditions']) {
                    $quoter->quoteExpression($join['conditions']);
                }
            }
        }
        $this->assertEquals($expected, array_values($query->clause('join')));
    }

    /**
     * Assertion method for where clause contents.
     *
     * @param \Cake\Database\QueryExpression $expected The expected where clause.
     * @param \Muffin\Webservice\Query $query The query to check.
     * @return void
     */
    protected function assertWhereClause($expected, $query)
    {
        $this->assertEquals($expected, $query->clause('where'));
    }

    /**
     * Assertion method for order by clause contents.
     *
     * @param \Cake\Database\QueryExpression $expected The expected where clause.
     * @param \Muffin\Webservice\Query $query The query to check.
     * @return void
     */
    protected function assertOrderClause($expected, $query)
    {
        $this->assertEquals($expected, $query->clause('order'));
    }
}
