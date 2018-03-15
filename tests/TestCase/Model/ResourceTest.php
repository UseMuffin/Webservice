<?php

namespace Muffin\Webservice\Test\TestCase;

use Cake\TestSuite\TestCase;
use Muffin\Webservice\Model\Endpoint;
use Muffin\Webservice\Model\Resource;

class ResourceTest extends TestCase
{

    public function testBasicConstruct()
    {
        $resource = new Resource([]);

        $this->assertTrue($resource->isNew());
    }

    public function testSoureConstruct()
    {
        $endpoint = new Endpoint();

        $resource = new Resource([], [
            'source' => $endpoint
        ]);
        $this->assertEquals($endpoint, $resource->getSource());
    }

    public function testConstructUseSettersOff()
    {
        $resource = new Resource([
            'field' => 'text'
        ], [
            'markClean' => true,
            'useSetters' => false
        ]);

        $this->assertEquals('text', $resource->get('field'));
    }
}
