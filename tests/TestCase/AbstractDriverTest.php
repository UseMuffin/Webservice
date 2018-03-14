<?php
namespace Muffin\Webservice\Test\TestCase\Model;

use Cake\TestSuite\TestCase;
use SomeVendor\SomePlugin\Webservice\Driver\SomePlugin;
use TestPlugin\Webservice\Driver\TestPlugin;

class AbstractDriverTest extends TestCase
{
    public function testWebserviceWithoutVendor()
    {
        $driver = new TestPlugin;

        $webservice = $driver->getWebservice('test_plugin');
        $this->assertInstanceOf('TestPlugin\Webservice\TestPluginWebservice', $webservice);

        $webservice = $driver->getWebservice('foo');
        $this->assertInstanceOf('TestPlugin\Webservice\TestPluginWebservice', $webservice);
    }

    public function testWebserviceWithVendor()
    {
        $driver = new SomePlugin;

        $webservice = $driver->getWebservice('some_plugin');
        $this->assertInstanceOf('SomeVendor\SomePlugin\Webservice\SomePluginWebservice', $webservice);

        $webservice = $driver->getWebservice('foo');
        $this->assertInstanceOf('SomeVendor\SomePlugin\Webservice\SomePluginWebservice', $webservice);
    }
}
