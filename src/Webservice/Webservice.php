<?php
declare(strict_types=1);

namespace Muffin\Webservice\Webservice;

use Cake\Core\App;
use Cake\Utility\Inflector;
use Cake\Utility\Text;
use Muffin\Webservice\Datasource\Query;
use Muffin\Webservice\Datasource\ResultSet;
use Muffin\Webservice\Datasource\Schema;
use Muffin\Webservice\Model\Endpoint;
use Muffin\Webservice\Model\Exception\MissingEndpointSchemaException;
use Muffin\Webservice\Model\Resource;
use Muffin\Webservice\Webservice\Driver\AbstractDriver;
use Muffin\Webservice\Webservice\Exception\UnimplementedWebserviceMethodException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use function Cake\Core\pluginSplit;

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
     * @var \Muffin\Webservice\Webservice\Driver\AbstractDriver
     */
    protected AbstractDriver $_driver;

    /**
     * The webservice to call
     *
     * @var string
     */
    protected ?string $_endpoint = null;

    /**
     * A list of nested resources with their path and needed conditions
     *
     * @var array
     */
    protected array $_nestedResources = [];

    /**
     * Construct the webservice
     *
     * @param array $config The config to use
     */
    public function __construct(array $config = [])
    {
        if (!empty($config['driver'])) {
            $this->setDriver($config['driver']);
        }
        if (!empty($config['endpoint'])) {
            $this->setEndpoint($config['endpoint']);
        }

        $this->initialize();
    }

    /**
     * Initialize the webservice
     *
     * @return void
     */
    public function initialize(): void
    {
    }

    /**
     * Set the webservice driver and return the instance for chaining
     *
     * @param \Muffin\Webservice\Webservice\Driver\AbstractDriver $driver Instance of the driver
     * @return $this
     */
    public function setDriver(AbstractDriver $driver)
    {
        $this->_driver = $driver;

        return $this;
    }

    /**
     * Get this webservices driver
     *
     * @return \Muffin\Webservice\Webservice\Driver\AbstractDriver
     */
    public function getDriver(): AbstractDriver
    {
        if ($this->_driver === null) {
            throw new RuntimeException('No driver has been defined');
        }

        return $this->_driver;
    }

    /**
     * Set the endpoint path this webservice uses
     *
     * @param string $endpoint Endpoint path
     * @return $this
     */
    public function setEndpoint(string $endpoint)
    {
        $this->_endpoint = $endpoint;

        return $this;
    }

    /**
     * Get the endpoint path for this webservice
     *
     * @return string
     */
    public function getEndpoint(): string
    {
        return $this->_endpoint;
    }

    /**
     * Add a nested resource
     *
     * @param string $url The URL to use as base
     * @param array $requiredFields The required fields
     * @return void
     */
    public function addNestedResource(string $url, array $requiredFields): void
    {
        $this->_nestedResources[$url] = [
            'requiredFields' => $requiredFields,
        ];
    }

    /**
     * Checks if a set of conditions match a nested resource
     *
     * @param array $conditions The conditions in a query
     * @return string|null Either a URL or false in case no nested resource matched
     */
    public function nestedResource(array $conditions): ?string
    {
        foreach ($this->_nestedResources as $url => $options) {
            $fieldsInConditionsCount = count(array_intersect_key(array_flip($options['requiredFields']), $conditions));
            $requiredFieldsCount = count($options['requiredFields']);

            if ($fieldsInConditionsCount !== $requiredFieldsCount) {
                continue;
            }

            return Text::insert($url, $conditions);
        }

        return null;
    }

    /**
     * Executes a query
     *
     * @param \Muffin\Webservice\Datasource\Query $query The query to execute
     * @param array $options The options to use
     * @return \Muffin\Webservice\Model\Resource|\Muffin\Webservice\Datasource\ResultSet|int|bool
     */
    public function execute(Query $query, array $options = []): bool|int|Resource|ResultSet
    {
        $result = $this->_executeQuery($query, $options);

        $logger = $this->getDriver()->getLogger();

        if ($logger !== null) {
            $this->_logQuery($query, $logger);
        }

        return $result;
    }

    /**
     * Returns a schema for the provided endpoint
     *
     * @param string $endpoint The endpoint to get the schema for
     * @return \Muffin\Webservice\Datasource\Schema The schema to use
     */
    public function describe(string $endpoint): Schema
    {
        $shortName = App::shortName(static::class, 'Webservice', 'Webservice');
        [$plugin] = pluginSplit($shortName);

        $endpoint = Inflector::classify(str_replace('-', '_', $endpoint));
        $schemaShortName = implode('.', array_filter([$plugin, $endpoint]));
        $schemaClassName = App::className($schemaShortName, 'Model/Endpoint/Schema', 'Schema');
        if ($schemaClassName) {
            /** @var \Muffin\Webservice\Datasource\Schema */
            return new $schemaClassName($endpoint);
        }

        throw new MissingEndpointSchemaException([
            'schema' => $schemaShortName,
            'webservice' => $shortName,
        ]);
    }

    /**
     * Execute the appropriate method for a query
     *
     * @param \Muffin\Webservice\Datasource\Query $query The query to execute
     * @param array $options The options to use
     * @return \Muffin\Webservice\Model\Resource|\Muffin\Webservice\Datasource\ResultSet|int|bool
     * @psalm-suppress NullableReturnStatement
     * @psalm-suppress InvalidNullableReturnType
     */
    protected function _executeQuery(Query $query, array $options = []): bool|int|Resource|ResultSet
    {
        switch ($query->clause('action')) {
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
     * @param \Muffin\Webservice\Datasource\Query $query The query to execute
     * @param array $options The options to use
     * @return \Muffin\Webservice\Model\Resource|bool
     * @throws \Muffin\Webservice\Webservice\Exception\UnimplementedWebserviceMethodException When this method has not been
     * implemented into userland classes
     */
    protected function _executeCreateQuery(Query $query, array $options = []): bool|Resource
    {
        throw new UnimplementedWebserviceMethodException([
            'name' => static::class,
            'method' => '_executeCreateQuery',
        ]);
    }

    /**
     * Executes a query with the read action
     *
     * @param \Muffin\Webservice\Datasource\Query $query The query to execute
     * @param array $options The options to use
     * @return \Muffin\Webservice\Datasource\ResultSet|bool
     * @throws \Muffin\Webservice\Webservice\Exception\UnimplementedWebserviceMethodException When this method has not been
     * implemented into userland classes
     */
    protected function _executeReadQuery(Query $query, array $options = []): bool|ResultSet
    {
        throw new UnimplementedWebserviceMethodException([
            'name' => static::class,
            'method' => '_executeReadQuery',
        ]);
    }

    /**
     * Executes a query with the update action
     *
     * @param \Muffin\Webservice\Datasource\Query $query The query to execute
     * @param array $options The options to use
     * @return \Muffin\Webservice\Model\Resource|int|bool
     * @throws \Muffin\Webservice\Webservice\Exception\UnimplementedWebserviceMethodException When this method has not been
     * implemented into userland classes
     */
    protected function _executeUpdateQuery(Query $query, array $options = []): int|bool|Resource
    {
        throw new UnimplementedWebserviceMethodException([
            'name' => static::class,
            'method' => '_executeUpdateQuery',
        ]);
    }

    /**
     * Executes a query with the delete action
     *
     * @param \Muffin\Webservice\Datasource\Query $query The query to execute
     * @param array $options The options to use
     * @return int|bool
     * @throws \Muffin\Webservice\Webservice\Exception\UnimplementedWebserviceMethodException When this method has not been
     * implemented into userland classes
     */
    protected function _executeDeleteQuery(Query $query, array $options = []): int|bool
    {
        throw new UnimplementedWebserviceMethodException([
            'name' => static::class,
            'method' => '_executeDeleteQuery',
        ]);
    }

    /**
     * Creates a resource with the given class and properties
     *
     * @param string $resourceClass The class to use to create the resource
     * @param array $properties The properties to apply
     * @return \Muffin\Webservice\Model\Resource
     * @psalm-suppress LessSpecificReturnStatement
     * @psalm-suppress MoreSpecificReturnType
     * @psalm-suppress InvalidStringClass
     */
    protected function _createResource(string $resourceClass, array $properties = []): Resource
    {
        return new $resourceClass($properties, [
            'markClean' => true,
            'markNew' => false,
        ]);
    }

    /**
     * Logs a query to the specified logger
     *
     * @param \Muffin\Webservice\Datasource\Query $query The query to log
     * @param \Psr\Log\LoggerInterface $logger The logger instance to use
     * @return void
     */
    protected function _logQuery(Query $query, LoggerInterface $logger): void
    {
        if (!$this->getDriver()->isQueryLoggingEnabled()) {
            return;
        }

        $logger->debug($query->getEndpoint()->getName(), [
            'params' => $query->where(),
        ]);
    }

    /**
     * Loops through the results and turns them into resource objects
     *
     * @param \Muffin\Webservice\Model\Endpoint $endpoint The endpoint class to use
     * @param array $results Array of results from the API
     * @return array<\Muffin\Webservice\Model\Resource> Array of resource objects
     */
    protected function _transformResults(Endpoint $endpoint, array $results): array
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
     * @return \Muffin\Webservice\Model\Resource
     */
    protected function _transformResource(Endpoint $endpoint, array $result): Resource
    {
        $properties = [];

        foreach ($result as $property => $value) {
            $properties[$property] = $value;
        }

        return $this->_createResource($endpoint->getResourceClass(), $properties);
    }

    /**
     * Creates a handy representation of the webservice
     *
     * @return array
     */
    public function __debugInfo(): array
    {
        return [
            'driver' => $this->_driver,
            'endpoint' => $this->_endpoint,
        ];
    }
}
