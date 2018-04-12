<?php

namespace Muffin\Webservice\Test\TestCase;

use Cake\Controller\Controller;
use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\Datasource\FactoryLocator;
use Cake\TestSuite\TestCase;
use Muffin\Webservice\Connection;
use Muffin\Webservice\Test\test_app\Model\Endpoint\TestEndpoint;

/**
 * @package MuffinWebservice
 * @author David Yell <dyell@ukwebmedia.com>
 * @copyright UK Web Media Ltd
 */
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
        Plugin::load('Muffin/Webservice', [
            'path' => ROOT . 'src',
            'bootstrap' => true
        ]);

        $connection = new Connection([
            'name' => 'test',
            'service' => 'Test'
        ]);
        ConnectionManager::setConfig('test_app', $connection);

        $controller = new Controller();
        $endpoint = $controller->loadModel('Test', 'Endpoint');

        $this->assertInstanceOf(TestEndpoint::class, $endpoint);
        $this->assertEquals('Test', $endpoint->getAlias());
    }

    public function testFactoryLocatorAddition()
    {
        $expected = [\Muffin\Webservice\Model\EndpointRegistry::class, 'get'];
        $result = FactoryLocator::get('Endpoint');

        $this->assertSame($expected, $result);
    }
}
