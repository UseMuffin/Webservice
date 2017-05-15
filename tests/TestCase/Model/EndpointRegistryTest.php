<?php

namespace Muffin\Webservice\Model;

use Cake\TestSuite\TestCase;
use Muffin\Webservice\Connection;

class EndpointRegistryTest extends TestCase
{
    public function tearDown()
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

        $this->assertInstanceOf(\Muffin\Webservice\Model\Endpoint::class, $result);
    }

    /**
     * Ensure that if you try and set the options for an already configured Endpoint instance an
     * exception is thrown
     *
     * @expectedException \RuntimeException
     */
    public function testAddingSameEndpoint()
    {
        $result = EndpointRegistry::get('Test', [
            'connection' => new Connection([
                'name' => 'test',
                'service' => 'test'
            ])
        ]);

        $this->assertInstanceOf(\Muffin\Webservice\Model\Endpoint::class, $result);

        $result = EndpointRegistry::get('Test', [
            'connection' => new Connection([
                'name' => 'exception',
                'service' => 'exception'
            ])
        ]);
    }

    public function testRemovingInstance()
    {
        $result = EndpointRegistry::get('Test', [
            'connection' => new Connection([
                'name' => 'test',
                'service' => 'test'
            ])
        ]);

        $this->assertInstanceOf(\Muffin\Webservice\Model\Endpoint::class, $result);

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

        $this->assertInstanceOf(\Muffin\Webservice\Model\Endpoint::class, $result);

        EndpointRegistry::clear();

        $ref = new \ReflectionClass(EndpointRegistry::class);
        $this->assertEmpty($ref->getStaticProperties()['_instances']);
        $this->assertEmpty($ref->getStaticProperties()['_options']);
    }
}
