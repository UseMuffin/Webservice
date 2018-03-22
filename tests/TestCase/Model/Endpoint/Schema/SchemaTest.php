<?php
/**
 * @package MuffinWebservice
 * @author David Yell <dyell@ukwebmedia.com>
 * @copyright UK Web Media Ltd
 */

namespace Muffin\Webservice\Test\TestCase\Model\Endpoint\Schema;

use Muffin\Webservice\Test\test_app\Model\Endpoint\Schema\TestSchema;
use Muffin\Webservice\Test\test_app\Model\Endpoint\TestEndpoint;

class SchemaTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Muffin\Webservice\Schema
     */
    private $schema;

    protected function setUp()
    {
        $this->schema = new TestSchema(new TestEndpoint());
    }

    public function testName()
    {
        $this->assertEquals($this->schema->name()->getName(), 'test');
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

    }

    public function testBaseColumnType()
    {

    }

    public function testTypeMap()
    {

    }

    public function testIsNullable()
    {

    }

    public function testDefaultValues()
    {

    }

    public function testPrimaryKey()
    {

    }

    public function testOptions()
    {

    }
}
