<?php
declare(strict_types=1);

namespace Muffin\Webservice\Test\TestCase;

use Cake\TestSuite\TestCase;
use Muffin\Webservice\Datasource\ResultSet;
use Muffin\Webservice\Model\Resource;

class ResultSetTest extends TestCase
{
    /**
     * @var ResultSet|null
     */
    public ?ResultSet $resultSet;

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->resultSet = new ResultSet([
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
        ], 6);
    }

    public function testCount()
    {
        $this->assertEquals(3, $this->resultSet->count());
    }

    public function testTotal()
    {
        $this->assertEquals(6, $this->resultSet->total());
    }

    public function testSerialize()
    {
        $this->assertIsString(serialize($this->resultSet));
    }

    public function testUnserialize()
    {
        $unserialized = unserialize(serialize($this->resultSet));

        $this->assertInstanceOf(ResultSet::class, $unserialized);
    }

    /**
     * @inheritDoc
     */
    public function tearDown(): void
    {
        parent::tearDown();

        $this->resultSet = null;
    }
}
