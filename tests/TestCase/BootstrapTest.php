<?php
declare(strict_types=1);

namespace Muffin\Webservice\Test\TestCase;

use Cake\Controller\Controller;
use Cake\Datasource\ConnectionManager;
use Cake\Datasource\FactoryLocator;
use Cake\TestSuite\TestCase;
use Muffin\Webservice\Connection;
use Muffin\Webservice\Model\EndpointLocator;
use TestApp\Model\Endpoint\TestEndpoint;

class BootstrapTest extends TestCase
{
    /**
     * Test that the plugins bootstrap is correctly registering the Endpoint
     * repository type with the factory locator
     *
     * @return void
     */
    public function testLoadingEndpointWithLoadModel()
    {
        $connection = new Connection([
            'name' => 'test',
            'service' => 'Test',
        ]);
        ConnectionManager::setConfig('test_app', $connection);

        $controller = new Controller();
        $endpoint = $controller->loadModel('Test', 'Endpoint');

        $this->assertInstanceOf(TestEndpoint::class, $endpoint);
        $this->assertEquals('Test', $endpoint->getAlias());
    }

    public function testFactoryLocatorAddition()
    {
        $result = FactoryLocator::get('Endpoint');

        $this->assertInstanceOf(EndpointLocator::class, $result[0]);
        $this->assertSame('get', $result[1]);
    }
}
