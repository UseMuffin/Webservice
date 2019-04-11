<?php

namespace Muffin\Webservice\Routing\Filter;

use Cake\Event\Event;
use Cake\Routing\DispatcherFilter;

/**
 * This filter registers the endpoint model type in controllers
 *
 * @package Muffin\Webservice\Routing\Filter
 * @deprecated 2.0.0 Dispatch filters are deprecated
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
     * Method called before the controller is instantiated and called to serve a request.
     * If used with default priority, it will be called after the Router has parsed the
     * URL and set the routing params into the request object.
     *
     * If a Cake\Http\Response object instance is returned, it will be served at the end of the
     * event cycle, not calling any controller as a result. This will also have the effect of
     * not calling the after event in the dispatcher.
     *
     * If false is returned, the event will be stopped and no more listeners will be notified.
     * Alternatively you can call `$event->stopPropagation()` to achieve the same result.
     *
     * @param \Cake\Event\Event $event container object having the `request`, `response` and `additionalParams`
     *    keys in the data property.
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
