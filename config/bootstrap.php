<?php
\Cake\Datasource\FactoryLocator::add('Endpoint', [Muffin\Webservice\Model\EndpointRegistry::class, 'get']);
