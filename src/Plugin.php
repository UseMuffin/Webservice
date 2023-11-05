<?php
declare(strict_types=1);

namespace Muffin\Webservice;

use Cake\Core\BasePlugin;
use Cake\Core\PluginApplicationInterface;
use Cake\Datasource\FactoryLocator;
use Muffin\Webservice\Model\EndpointLocator;

class Plugin extends BasePlugin
{
    /**
     * Disable routes hook.
     *
     * @var bool
     */
    protected bool $routesEnabled = false;

    /**
     * Disable middleware hook.
     *
     * @var bool
     */
    protected bool $middlewareEnabled = false;

    /**
     * Disable console hook.
     *
     * @var bool
     */
    protected bool $consoleEnabled = false;

    /**
     * @inheritDoc
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        FactoryLocator::add('Endpoint', new EndpointLocator());
    }
}
