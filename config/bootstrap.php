<?php

use Cake\Event\EventManager;
use Muffin\Webservice\View\Form\ResourceContext;

\Cake\Routing\DispatcherFactory::add('Muffin/Webservice.ControllerEndpoint');

EventManager::instance()->on('View.beforeRender', function ($event) {
    $view = $event->subject();
    $view->Form->addContextProvider('webservice', function ($request, $data) {
        if ($data['entity'] instanceof \Muffin\Webservice\Model\Resource) {
            return new ResourceContext($request, $data);
        }
    });
});
