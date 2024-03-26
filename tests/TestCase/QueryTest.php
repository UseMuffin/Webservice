<?php
declare(strict_types=1);

namespace Muffin\Webservice\Test\TestCase;

use Cake\Database\Expression\ComparisonExpression;
use Cake\TestSuite\TestCase;
use Muffin\Webservice\Datasource\Query;
use Muffin\Webservice\Datasource\ResultSet;
use Muffin\Webservice\Model\Endpoint;
use Muffin\Webservice\Model\Resource;
use TestApp\Webservice\StaticWebservice;
use UnexpectedValueException;

class QueryTest extends TestCase
{
    /**
     * @var Query|null
     */
    public ?Query $query;

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->query = new Query(new StaticWebservice(), new Endpoint());
    }

    public function testAction()
    {
        $this->assertNull($this->query->clause('action'));

        $this->assertEquals($this->query, $this->query->action(Query::ACTION_READ));
        $this->assertEquals(Query::ACTION_READ, $this->query->clause('action'));
    }

    public function testActionMethods()
    {
        $this->assertEquals($this->query, $this->query->create());
        $this->assertEquals(Query::ACTION_CREATE, $this->query->clause('action'));

        $this->assertEquals($this->query, $this->query->read());
        $this->assertEquals(Query::ACTION_READ, $this->query->clause('action'));

        $this->assertEquals($this->query, $this->query->update());
        $this->assertEquals(Query::ACTION_UPDATE, $this->query->clause('action'));

        $this->assertEquals($this->query, $this->query->delete());
        $this->assertEquals(Query::ACTION_DELETE, $this->query->clause('action'));
    }

    public function testAliasField()
    {
        $this->assertEquals(['field' => 'field'], $this->query->aliasField('field'));
    }

    public function testCountNonReadAction()
    {
        $this->assertEquals(0, $this->query->count());
    }

    public function testCount()
    {
        $this->query->read();

        $this->assertEquals(3, $this->query->count());
    }

    public function testFirst()
    {
        $this->assertEquals(new Resource([
            'id' => 1,
            'title' => 'Hello World',
        ]), $this->query->first());
    }

    public function testApplyOptions()
    {
        $this->assertEquals($this->query, $this->query->applyOptions([
            'page' => 1,
            'limit' => 2,
            'order' => [
                'field' => 'ASC',
            ],
            'customOption' => 'value',
        ]));
        $this->assertEquals(1, $this->query->clause('page'));
        $this->assertEquals(2, $this->query->clause('limit'));
        $this->assertEquals([
            'field' => 'ASC',
        ], $this->query->clause('order'));
        $this->assertEquals([
            'customOption' => 'value',
        ], $this->query->getOptions());
    }

    public function testFind()
    {
        $this->query->getEndpoint()->setPrimaryKey('id');
        $this->query->getEndpoint()->setDisplayField('title');

        $this->assertEquals($this->query, $this->query->find('list'));

        $debugInfo = $this->query->__debugInfo();

        $this->assertIsCallable($debugInfo['formatters'][0]);
    }

    public function testSetInvalidAction()
    {
        $this->expectException(UnexpectedValueException::class);

        $this->query->read();

        $this->query->set([]);
    }

    public function testSet()
    {
        $this->query->update();

        $this->assertEquals($this->query, $this->query->set([
            'field' => 'value',
        ]));
        $this->assertEquals([
            'field' => 'value',
        ], $this->query->clause('set'));
    }

    public function testPage()
    {
        $this->assertEquals($this->query, $this->query->page(10));

        $this->assertEquals(10, $this->query->clause('page'));
    }

    public function testPageWithLimit()
    {
        $this->assertEquals($this->query, $this->query->page(10, 20));

        $this->assertEquals(10, $this->query->clause('page'));
        $this->assertEquals(20, $this->query->clause('limit'));
    }

    public function testOffset()
    {
        $this->assertEquals($this->query, $this->query->offset(10));

        $this->assertEquals(10, $this->query->clause('offset'));
    }

    public function testOrder()
    {
        $this->assertEquals($this->query, $this->query->order([
            'field' => 'ASC',
        ]));

        $this->assertEquals([
            'field' => 'ASC',
        ], $this->query->clause('order'));
    }

    public function testExecuteTwice()
    {
        $mockWebservice = $this
            ->getMockBuilder('\TestApp\Webservice\StaticWebservice')
            ->onlyMethods([
                'execute',
            ])
            ->getMock();

        $mockWebservice->expects($this->once())
            ->method('execute')
            ->willReturn(new ResultSet([
                new Resource([
                    'id' => 1,
                    'title' => 'Hello World',
                ]),
                new Resource([
                    'id' => 2,
                    'title' => 'New ORM',
                ]),
                new Resource([
                    'id' => 3,
                    'title' => 'Webservices',
                ]),
            ], 3));

        $this->query
            ->setWebservice($mockWebservice)
            ->action(Query::ACTION_READ);

        $this->query->execute();

        // This webservice shouldn't be called a second time
        $this->query->execute();
    }

    public function testDebugInfo()
    {
        $this->assertEquals([
            '(help)' => 'This is a Query object, to get the results execute or iterate it.',
            'action' => null,
            'formatters' => [],
            'offset' => null,
            'page' => null,
            'limit' => null,
            'set' => [],
            'sort' => [],
            'extraOptions' => [],
            'conditions' => [],
            'repository' => new Endpoint(),
            'webservice' => new StaticWebservice(),
        ], $this->query->__debugInfo());
    }

    public function testJsonSerialize()
    {
        $expected = [
            ['id' => 1, 'title' => 'Hello World'],
            ['id' => 2, 'title' => 'New ORM'],
            ['id' => 3, 'title' => 'Webservices'],
        ];

        $this->assertEquals(json_encode($expected), json_encode($this->query));
    }

    public function testAndWhere()
    {
        $conditions = [
            'foo' => 'bar',
            'baz' => 2,
        ];
        $this->query->andWhere($conditions);

        $this->assertSame($conditions, $this->query->clause('where'));
    }

    public function testSelectWithArrayMerging()
    {
        $this->query->select(['id', 'name', 'title', 'description']);
        $this->assertSame(['id', 'name', 'title', 'description'], $this->query->clause('select'));

        $this->query->select(['published']);
        $this->assertSame(['id', 'name', 'title', 'description', 'published'], $this->query->clause('select'));
    }

    public function testSelectWithArrayOverwrite()
    {
        $firstFields = ['id', 'first_name', 'last_name', 'date_of_birth'];
        $this->query->select($firstFields);
        $this->assertSame($firstFields, $this->query->clause('select'));

        $secondFields = ['id', 'username', 'email'];
        $this->query->select($secondFields, true);
        $this->assertSame($secondFields, $this->query->clause('select'));
    }

    public function testSelectWithString()
    {
        $field = 'examples';
        $this->query->select($field);

        $this->assertSame([$field], $this->query->clause('select'));
    }

    public function testSelectWithExpression()
    {
        $exp = new ComparisonExpression('upvotes', 50, 'integer', '>=');
        $this->query->select($exp);

        /** @var ComparisonExpression $comparisonClause */
        $comparisonClause = $this->query->clause('select')[0];

        $this->assertInstanceOf(ComparisonExpression::class, $comparisonClause);
        $this->assertEquals(50, $comparisonClause->getValue());
        $this->assertEquals('>=', $comparisonClause->getOperator());
    }

    public function testSelectWithCallable()
    {
        $fields = ['id', 'username', 'email', 'biography'];

        $callable = function () use ($fields) {
            return $fields;
        };
        $this->query->select($callable);

        $this->assertSame($fields, $this->query->clause('select'));
    }

    /**
     * @inheritDoc
     */
    public function tearDown(): void
    {
        parent::tearDown();

        $this->query = null;
    }
}
