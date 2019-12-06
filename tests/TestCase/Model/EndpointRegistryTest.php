<?php

namespace Muffin\Webservice\Model;

use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use Muffin\Webservice\Connection;
use Muffin\Webservice\Test\test_app\Model\Endpoint\TestEndpoint;

class EndpointRegistryTest extends TestCase
{
    public function setUp(): void
    {
        EndpointRegistry::clear();
    }

    /**
     * Test to ensure that an endpoint can be added/retrieved from the registry
     *
     * @return void
     */
    public function testAddingEndpoint()
    {
        $result = EndpointRegistry::get('Test', [
            'connection' => new Connection([
                'name' => 'test',
                'service' => 'test'
            ])
        ]);

        $this->assertInstanceOf(Endpoint::class, $result);
        $this->assertEquals('test', $result->endpoint());
    }

    /**
     * Ensure that if you try and set the options for an already configured Endpoint instance an
     * exception is thrown
     *
     * @expectedException \RuntimeException
     * @expectedExceptionMessage You cannot configure "Test", it already exists in the registry.
     */
    public function testReconfiguringExistingInstance()
    {
        $result = EndpointRegistry::get('Test', [
            'connection' => new Connection([
                'name' => 'test',
                'service' => 'test'
            ]),
            'displayField' => 'foo'
        ]);

        $this->assertInstanceOf(Endpoint::class, $result);
        $this->assertEquals('test', $result->endpoint());

        $result = EndpointRegistry::get('Test', [
            'displayField' => 'foo'
        ]);
    }

    public function testGettingSameInstance()
    {
        $result = EndpointRegistry::get('Test', [
            'connection' => new Connection([
                'name' => 'test',
                'service' => 'test'
            ]),
        ]);

        $this->assertInstanceOf(Endpoint::class, $result);
        $this->assertEquals('test', $result->endpoint());

        $result = EndpointRegistry::get('Test');

        $this->assertInstanceOf(Endpoint::class, $result);
        $this->assertEquals('test', $result->endpoint());
    }

    public function testGetInstanceWithNoEndpointName()
    {
        $result = EndpointRegistry::get('Test', [
            'connection' => new Connection([
                'name' => 'test',
                'service' => 'test'
            ]),
            'className' => 'UnfindableClass'
        ]);

        $this->assertInstanceOf(Endpoint::class, $result);
        $this->assertEquals('unfindable_class', $result->endpoint());
    }

    public function testRemovingInstance()
    {
        $result = EndpointRegistry::get('Test', [
            'connection' => new Connection([
                'name' => 'test',
                'service' => 'test'
            ])
        ]);

        $this->assertInstanceOf(Endpoint::class, $result);

        EndpointRegistry::remove('Test');

        $ref = new \ReflectionClass(EndpointRegistry::class);
        $this->assertEmpty($ref->getStaticProperties()['_instances']);
        $this->assertEmpty($ref->getStaticProperties()['_options']);
    }

    public function testClearing()
    {
        $result = EndpointRegistry::get('Test', [
            'connection' => new Connection([
                'name' => 'test',
                'service' => 'test'
            ])
        ]);

        $this->assertInstanceOf(Endpoint::class, $result);

        EndpointRegistry::clear();

        $ref = new \ReflectionClass(EndpointRegistry::class);
        $this->assertEmpty($ref->getStaticProperties()['_instances']);
        $this->assertEmpty($ref->getStaticProperties()['_options']);
    }
}
