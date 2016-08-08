<?php

use Cake\Core\Configure;
use Cake\Routing\DispatcherFactory;

DispatcherFactory::add('Muffin/Webservice.ControllerEndpoint');

Configure::write(
    'DebugKit.panels',
    array_merge((array)Configure::read('DebugKit.panels'), [
        'Muffin/Webservice.WebserviceQueries',
    ])
);
