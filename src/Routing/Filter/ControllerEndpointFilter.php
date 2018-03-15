<?php

namespace Muffin\Webservice\Routing\Filter;

use Cake\Event\Event;
use Cake\Routing\DispatcherFilter;

/**
 * This filter registers the endpoint model type in controllers
 *
 * @package Muffin\Webservice\Routing\Filter
 */
class ControllerEndpointFilter extends DispatcherFilter
{

    /**
     * Priority to use
     *
     * @var int
     */
    protected $_priority = 51;

    /**
     * @inheritDoc
     *
     * @param \Cake\Event\Event $event The event to handle
     * @return void
     */
    public function beforeDispatch(Event $event)
    {
        $controller = false;
        if (isset($event->data['controller'])) {
            $controller = $event->data['controller'];
        }
        if ($controller) {
            $callback = ['Muffin\Webservice\Model\EndpointRegistry', 'get'];
            $controller->modelFactory('Endpoint', $callback);
        }
    }
}
