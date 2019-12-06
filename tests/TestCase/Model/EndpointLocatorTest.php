<?php
namespace Muffin\Webservice\Model;

use Cake\TestSuite\TestCase;
use RuntimeException;

class EndpointLocatorTest extends TestCase
{
    /**
     * @var \Muffin\Webservice\Model\EndpointLocator
     */
    private $Locator;

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

    public function testConfig()
    {
        $this->assertSame([], $this->Locator->getConfig());

        $configTest = ['foo' => 'bar'];
        $this->Locator->setConfig('test', $configTest);
        $this->assertSame($configTest, $this->Locator->getConfig('test'));

        $configExample = ['example' => true];
        $this->Locator->setConfig('example', $configExample);
        $this->assertSame($configExample, $this->Locator->getConfig('example'));
    }

    public function testSetConfigForExistingObject()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('You cannot configure "Test", it has already been constructed.');

        $this->Locator->get('Test', [
            'registryAlias' => 'Test',
            'connection' => 'test'
        ]);

        $this->Locator->setConfig('Test', ['foo' => 'bar']);
    }

    public function testSetConfigUsingAliasArray()
    {
        $multiAliasConfig = [
            'Test' => ['foo' => 'bar'],
            'Example' => ['foo' => 'bar']
        ];

        $result = $this->Locator->setConfig($multiAliasConfig);
        $this->assertInstanceOf(EndpointLocator::class, $result);

        $this->assertSame($multiAliasConfig, $this->Locator->getConfig());
    }

    public function testRemoveUsingExists()
    {
        /** @var \PHPUnit\Framework\MockObject\MockObject|\Muffin\Webservice\Model\Endpoint $first */
        $first = $this->getMockBuilder(Endpoint::class)
            ->setConstructorArgs([['alias' => 'First']])
            ->setMethods(['getAlias'])
            ->getMock();
        $first->expects($this->any())
            ->method('getAlias')
            ->willReturn('First');

        /** @var \PHPUnit\Framework\MockObject\MockObject|\Muffin\Webservice\Model\Endpoint $first */
        $second = $this->getMockBuilder(Endpoint::class)
            ->setConstructorArgs([['alias' => 'Second']])
            ->setMethods(['getAlias'])
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
            ->setMethods(['getAlias'])
            ->getMock();
        $first->expects($this->any())
            ->method('getAlias')
            ->willReturn('First');

        $this->Locator->set($first->getAlias(), $first);

        $result = $this->Locator->get($first->getAlias());
        $this->assertSame($first, $result);
    }

    public function testGetWithExistingObject()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('You cannot configure "First", it already exists in the locator.');

        $result = $this->Locator->get('First', [
            'className' => Endpoint::class,
            'registryAlias' => 'First',
            'connection' => 'test'
        ]);
        $this->assertInstanceOf(Endpoint::class, $result);

        $this->Locator->get('First', ['registryAlias' => 'NotFirst']);
    }

    public function testGetCreateInstance()
    {
        $result = $this->Locator->get('Test', [
            'registryAlias' => 'Test',
            'connection' => 'test'
        ]);

        $this->assertInstanceOf(Endpoint::class, $result);
    }

    public function testGetInstanceWithNonExistentClass()
    {
        $result = $this->Locator->get('Test', [
            'connection' => 'test',
            'className' => 'UnfindableClass'
        ]);

        $this->assertInstanceOf(Endpoint::class, $result);
        $this->assertEquals('unfindable_class', $result->getName());
    }

    public function testClear()
    {
        /** @var \Muffin\Webservice\Model\Endpoint $first */
        $first = $this->getMockBuilder(Endpoint::class)
            ->setConstructorArgs([['alias' => 'First']])
            ->setMethods(['getAlias'])
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
