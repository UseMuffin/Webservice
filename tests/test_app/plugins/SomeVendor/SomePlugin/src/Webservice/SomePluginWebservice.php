<?php
namespace SomeVendor\SomePlugin\Webservice;

use Muffin\Webservice\Model\Endpoint;
use Muffin\Webservice\Webservice\Webservice;

class SomePluginWebservice extends Webservice
{

    public function createResource($resourceClass, array $properties = [])
    {
        return $this->_createResource($resourceClass, $properties);
    }

    public function transformResults(Endpoint $endpoint, array $results)
    {
        return $this->_transformResults($endpoint, $results);
    }
}
