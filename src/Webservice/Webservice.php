<?php

namespace Muffin\Webservice\Webservice;

use Cake\Core\App;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionInterface;
use Cake\Utility\Inflector;
use Cake\Utility\Text;
use Muffin\Webservice\AbstractDriver;
use Muffin\Webservice\Exception\MissingEndpointSchemaException;
use Muffin\Webservice\Exception\UnimplementedWebserviceMethodException;
use Muffin\Webservice\Model\Endpoint;
use Muffin\Webservice\Query;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

/**
 * Basic implementation of a webservice
 *
 * @package Muffin\Webservice\Webservice
 */
abstract class Webservice implements WebserviceInterface
{

    /**
     * The driver to use to communicate with the webservice
     *
     * @var \Muffin\Webservice\AbstractDriver
     */
    protected $_driver;

    /**
     * The webservice to call
     *
     * @var string
     */
    protected $_endpoint;

    /**
     * A list of nested resources with their path and needed conditions
     *
     * @var array
     */
    protected $_nestedResources = [];

    /**
     * Construct the webservice
     *
     * @param array $config The config to use
     */
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

    /**
     * Initialize the webservice
     *
     * @return void
     */
    public function initialize()
    {
    }

    /**
     * Set the driver to use
     *
     * @param \Muffin\Webservice\AbstractDriver|null $driver The driver to use
     *
     * @return \Muffin\Webservice\AbstractDriver|$this
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
     * Set the endpoint path to use
     *
     * @param string|null $endpoint The endpoint
     *
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

    /**
     * Add a nested resource
     *
     * @param string $url The URL to use as base
     * @param array $requiredFields The required fields
     *
     * @return void
     */
    public function addNestedResource($url, array $requiredFields)
    {
        $this->_nestedResources[$url] = [
            'requiredFields' => $requiredFields
        ];
    }

    /**
     * Checks if a set of conditions match a nested resource
     *
     * @param array $conditions The conditions in a query
     *
     * @return bool|string Either a URL or false in case no nested resource matched
     */
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

    /**
     * {@inheritDoc}
     */
    public function execute(Query $query, array $options = [])
    {
        $result = $this->_executeQuery($query, $options);

        if ($this->driver() === null) {
            throw new \UnexpectedValueException(__('No driver has been defined'));
        }

        // Write to the logger when one has been defined
        if ($this->driver()->logger()) {
            $this->_logQuery($query, $this->driver()->logger());
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function describe($endpoint)
    {
        $shortName = App::shortName(get_class($this), 'Webservice', 'Webservice');
        list($plugin, $name) = pluginSplit($shortName);

        $endpoint = Inflector::classify(str_replace('-', '_', $endpoint));
        $schemaShortName = implode('.', array_filter([$plugin, $endpoint]));
        $schemaClassName = App::className($schemaShortName, 'Model/Endpoint/Schema', 'Schema');
        if ($schemaClassName) {
            return new $schemaClassName($endpoint);
        }

        throw new MissingEndpointSchemaException([
            'schema' => $schemaShortName,
            'webservice' => $shortName
        ]);
    }

    /**
     * Execute the appropriate method for a query
     *
     * @param \Muffin\Webservice\Query $query The query to execute
     * @param array $options The options to use
     *
     * @return bool|int|\Muffin\Webservice\ResultSet
     */
    protected function _executeQuery(Query $query, array $options = [])
    {
        switch ($query->action()) {
            case Query::ACTION_CREATE:
                return $this->_executeCreateQuery($query, $options);
            case Query::ACTION_READ:
                return $this->_executeReadQuery($query, $options);
            case Query::ACTION_UPDATE:
                return $this->_executeUpdateQuery($query, $options);
            case Query::ACTION_DELETE:
                return $this->_executeDeleteQuery($query, $options);
        }

        return false;
    }

    /**
     * Executes a query with the create action
     *
     * @param \Muffin\Webservice\Query $query The query to execute
     * @param array $options The options to use
     *
     * @return bool|void
     */
    protected function _executeCreateQuery(Query $query, array $options = [])
    {
        throw new UnimplementedWebserviceMethodException([
            'name' => get_class($this),
            'method' => '_executeCreateQuery'
        ]);
    }

    /**
     * Executes a query with the read action
     *
     * @param \Muffin\Webservice\Query $query The query to execute
     * @param array $options The options to use
     *
     * @return \Muffin\Webservice\ResultSet|bool|void
     */
    protected function _executeReadQuery(Query $query, array $options = [])
    {
        throw new UnimplementedWebserviceMethodException([
            'name' => get_class($this),
            'method' => '_executeReadQuery'
        ]);
    }

    /**
     * Executes a query with the update action
     *
     * @param \Muffin\Webservice\Query $query The query to execute
     * @param array $options The options to use
     *
     * @return int|bool|void
     */
    protected function _executeUpdateQuery(Query $query, array $options = [])
    {
        throw new UnimplementedWebserviceMethodException([
            'name' => get_class($this),
            'method' => '_executeUpdateQuery'
        ]);
    }

    /**
     * Executes a query with the delete action
     *
     * @param \Muffin\Webservice\Query $query The query to execute
     * @param array $options The options to use
     *
     * @return int|bool|void
     */
    protected function _executeDeleteQuery(Query $query, array $options = [])
    {
        throw new UnimplementedWebserviceMethodException([
            'name' => get_class($this),
            'method' => '_executeDeleteQuery'
        ]);
    }

    /**
     * Creates a resource with the given class and properties
     *
     * @param string $resourceClass The class to use to create the resource
     * @param array $properties The properties to apply
     *
     * @return \Muffin\Webservice\Model\Resource
     */
    protected function _createResource($resourceClass, array $properties = [])
    {
        return new $resourceClass($properties, [
            'markClean' => true,
            'markNew' => false,
        ]);
    }

    /**
     * Logs a query to the specified logger
     *
     * @param \Muffin\Webservice\Query $query The query to log
     * @param \Psr\Log\LoggerInterface $logger The logger instance to use
     *
     * @return void
     */
    protected function _logQuery(Query $query, LoggerInterface $logger)
    {
        if (!$this->driver()->logQueries()) {
            return;
        }

        $logger->debug($query->endpoint(), [
            'params' => $query->where()
        ]);
    }

    /**
     * Loops through the results and turns them into resource objects
     *
     * @param \Muffin\Webservice\Model\Endpoint $endpoint The endpoint class to use
     * @param array $results Array of results from the API
     *
     * @return \Muffin\Webservice\Model\Resource[] Array of resource objects
     */
    protected function _transformResults(Endpoint $endpoint, array $results)
    {
        $resources = [];
        foreach ($results as $result) {
            $resources[] = $this->_transformResource($endpoint, $result);
        }

        return $resources;
    }

    /**
     * Turns a single result into a resource
     *
     * @param \Muffin\Webservice\Model\Endpoint $endpoint The endpoint class to use
     * @param array $result The API result
     *
     * @return \Muffin\Webservice\Model\Resource
     */
    protected function _transformResource(Endpoint $endpoint, array $result)
    {
        $properties = [];

        foreach ($result as $property => $value) {
            $properties[$property] = $value;
        }

        return $this->_createResource($endpoint->resourceClass(), $properties);
    }

    /**
     * Creates a handy representation of the webservice
     *
     * @return array
     */
    public function __debugInfo()
    {
        return [
            'driver' => $this->driver(),
            'endpoint' => $this->endpoint(),
        ];
    }
}
