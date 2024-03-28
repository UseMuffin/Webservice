<?php
declare(strict_types=1);

namespace TestPlugin\Webservice;

use Muffin\Webservice\Model\Endpoint;
use Muffin\Webservice\Model\Resource;
use Muffin\Webservice\Webservice\Webservice;

class TestPluginWebservice extends Webservice
{
    public function createResource($resourceClass, array $properties = []): Resource
    {
        return $this->_createResource($resourceClass, $properties);
    }

    /**
     * @return Resource[]
     */
    public function transformResults(Endpoint $endpoint, array $results): array
    {
        return $this->_transformResults($endpoint, $results);
    }
}
