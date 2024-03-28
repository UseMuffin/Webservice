<?php
declare(strict_types=1);

namespace Muffin\Webservice\Test\TestCase\Model;

use Cake\Datasource\Exception\MissingDatasourceConfigException;
use Cake\TestSuite\TestCase;
use Muffin\Webservice\Model\Endpoint;
use Muffin\Webservice\Model\EndpointLocator;
use RuntimeException;
use TestApp\Model\Endpoint\TestEndpoint;

class EndpointLocatorTest extends TestCase
{
    /**
     * @var \Muffin\Webservice\Model\EndpointLocator|null
     */
    private ?EndpointLocator $Locator;

    public function setUp(): void
    {
        parent::setUp();

        $this->Locator = new EndpointLocator();
    }

    public function tearDown(): void
    {
        parent::tearDown();

        unset($this->Locator);
    }

    public function testRemoveUsingExists()
    {
        /** @var \PHPUnit\Framework\MockObject\MockObject|\Muffin\Webservice\Model\Endpoint $first */
        $first = $this->getMockBuilder(Endpoint::class)
            ->setConstructorArgs([['alias' => 'First']])
            ->onlyMethods(['getAlias'])
            ->getMock();
        $first->expects($this->any())
            ->method('getAlias')
            ->willReturn('First');

        /** @var \PHPUnit\Framework\MockObject\MockObject|\Muffin\Webservice\Model\Endpoint $second */
        $second = $this->getMockBuilder(Endpoint::class)
            ->setConstructorArgs([['alias' => 'Second']])
            ->onlyMethods(['getAlias'])
            ->getMock();
        $second->expects($this->any())
            ->method('getAlias')
            ->willReturn('Second');

        $this->Locator->set($first->getAlias(), $first);
        $this->Locator->set($second->getAlias(), $second);

        $this->assertTrue($this->Locator->exists($first->getAlias()));
        $this->assertTrue($this->Locator->exists($second->getAlias()));

        $this->Locator->remove($second->getAlias());

        $this->assertTrue($this->Locator->exists($first->getAlias()));
        $this->assertFalse($this->Locator->exists($second->getAlias()));
    }

    public function testGet()
    {
        /** @var \Muffin\Webservice\Model\Endpoint $first */
        $first = $this->getMockBuilder(Endpoint::class)
            ->setConstructorArgs([['alias' => 'First']])
            ->onlyMethods(['getAlias'])
            ->getMock();
        $first->expects($this->any())
            ->method('getAlias')
            ->willReturn('First');

        $this->Locator->set($first->getAlias(), $first);

        $result = $this->Locator->get($first->getAlias());
        $this->assertSame($first, $result);
    }

    public function testGetException()
    {
        $this->expectException(MissingDatasourceConfigException::class);
        $this->expectExceptionMessage(
            'The datasource configuration `non-existent` was not found.'
            . ' You can override Endpoint::defaultConnectionName() to return the connection name you want.'
        );

        $locator = new EndpointLocator();
        $locator->get('Foo', ['className' => TestEndpoint::class, 'connection' => 'non-existent']);
    }

    public function testGetWithExistingObject()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('You cannot configure `First`, it already exists in the registry.');

        $result = $this->Locator->get('First', [
            'className' => Endpoint::class,
            'registryAlias' => 'First',
            'connection' => 'test',
        ]);
        // debug($result);
        $this->assertInstanceOf(Endpoint::class, $result);

        $this->Locator->get('First', ['registryAlias' => 'NotFirst']);
    }

    public function testGetCreateInstance()
    {
        $result = $this->Locator->get('Test', [
            'registryAlias' => 'Test',
            'connection' => 'test',
        ]);

        $this->assertInstanceOf(Endpoint::class, $result);
    }

    public function testGetInstanceWithNonExistentClass()
    {
        $result = $this->Locator->get('Test', [
            'connection' => 'test',
            'className' => 'UnfindableClass',
        ]);

        $this->assertInstanceOf(Endpoint::class, $result);
        $this->assertEquals('unfindable_class', $result->getName());
    }

    public function testClear()
    {
        /** @var \Muffin\Webservice\Model\Endpoint $first */
        $first = $this->getMockBuilder(Endpoint::class)
            ->setConstructorArgs([['alias' => 'First']])
            ->onlyMethods(['getAlias'])
            ->getMock();
        $first->expects($this->any())
            ->method('getAlias')
            ->willReturn('First');

        $this->Locator->set($first->getAlias(), $first);
        $this->assertSame($first, $this->Locator->get($first->getAlias()));

        $this->Locator->clear();

        $this->assertFalse($this->Locator->exists($first->getAlias()));
    }
}
