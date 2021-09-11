<?php
declare(strict_types=1);

namespace TestApp\Webservice;

use Muffin\Webservice\Model\Endpoint;
use Muffin\Webservice\Webservice\Webservice;

class TestWebservice extends Webservice
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
