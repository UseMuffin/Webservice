<?php
namespace Muffin\Webservice\Test\TestCase\Model\Endpoint\Schema;

use Muffin\Webservice\Test\test_app\Model\Endpoint\Schema\TestSchema;

class SchemaTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Muffin\Webservice\Schema
     */
    private $schema;

    protected function setUp()
    {
        $this->schema = new TestSchema('test');
    }

    public function testName()
    {
        $this->assertEquals($this->schema->name(), 'test');
    }

    public function testAddColumn()
    {
        $updatedSchema = $this->schema->addColumn('example', ['type' => 'string']);
        $this->assertContains('example', $updatedSchema->columns());
    }

    public function testAddColumnWithStringAttributes()
    {
        $updatedSchema = $this->schema->addColumn('example', 'string');
        $this->assertContains('example', $updatedSchema->columns());
    }

    public function testColumns()
    {
        $this->assertEquals(['id', 'title', 'body'], $this->schema->columns());
    }

    public function testColumn()
    {
        $this->assertEquals(
            [
                'type' => 'int',
                'length' => null,
                'precision' => null,
                'null' => null,
                'default' => null,
                'comment' => null,
                'primaryKey' => null
            ],
            $this->schema->column('id')
        );
    }

    public function testColumnType()
    {
        $this->assertEquals('int', $this->schema->columnType('id'));
    }

    public function testMissingColumnType()
    {
        $this->assertNull($this->schema->columnType('missing'));
    }

    public function testChangingColumnType()
    {
        $this->assertEquals('string', $this->schema->columnType('body'));
        $this->schema->columnType('body', 'text');
        $this->assertEquals('text', $this->schema->columnType('body'));
    }

    public function testBaseColumnType()
    {
        $this->schema->addColumn('example', ['type' => 'text', 'baseType' => 'string']);

        $this->assertEquals('int', $this->schema->baseColumnType('id'));
        $this->assertEquals('string', $this->schema->baseColumnType('example'));
    }

    public function testBaseColumnTypeWithNullType()
    {
        $this->schema->addColumn('example', ['type' => null]);
        $this->assertNull($this->schema->baseColumnType('examples'));
    }

    public function testBaseColumnTypeFromTypeMap()
    {
        $this->schema->addColumn('example', 'text');
        $this->assertEquals('text', $this->schema->baseColumnType('example'));
    }

    public function testTypeMap()
    {
        $this->assertEquals(
            ['id' => 'int', 'title' => 'string', 'body' => 'string'],
            $this->schema->typeMap()
        );
    }

    public function testIsNullable()
    {
        $this->schema->addColumn('nullable_column', ['type' => 'tinyint', 'null' => true]);
        $this->schema->addColumn('not_nullable_column', ['type' => 'tinyint', 'null' => false]);

        $this->assertTrue($this->schema->isNullable('nullable_column'));
        $this->assertFalse($this->schema->isNullable('not_nullable_column'));
    }

    public function testIsNullableWithMissingColumn()
    {
        $this->assertTrue($this->schema->isNullable('missing'));
    }

    public function testDefaultValues()
    {
        $this->assertEquals([], $this->schema->defaultValues());

        $this->schema->addColumn('example', ['type' => 'string', 'default' => 'test']);
        $this->assertEquals(['example' => 'test'], $this->schema->defaultValues());
    }

    public function testPrimaryKey()
    {
        $this->schema->addColumn('id', ['type' => 'integer', 'primaryKey' => true]);
        $this->assertEquals(['id'], $this->schema->primaryKey());
    }

    public function testOptions()
    {
        $this->assertEmpty($this->schema->options());
        $this->schema->options(['example' => true]);
        $this->assertEquals(['example' => true], $this->schema->options());
    }
}
