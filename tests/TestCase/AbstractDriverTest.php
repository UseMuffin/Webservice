<?php
declare(strict_types=1);

namespace Muffin\Webservice\Test\TestCase\Model;

use Cake\Http\Client;
use Cake\TestSuite\TestCase;
use Muffin\Webservice\Test\test_app\Webservice\Driver\Test;
use Muffin\Webservice\Test\test_app\Webservice\Logger;
use Muffin\Webservice\Test\test_app\Webservice\TestWebservice;
use SomeVendor\SomePlugin\Webservice\Driver\SomePlugin;
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
        $client = new \StdClass();

        $driver = new Test();
        $driver->setClient($client);

        $this->assertSame($client, $driver->getClient());
    }

    public function testEnableQueryLogging()
    {
        $driver = new Test();
        $driver->enableQueryLogging();

        $this->assertTrue($driver->isQueryLoggingEnabled());
    }

    public function testDisableQueryLogging()
    {
        $driver = new Test();
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

        $driver = new Test();
        $driver->setLogger($logger);
        $driver
            ->setClient($client)
            ->setWebservice('example', new TestWebservice())
            ->enableQueryLogging();

        $this->assertEquals($expected, $driver->__debugInfo());
    }
}
