<?php
declare(strict_types=1);

namespace Muffin\Webservice\Test\TestCase\Model;

use Cake\TestSuite\TestCase;
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
        $resource = new Resource([], [
            'source' => 'TestEndPoint',
        ]);
        $this->assertEquals('TestEndPoint', $resource->getSource());
    }

    public function testConstructUseSettersOff()
    {
        $resource = new Resource([
            'field' => 'text',
        ], [
            'markClean' => true,
            'useSetters' => false,
        ]);

        $this->assertEquals('text', $resource->get('field'));
    }
}
