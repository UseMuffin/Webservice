<?php
declare(strict_types=1);

namespace Muffin\Webservice\Test\TestCase;

use Cake\Http\Client;
use Cake\TestSuite\TestCase;
use SomeVendor\SomePlugin\Webservice\Driver\SomePlugin;
use StdClass;
use TestApp\Webservice\Driver\TestDriver;
use TestApp\Webservice\Logger;
use TestApp\Webservice\TestWebservice;
use TestPlugin\Webservice\Driver\TestPlugin;

class AbstractDriverTest extends TestCase
{
    public function testWebserviceWithoutVendor()
    {
        $driver = new TestPlugin();

        $webservice = $driver->getWebservice('test_plugin');
        $this->assertInstanceOf('TestPlugin\Webservice\TestPluginWebservice', $webservice);

        $webservice = $driver->getWebservice('foo');
        $this->assertInstanceOf('TestPlugin\Webservice\TestPluginWebservice', $webservice);
    }

    public function testWebserviceWithVendor()
    {
        $driver = new SomePlugin();

        $webservice = $driver->getWebservice('some_plugin');
        $this->assertInstanceOf('SomeVendor\SomePlugin\Webservice\SomePluginWebservice', $webservice);

        $webservice = $driver->getWebservice('foo');
        $this->assertInstanceOf('SomeVendor\SomePlugin\Webservice\SomePluginWebservice', $webservice);
    }

    public function testSetClient()
    {
        $client = new StdClass();

        $driver = new TestDriver();
        $driver->setClient($client);

        $this->assertSame($client, $driver->getClient());
    }

    public function testEnableQueryLogging()
    {
        $driver = new TestDriver();
        $driver->enableQueryLogging();

        $this->assertTrue($driver->isQueryLoggingEnabled());
    }

    public function testDisableQueryLogging()
    {
        $driver = new TestDriver();
        $driver->disableQueryLogging();

        $this->assertFalse($driver->isQueryLoggingEnabled());
    }

    public function testDebugInfo()
    {
        $client = new Client();
        $logger = new Logger();

        $expected = [
            'client' => $client,
            'logger' => $logger,
            'query_logging' => true,
            'webservices' => ['example'],
        ];

        $driver = new TestDriver();
        $driver->setLogger($logger);
        $driver
            ->setClient($client)
            ->setWebservice('example', new TestWebservice())
            ->enableQueryLogging();

        $this->assertEquals($expected, $driver->__debugInfo());
    }
}
