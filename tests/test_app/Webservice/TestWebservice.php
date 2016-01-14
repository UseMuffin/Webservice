<?php

namespace Muffin\Webservice\Test\test_app\Webservice;

use Muffin\Webservice\Webservice\Webservice;

class TestWebservice extends Webservice
{

    public function createResource($resourceClass, array $properties = [])
    {
        return $this->_createResource($resourceClass, $properties);
    }

    public function transformResults(array $results, $resourceClass)
    {
        return $this->_transformResults($results, $resourceClass);
    }
}
