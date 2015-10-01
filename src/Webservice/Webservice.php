<?php

namespace Muffin\Webservice\Webservice;

use Cake\Datasource\ConnectionInterface;
use Cake\Utility\Text;
use Muffin\Webservice\AbstractDriver;
use Muffin\Webservice\Exception\UnimplementedWebserviceMethodException;
use Muffin\Webservice\WebserviceQuery;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

abstract class Webservice implements WebserviceInterface
{

    protected $_driver;
    protected $_endpoint;
    protected $_nestedResources = [];

    public function __construct(array $config = [])
    {
        if (!empty($config['driver'])) {
            $this->driver($config['driver']);
        }
        if (!empty($config['endpoint'])) {
            $this->endpoint($config['endpoint']);
        }

        $this->initialize();
    }

    public function initialize()
    {

    }

    /**
     * @param AbstractDriver|null $driver
     * @return AbstractDriver|$this
     */
    public function driver(AbstractDriver $driver = null)
    {
        if ($driver === null) {
            return $this->_driver;
        }

        $this->_driver = $driver;

        return $this;
    }

    /**
     * @param string|null $endpoint
     * @return string|$this
     */
    public function endpoint($endpoint = null)
    {
        if ($endpoint === null) {
            return $this->_endpoint;
        }

        $this->_endpoint = $endpoint;

        return $this;
    }

    public function addNestedResource($url, array $requiredFields)
    {
        $this->_nestedResources[$url] = [
            'requiredFields' => $requiredFields
        ];
    }

    public function nestedResource(array $conditions)
    {
        foreach ($this->_nestedResources as $url => $options) {
            if (count(array_intersect_key(array_flip($options['requiredFields']), $conditions)) !== count($options['requiredFields'])) {
                continue;
            }

            return Text::insert($url, $conditions);
        }

        return false;
    }

    public function execute(WebserviceQuery $query, array $options = [])
    {
        $result = $this->_executeQuery($query, $options);

        if ($this->driver()->logger()) {
            $this->_logQuery($query, $this->driver()->logger());
        }

        return $result;
    }

    protected function _executeQuery(WebserviceQuery $query, array $options = [])
    {
        switch ($query->action()) {
            case WebserviceQuery::ACTION_CREATE:
                return $this->_executeCreateQuery($query, $options);
            case WebserviceQuery::ACTION_READ:
                return $this->_executeReadQuery($query, $options);
            case WebserviceQuery::ACTION_UPDATE:
                return $this->_executeUpdateQuery($query, $options);
            case WebserviceQuery::ACTION_DELETE:
                return $this->_executeDeleteQuery($query, $options);
        }
    }

    protected function _executeCreateQuery(WebserviceQuery $query, array $options = [])
    {
        throw new UnimplementedWebserviceMethodException([
            'name' => get_class($this),
            'method' => '_executeCreateQuery'
        ]);
    }

    protected function _executeReadQuery(WebserviceQuery $query, array $options = [])
    {
        throw new UnimplementedWebserviceMethodException([
            'name' => get_class($this),
            'method' => '_executeReadQuery'
        ]);
    }

    protected function _executeUpdateQuery(WebserviceQuery $query, array $options = [])
    {
        throw new UnimplementedWebserviceMethodException([
            'name' => get_class($this),
            'method' => '_executeUpdateQuery'
        ]);
    }

    protected function _executeDeleteQuery(WebserviceQuery $query, array $options = [])
    {
        throw new UnimplementedWebserviceMethodException([
            'name' => get_class($this),
            'method' => '_executeDeleteQuery'
        ]);
    }

    /**
     * @param string $resourceClass
     * @param array $properties
     * @return \Muffin\Webservice\Model\Resource
     */
    protected function _createResource($resourceClass, array $properties = [])
    {
        return new $resourceClass($properties, [
            'markClean' => true,
            'markNew' => false,
        ]);
    }

    protected function _logQuery(WebserviceQuery $query, LoggerInterface $logger)
    {
        if (!$this->driver()->logQueries()) {
            return;
        }

        $logger->debug($query->endpoint(), [
            'params' => $query->conditions()
        ]);
    }

    /**
     * Loops through the results and turns them into resource objects
     *
     * @param array $results Array of results from the API
     * @param string $resourceClass The resource class to use
     *
     * @return array Array of resource objects
     */
    protected function _transformResults(array $results, $resourceClass)
    {
        $resources = [];
        foreach ($results as $result) {
            $resources[] = $this->_transformResource($result, $resourceClass);
        }

        return $resources;
    }

    /**
     * Turns a single result into a resource
     *
     * @param array $result
     * @param string $resourceClass
     * @return \Muffin\Webservice\Model\Resource
     */
    protected function _transformResource(array $result, $resourceClass)
    {
        $properties = [];

        foreach ($result as $property => $value) {
            $properties[$property] = $value;
        }

        return $this->_createResource($resourceClass, $properties);
    }

    public function __debugInfo()
    {
        return [
            'driver' => $this->driver(),
            'endpoint' => $this->endpoint(),
        ];
    }
}
