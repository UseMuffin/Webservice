<?php
use Cake\Datasource\FactoryLocator;
use Muffin\Webservice\Model\EndpointLocator;

FactoryLocator::add('Endpoint', [new EndpointLocator(), 'get']);
