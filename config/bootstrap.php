<?php

use Cake\Event\EventManager;

EventManager::instance()->on(
    'Dispatcher.beforeDispatch',
    ['priority' => 51],
    function ($event) {
        $controller = false;
        if (isset($event->data['controller'])) {
            $controller = $event->data['controller'];
        }
        if ($controller) {
            $callback = ['Muffin\Webservice\Model\EndpointRegistry', 'get'];
            $controller->modelFactory('Endpoint', $callback);
        }
    }
);
