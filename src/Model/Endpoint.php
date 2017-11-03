<?php

namespace Muffin\Webservice\Model;

use ArrayObject;
use BadMethodCallException;
use Cake\Core\App;
use Cake\Datasource\EntityInterface;
use Cake\Datasource\Exception\InvalidPrimaryKeyException;
use Cake\Datasource\RepositoryInterface;
use Cake\Datasource\RulesAwareTrait;
use Cake\Datasource\RulesChecker;
use Cake\Event\EventDispatcherInterface;
use Cake\Event\EventDispatcherTrait;
use Cake\Event\EventListenerInterface;
use Cake\Event\EventManager;
use Cake\Network\Exception\NotImplementedException;
use Cake\Utility\Inflector;
use Cake\Validation\ValidatorAwareTrait;
use Muffin\Webservice\Exception\MissingResourceClassException;
use Muffin\Webservice\Exception\UnexpectedDriverException;
use Muffin\Webservice\Marshaller;
use Muffin\Webservice\Query;
use Muffin\Webservice\Schema;
use Muffin\Webservice\StreamQuery;

/**
 * The table equivalent of a webservice endpoint
 *
 * @package Muffin\Webservice\Model
 */
class Endpoint implements RepositoryInterface, EventListenerInterface, EventDispatcherInterface
{

    use EventDispatcherTrait;
    use RulesAwareTrait;
    use ValidatorAwareTrait;

    /**
     * Name of default validation set.
     *
     * @var string
     */
    const DEFAULT_VALIDATOR = 'default';

    /**
     * The alias this object is assigned to validators as.
     *
     * @var string
     */
    const VALIDATOR_PROVIDER_NAME = 'endpoint';

    protected $_connection;

    /**
     * The schema object containing a description of this endpoint fields
     *
     * @var \Muffin\Webservice\Schema
     */
    protected $_schema;

    /**
     * The name of the class that represent a single resource for this endpoint
     *
     * @var string
     */
    protected $_resourceClass;

    /**
     * Registry key used to create this endpoint object
     *
     * @var string
     */
    protected $_registryAlias;

    /**
     * The name of the endpoint to contact
     *
     * @var string
     */
    protected $_endpoint;

    /**
     * The name of the field that represents the primary key in the endpoint
     *
     * @var string|array
     */
    protected $_primaryKey;

    /**
     * The name of the field that represents a human readable representation of a row
     *
     * @var string
     */
    protected $_displayField;

    /**
     * The webservice instance to call
     *
     * @var \Muffin\Webservice\Webservice\WebserviceInterface
     */
    protected $_webservice;

    /**
     * The alias to use for the endpoint
     *
     * @var string
     */
    protected $_alias;

    /**
     * The inflect method to use for endpoint routes
     *
     * @var string
     */
    protected $_inflection = 'underscore';

    /**
     * Initializes a new instance
     *
     * The $config array understands the following keys:
     *
     * - alias: Alias to be assigned to this endpoint (default to endpoint name)
     * - connection: The connection instance to use
     * - endpoint: Name of the endpoint to represent
     * - resourceClass: The fully namespaced class name of the resource class that will
     *   represent rows in this endpoint.
     * - schema: A \Muffin\Webservice\Schema object or an array that can be
     *   passed to it.
     *
     * @param array $config List of options for this endpoint
     */
    public function __construct(array $config = [])
    {
        if (!empty($config['alias'])) {
            $this->alias($config['alias']);
        }
        if (!empty($config['connection'])) {
            $this->connection($config['connection']);
        }
        if (!empty($config['displayField'])) {
            $this->displayField($config['displayField']);
        }
        if (!empty($config['endpoint'])) {
            $this->endpoint($config['endpoint']);
        }
        $eventManager = null;
        if (!empty($config['eventManager'])) {
            $eventManager = $config['eventManager'];
        }
        if (!empty($config['primaryKey'])) {
            $this->primaryKey($config['primaryKey']);
        }
        if (!empty($config['schema'])) {
            $this->schema($config['schema']);
        }
        if (!empty($config['registryAlias'])) {
            $this->registryAlias($config['registryAlias']);
        }
        if (!empty($config['resourceClass'])) {
            $this->resourceClass($config['resourceClass']);
        }
        if (!empty($config['inflection'])) {
            $this->inflectionMethod($config['inflection']);
        }

        $this->_eventManager = $eventManager ?: new EventManager();

        $this->initialize($config);
        $this->_eventManager->on($this);
        $this->dispatchEvent('Model.initialize');
    }

    /**
     * Get the default connection name.
     *
     * This method is used to get the fallback connection name if an
     * instance is created through the EndpointRegistry without a connection.
     *
     * @return string
     *
     * @see \Muffin\Webservice\Model\EndpointRegistry::get()
     */
    public static function defaultConnectionName()
    {
        $namespaceParts = explode('\\', get_called_class());
        $plugin = array_slice(array_reverse($namespaceParts), 3, 2);

        return Inflector::underscore(current($plugin));
    }

    /**
     * Initialize a endpoint instance. Called after the constructor.
     *
     * You can use this method to define validation and do any other initialization logic you need.
     *
     * ```
     *  public function initialize(array $config)
     *  {
     *      $this->primaryKey('something_else');
     *  }
     * ```
     *
     * @param array $config Configuration options passed to the constructor
     * @return void
     */
    public function initialize(array $config)
    {
    }

    /**
     * Returns the endpoint name or sets a new one
     *
     * @param string|null $endpoint the new endpoint name
     * @return string
     */
    public function endpoint($endpoint = null)
    {
        if ($endpoint !== null) {
            $this->_endpoint = $endpoint;
        }
        if ($this->_endpoint === null) {
            $endpoint = namespaceSplit(get_class($this));
            $endpoint = substr(end($endpoint), 0, -8);

            // In case someone constructs the Endpoint class directly
            if (empty($endpoint)) {
                $endpoint = $this->alias();
            }
            $inflectMethod = $this->inflectionMethod();
            $this->_endpoint = Inflector::$inflectMethod($endpoint);
        }

        return $this->_endpoint;
    }

    /**
     * Returns the endpoint alias or sets a new one
     *
     * @param string|null $alias the new endpoint alias
     * @return string
     */
    public function alias($alias = null)
    {
        if ($alias !== null) {
            $this->_alias = $alias;
        }

        return $this->_alias;
    }

    /**
     * Alias a field with the endpoint's current alias.
     *
     * @param string $field The field to alias.
     * @return string The field prefixed with the endpoint alias.
     */
    public function aliasField($field)
    {
        return $this->alias() . '.' . $field;
    }

    /**
     * Returns the endpoint registry key used to create this endpoint instance
     *
     * @param string|null $registryAlias the key used to access this object
     * @return string
     */
    public function registryAlias($registryAlias = null)
    {
        if ($registryAlias !== null) {
            $this->_registryAlias = $registryAlias;
        }
        if ($this->_registryAlias === null) {
            $this->_registryAlias = $this->alias();
        }

        return $this->_registryAlias;
    }

    /**
     * Set the driver to use
     *
     * @param \Muffin\Webservice\AbstractDriver|null $connection The driver to use
     *
     * @return \Muffin\Webservice\AbstractDriver
     */
    public function connection($connection = null)
    {
        if ($connection === null) {
            return $this->_connection;
        }

        return $this->_connection = $connection;
    }

    /**
     * Returns the schema endpoint object describing this endpoint's properties.
     *
     * If an \Muffin\Webservice\Schema is passed, it will be used for this endpoint
     * instead of the default one.
     *
     * If an array is passed, a new \Muffin\Webservice\Schema will be constructed
     * out of it and used as the schema for this endpoint.
     *
     * @param array|\Muffin\Webservice\Schema|null $schema New schema to be used for this endpoint
     *
     * @return \Muffin\Webservice\Schema
     */
    public function schema($schema = null)
    {
        if ($schema === null) {
            if ($this->_schema === null) {
                $this->_schema = $this->_initializeSchema(
                    $this->webservice()
                        ->describe($this->endpoint())
                );
            }

            return $this->_schema;
        }
        if (is_array($schema)) {
            $schema = new Schema($this->endpoint(), $schema);
        }

        return $this->_schema = $schema;
    }

    /**
     * Override this function in order to alter the schema used by this endpoint.
     * This function is only called after fetching the schema out of the webservice.
     * If you wish to provide your own schema to this table without touching the
     * database, you can override schema() or inject the definitions though that
     * method.
     *
     * ### Example:
     *
     * ```
     * protected function _initializeSchema(\Muffin\Webservice\Schema $schema) {
     *  $schema->addColumn('preferences', [
     *   'type' => 'string'
     *  ]);
     *  return $schema;
     * }
     * ```
     *
     * @param \Muffin\Webservice\Schema $schema The schema definition fetched from webservice.
     * @return \Muffin\Webservice\Schema the altered schema
     * @api
     */
    protected function _initializeSchema(Schema $schema)
    {
        return $schema;
    }

    /**
     * Test to see if a Table has a specific field/column.
     *
     * Delegates to the schema object and checks for column presence
     * using the Schema\Table instance.
     *
     * @param string $field The field to check for.
     * @return bool True if the field exists, false if it does not.
     */
    public function hasField($field)
    {
        $schema = $this->schema();

        return $schema->column($field) !== null;
    }

    /**
     * Returns the primary key field name or sets a new one
     *
     * @param string|array|null $key sets a new name to be used as primary key
     * @return string|array
     */
    public function primaryKey($key = null)
    {
        if ($key !== null) {
            $this->_primaryKey = $key;
        }
        if ($this->_primaryKey === null) {
            $schema = $this->schema();
            if (!$schema) {
                throw new UnexpectedDriverException(__('No schema has been defined for this endpoint'));
            }
            $key = (array)$schema->primaryKey();
            if (count($key) === 1) {
                $key = $key[0];
            }
            $this->_primaryKey = $key;
        }

        return $this->_primaryKey;
    }

    /**
     * Returns the display field or sets a new one
     *
     * @param string|null $key sets a new name to be used as display field
     * @return string
     */
    public function displayField($key = null)
    {
        if ($key !== null) {
            $this->_displayField = $key;
        }
        if ($this->_displayField === null) {
            $primary = (array)$this->primaryKey();
            $this->_displayField = array_shift($primary);

            $schema = $this->schema();
            if (!$schema) {
                throw new UnexpectedDriverException(__('No schema has been defined for this endpoint'));
            }
            if ($schema->column('title')) {
                $this->_displayField = 'title';
            }
            if ($schema->column('name')) {
                $this->_displayField = 'name';
            }
        }

        return $this->_displayField;
    }

    /**
     * Returns the class used to hydrate resources for this endpoint or sets
     * a new one
     *
     * @param string|null $name the name of the class to use
     * @throws \Muffin\Webservice\Exception\MissingResourceClassException when the entity class cannot be found
     * @return string
     */
    public function resourceClass($name = null)
    {
        if ($name === null && !$this->_resourceClass) {
            $default = '\Muffin\Webservice\Model\Resource';
            $self = get_called_class();
            $parts = explode('\\', $self);

            if ($self === __CLASS__ || count($parts) < 3) {
                return $this->_resourceClass = $default;
            }

            $alias = Inflector::singularize(substr(array_pop($parts), 0, -8));
            $name = implode('\\', array_slice($parts, 0, -1)) . '\Resource\\' . $alias;
            if (!class_exists($name)) {
                return $this->_resourceClass = $default;
            }
        }

        if ($name !== null) {
            $class = App::className($name, 'Model/Resource');
            $this->_resourceClass = $class;
        }

        if (!$this->_resourceClass) {
            throw new MissingResourceClassException([$name]);
        }

        return $this->_resourceClass;
    }

    /**
     * Returns the inflect method or sets a new one
     *
     * @param null|string $method The inflection method to use
     *
     * @return null|string
     */
    public function inflectionMethod($method = null)
    {
        if ($method === null) {
            return $this->_inflection;
        }

        return $this->_inflection = $method;
    }

    /**
     * Returns an instance of the Webservice used
     *
     * @param \Muffin\Webservice\Webservice\WebserviceInterface|string|null $webservice The webservice to use
     *
     * @return $this|\Muffin\Webservice\Webservice\WebserviceInterface
     */
    public function webservice($webservice = null)
    {
        if ((is_string($webservice)) || ($this->_webservice === null)) {
            if ($webservice === null) {
                $webservice = $this->endpoint();
            }

            $connection = $this->connection();
            if (!$connection) {
                throw new UnexpectedDriverException(__('No connection has been defined for this endpoint'));
            }

            $this->_webservice = $connection->webservice($webservice);

            return $this->_webservice;
        }
        if ($webservice === null) {
            return $this->_webservice;
        }

        $this->_webservice = $webservice;

        return $this;
    }

    /**
     * Creates a new Query for this repository and applies some defaults based on the
     * type of search that was selected.
     *
     * ### Model.beforeFind event
     *
     * Each find() will trigger a `Model.beforeFind` event for all attached
     * listeners. Any listener can set a valid result set using $query
     *
     * @param string $type the type of query to perform
     * @param array|\ArrayAccess $options An array that will be passed to Query::applyOptions()
     * @return \Muffin\Webservice\Query
     */
    public function find($type = 'all', $options = [])
    {
        $query = $this->query()->read();

        return $this->callFinder($type, $query, $options);
    }

    /**
     * Returns the query as passed.
     *
     * By default findAll() applies no conditions, you
     * can override this method in subclasses to modify how `find('all')` works.
     *
     * @param \Muffin\Webservice\Query $query The query to find with
     * @param array $options The options to use for the find
     * @return \Muffin\Webservice\Query The query builder
     */
    public function findAll(Query $query, array $options)
    {
        return $query;
    }

    /**
     * Sets up a query object so results appear as an indexed array, useful for any
     * place where you would want a list such as for populating input select boxes.
     *
     * When calling this finder, the fields passed are used to determine what should
     * be used as the array key, value and optionally what to group the results by.
     * By default the primary key for the model is used for the key, and the display
     * field as value.
     *
     * The results of this finder will be in the following form:
     *
     * ```
     * [
     *  1 => 'value for id 1',
     *  2 => 'value for id 2',
     *  4 => 'value for id 4'
     * ]
     * ```
     *
     * You can specify which property will be used as the key and which as value
     * by using the `$options` array, when not specified, it will use the results
     * of calling `primaryKey` and `displayField` respectively in this endpoint:
     *
     * ```
     * $endpoint->find('list', [
     *  'keyField' => 'name',
     *  'valueField' => 'age'
     * ]);
     * ```
     *
     * Results can be put together in bigger groups when they share a property, you
     * can customize the property to use for grouping by setting `groupField`:
     *
     * ```
     * $endpoint->find('list', [
     *  'groupField' => 'category_id',
     * ]);
     * ```
     *
     * When using a `groupField` results will be returned in this format:
     *
     * ```
     * [
     *  'group_1' => [
     *      1 => 'value for id 1',
     *      2 => 'value for id 2',
     *  ]
     *  'group_2' => [
     *      4 => 'value for id 4'
     *  ]
     * ]
     * ```
     *
     * @param \Muffin\Webservice\Query $query The query to find with
     * @param array $options The options for the find
     * @return \Muffin\Webservice\Query The query builder
     */
    public function findList(Query $query, array $options)
    {
        $options += [
            'keyField' => $this->primaryKey(),
            'valueField' => $this->displayField(),
            'groupField' => null
        ];

        $options = $this->_setFieldMatchers(
            $options,
            ['keyField', 'valueField', 'groupField']
        );

        return $query->formatResults(function ($results) use ($options) {
            return $results->combine(
                $options['keyField'],
                $options['valueField'],
                $options['groupField']
            );
        });
    }

    /**
     * Out of an options array, check if the keys described in `$keys` are arrays
     * and change the values for closures that will concatenate the each of the
     * properties in the value array when passed a row.
     *
     * This is an auxiliary function used for result formatters that can accept
     * composite keys when comparing values.
     *
     * @param array $options the original options passed to a finder
     * @param array $keys the keys to check in $options to build matchers from
     * the associated value
     * @return array
     */
    protected function _setFieldMatchers($options, $keys)
    {
        foreach ($keys as $field) {
            if (!is_array($options[$field])) {
                continue;
            }

            if (count($options[$field]) === 1) {
                $options[$field] = current($options[$field]);
                continue;
            }

            $fields = $options[$field];
            $options[$field] = function ($row) use ($fields) {
                $matches = [];
                foreach ($fields as $field) {
                    $matches[] = $row[$field];
                }

                return implode(';', $matches);
            };
        }

        return $options;
    }

    /**
     * Returns a single record after finding it by its primary key, if no record is
     * found this method throws an exception.
     *
     * ### Example:
     *
     * ```
     * $id = 10;
     * $article = $articles->get($id);
     *
     * $article = $articles->get($id, ['contain' => ['Comments]]);
     * ```
     *
     * @param mixed $primaryKey primary key value to find
     * @param array|\ArrayAccess $options options accepted by `Endpoint::find()`
     * @throws \Cake\Datasource\Exception\RecordNotFoundException if the record with such id
     * could not be found
     * @return \Cake\Datasource\EntityInterface
     * @see \Cake\Datasource\RepositoryInterface::find()
     */
    public function get($primaryKey, $options = [])
    {
        $key = (array)$this->primaryKey();
        $alias = $this->alias();
        foreach ($key as $index => $keyname) {
            $key[$index] = $keyname;
        }
        $primaryKey = (array)$primaryKey;
        if (count($key) !== count($primaryKey)) {
            $primaryKey = $primaryKey ?: [null];
            $primaryKey = array_map(function ($key) {
                return var_export($key, true);
            }, $primaryKey);

            throw new InvalidPrimaryKeyException(sprintf(
                'Record not found in endpoint "%s" with primary key [%s]',
                $this->webservice(),
                implode($primaryKey, ', ')
            ));
        }
        $conditions = array_combine($key, $primaryKey);

        $cacheConfig = isset($options['cache']) ? $options['cache'] : false;
        $cacheKey = isset($options['key']) ? $options['key'] : false;
        $finder = isset($options['finder']) ? $options['finder'] : 'all';
        unset($options['key'], $options['cache'], $options['finder']);

        $query = $this->find($finder, $options)->where($conditions);

        if ($cacheConfig) {
            if (!$cacheKey) {
                $cacheKey = sprintf(
                    "get:%s.%s%s",
                    $this->connection()->configName(),
                    $this->webservice(),
                    json_encode($primaryKey)
                );
            }
            $query->cache($cacheKey, $cacheConfig);
        }

        return $query->firstOrFail();
    }

    /**
     * Finds an existing record or creates a new one.
     *
     * Using the attributes defined in $search a find() will be done to locate
     * an existing record. If records matches the conditions, the first record
     * will be returned.
     *
     * If no record can be found, a new entity will be created
     * with the $search properties. If a callback is provided, it will be
     * called allowing you to define additional default values. The new
     * entity will be saved and returned.
     *
     * @param array $search The criteria to find existing records by.
     * @param callable|null $callback A callback that will be invoked for newly
     *   created entities. This callback will be called *before* the entity
     *   is persisted.
     * @return \Cake\Datasource\EntityInterface An entity.
     */
    public function findOrCreate($search, callable $callback = null)
    {
        $query = $this->find()->where($search);
        $row = $query->first();
        if ($row) {
            return $row;
        }
        $entity = $this->newEntity();
        $entity->set($search, ['guard' => false]);
        if ($callback) {
            $callback($entity);
        }

        return $this->save($entity) ?: $entity;
    }

    /**
     * Creates a new Query instance for this repository
     *
     * @return \Muffin\Webservice\Query
     */
    public function query()
    {
        return new Query($this->webservice(), $this);
    }

    /**
     * Update all matching records.
     *
     * Sets the $fields to the provided values based on $conditions.
     * This method will *not* trigger beforeSave/afterSave events. If you need those
     * first load a collection of records and update them.
     *
     * @param array $fields A hash of field => new value.
     * @param mixed $conditions Conditions to be used, accepts anything Query::where()
     * can take.
     * @return int Count Returns the affected rows.
     */
    public function updateAll($fields, $conditions)
    {
        return $this->query()->update()->where($conditions)->set($fields)->execute();
    }

    /**
     * Delete all matching records.
     *
     * Deletes all records matching the provided conditions.
     *
     * This method will *not* trigger beforeDelete/afterDelete events. If you
     * need those first load a collection of records and delete them.
     *
     * @param mixed $conditions Conditions to be used, accepts anything Query::where()
     * can take.
     *
     * @return int Count Returns the affected rows.
     *
     * @see \\Muffin\Webservice\Endpoint::delete()
     */
    public function deleteAll($conditions)
    {
        return $this->query()->delete()->where($conditions)->execute();
    }

    /**
     * Returns true if there is any record in this repository matching the specified
     * conditions.
     *
     * @param array|\ArrayAccess $conditions list of conditions to pass to the query
     *
     * @return bool
     */
    public function exists($conditions)
    {
        return ($this->find()->where($conditions)->count() > 0);
    }

    /**
     * Persists an resource based on the fields that are marked as dirty and
     * returns the same resource after a successful save or false in case
     * of any error.
     *
     * @param \Cake\Datasource\EntityInterface $resource the resource to be saved
     * @param array|\ArrayAccess $options The options to use when saving.
     *
     * @return \Cake\Datasource\EntityInterface|bool
     */
    public function save(EntityInterface $resource, $options = [])
    {
        $options = new ArrayObject($options + [
                'checkRules' => true,
                'checkExisting' => false,
            ]);

        if ($resource->errors()) {
            return false;
        }

        if ($resource->isNew() === false && !$resource->dirty()) {
            return $resource;
        }

        $primaryColumns = (array)$this->primaryKey();

        if ($options['checkExisting'] && $primaryColumns && $resource->isNew() && $resource->has($primaryColumns)) {
            $alias = $this->alias();
            $conditions = [];
            foreach ($resource->extract($primaryColumns) as $k => $v) {
                $conditions["$alias.$k"] = $v;
            }
            $resource->isNew(!$this->exists($conditions));
        }

        $mode = $resource->isNew() ? RulesChecker::CREATE : RulesChecker::UPDATE;
        if ($options['checkRules'] && !$this->checkRules($resource, $mode, $options)) {
            return false;
        }

        $event = $this->dispatchEvent('Model.beforeSave', compact('resource', 'options'));

        if ($event->isStopped()) {
            return $event->result;
        }

        $data = $resource->extract($this->schema()->columns(), true);

        if ($resource->isNew()) {
            $query = $this->query()->create();
        } else {
            $query = $this->query()->update()->where($resource->extract($primaryColumns));
        }
        $query->set($data);

        $result = $query->execute();
        if (!$result) {
            return false;
        }

        if (($resource->isNew()) && ($result instanceof EntityInterface)) {
            return $result;
        }

        $className = get_class($resource);

        return new $className($resource->toArray(), [
            'markNew' => false,
            'markClean' => true
        ]);
    }

    /**
     * Delete a single resource.
     *
     * @param \Cake\Datasource\EntityInterface $resource The resource to remove.
     * @param array|\ArrayAccess $options The options for the delete.
     * @return bool success
     */
    public function delete(EntityInterface $resource, $options = [])
    {
        return (bool)$this->query()->delete()->where([
            $this->primaryKey() => $resource->get($this->primaryKey())
        ])->execute();
    }

    /**
     * Returns true if the finder exists for the endpoint
     *
     * @param string $type name of finder to check
     *
     * @return bool
     */
    public function hasFinder($type)
    {
        $finder = 'find' . $type;

        return method_exists($this, $finder);
    }

    /**
     * Calls a finder method directly and applies it to the passed query,
     * if no query is passed a new one will be created and returned
     *
     * @param string $type name of the finder to be called
     * @param \Muffin\Webservice\Query $query The query object to apply the finder options to
     * @param array $options List of options to pass to the finder
     *
     * @return \Muffin\Webservice\Query
     */
    public function callFinder($type, Query $query, array $options = [])
    {
        $query->applyOptions($options);
        $options = $query->getOptions();
        $finder = 'find' . $type;
        if (method_exists($this, $finder)) {
            return $this->{$finder}($query, $options);
        }

        throw new \BadMethodCallException(
            sprintf('Unknown finder method "%s"', $type)
        );
    }

    /**
     * Provides the dynamic findBy and findByAll methods.
     *
     * @param string $method The method name that was fired.
     * @param array $args List of arguments passed to the function.
     * @return mixed
     * @throws \BadMethodCallException when there are missing arguments, or when
     *  and & or are combined.
     */
    protected function _dynamicFinder($method, $args)
    {
        $method = Inflector::underscore($method);
        preg_match('/^find_([\w]+)_by_/', $method, $matches);
        if (empty($matches)) {
            // find_by_ is 8 characters.
            $fields = substr($method, 8);
            $findType = 'all';
        } else {
            $fields = substr($method, strlen($matches[0]));
            $findType = Inflector::variable($matches[1]);
        }
        $hasOr = strpos($fields, '_or_');
        $hasAnd = strpos($fields, '_and_');

        $makeConditions = function ($fields, $args) {
            $conditions = [];
            if (count($args) < count($fields)) {
                throw new BadMethodCallException(sprintf(
                    'Not enough arguments for magic finder. Got %s required %s',
                    count($args),
                    count($fields)
                ));
            }
            foreach ($fields as $field) {
                $conditions[$this->aliasField($field)] = array_shift($args);
            }

            return $conditions;
        };

        if ($hasOr !== false && $hasAnd !== false) {
            throw new BadMethodCallException(
                'Cannot mix "and" & "or" in a magic finder. Use find() instead.'
            );
        }

        $conditions = [];
        if ($hasOr === false && $hasAnd === false) {
            $conditions = $makeConditions([$fields], $args);
        } elseif ($hasOr !== false) {
            $fields = explode('_or_', $fields);
            $conditions = [
                'OR' => $makeConditions($fields, $args)
            ];
        } elseif ($hasAnd !== false) {
            $fields = explode('_and_', $fields);
            $conditions = $makeConditions($fields, $args);
        }

        return $this->find($findType, [
            'conditions' => $conditions,
        ]);
    }

    /**
     * Handles dynamic finders.
     *
     * @param string $method name of the method to be invoked
     * @param array $args List of arguments passed to the function
     * @return mixed
     * @throws \BadMethodCallException
     */
    public function __call($method, $args)
    {
        if (preg_match('/^find(?:\w+)?By/', $method) > 0) {
            return $this->_dynamicFinder($method, $args);
        }

        throw new BadMethodCallException(
            sprintf('Unknown method "%s"', $method)
        );
    }

    /**
     * Get the object used to marshal/convert array data into objects.
     *
     * Override this method if you want a endpoint object to use custom
     * marshalling logic.
     *
     * @return \Muffin\Webservice\Marshaller
     *
     * @see \Muffin\Webservice\Marshaller
     */
    public function marshaller()
    {
        return new Marshaller($this);
    }

    /**
     * {@inheritDoc}
     *
     * @return \Muffin\Webservice\Model\Resource
     */
    public function newEntity($data = null, array $options = [])
    {
        if ($data === null) {
            $class = $this->resourceClass();
            $entity = new $class([], ['source' => $this->registryAlias()]);

            return $entity;
        }
        $marshaller = $this->marshaller();

        return $marshaller->one($data, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function newEntities(array $data, array $options = [])
    {
        $marshaller = $this->marshaller();

        return $marshaller->many($data, $options);
    }

    /**
     * Merges the passed `$data` into `$entity` respecting the accessible
     * fields configured on the resource. Returns the same resource after being
     * altered.
     *
     * This is most useful when editing an existing resource using request data:
     *
     * ```
     * $article = $this->Articles->patchEntity($article, $this->request->data());
     * ```
     *
     * @param \Cake\Datasource\EntityInterface $entity the resource that will get the
     * data merged in
     * @param array $data key value list of fields to be merged into the resource
     * @param array $options A list of options for the object hydration.
     *
     * @return \Cake\Datasource\EntityInterface
     */
    public function patchEntity(EntityInterface $entity, array $data, array $options = [])
    {
        $marshaller = $this->marshaller();

        return $marshaller->merge($entity, $data, $options);
    }

    /**
     * Merges each of the elements passed in `$data` into the entities
     * found in `$entities` respecting the accessible fields configured on the entities.
     * Merging is done by matching the primary key in each of the elements in `$data`
     * and `$entities`.
     *
     * This is most useful when editing a list of existing entities using request data:
     *
     * ```
     * $article = $this->Articles->patchEntities($articles, $this->request->data());
     * ```
     *
     * @param array|\Traversable $entities the entities that will get the
     * data merged in
     * @param array $data list of arrays to be merged into the entities
     * @param array $options A list of options for the objects hydration.
     *
     * @return array
     */
    public function patchEntities($entities, array $data, array $options = [])
    {
        $marshaller = $this->marshaller();

        return $marshaller->mergeMany($entities, $data, $options);
    }

    /**
     * Get the Model callbacks this endpoint is interested in.
     *
     * By implementing the conventional methods a endpoint class is assumed
     * to be interested in the related event.
     *
     * Override this method if you need to add non-conventional event listeners.
     * Or if you want you endpoint to listen to non-standard events.
     *
     * The conventional method map is:
     *
     * - Model.beforeMarshal => beforeMarshal
     * - Model.beforeFind => beforeFind
     * - Model.beforeSave => beforeSave
     * - Model.afterSave => afterSave
     * - Model.afterSaveCommit => afterSaveCommit
     * - Model.beforeDelete => beforeDelete
     * - Model.afterDelete => afterDelete
     * - Model.afterDeleteCommit => afterDeleteCommit
     * - Model.beforeRules => beforeRules
     * - Model.afterRules => afterRules
     *
     * @return array
     */
    public function implementedEvents()
    {
        $eventMap = [
            'Model.beforeMarshal' => 'beforeMarshal',
            'Model.beforeFind' => 'beforeFind',
            'Model.beforeSave' => 'beforeSave',
            'Model.afterSave' => 'afterSave',
            'Model.afterSaveCommit' => 'afterSaveCommit',
            'Model.beforeDelete' => 'beforeDelete',
            'Model.afterDelete' => 'afterDelete',
            'Model.afterDeleteCommit' => 'afterDeleteCommit',
            'Model.beforeRules' => 'beforeRules',
            'Model.afterRules' => 'afterRules',
        ];
        $events = [];

        foreach ($eventMap as $event => $method) {
            if (!method_exists($this, $method)) {
                continue;
            }
            $events[$event] = $method;
        }

        return $events;
    }

    /**
     * {@inheritDoc}
     *
     * @param \Cake\Datasource\RulesChecker $rules The rules object to be modified.
     * @return \Cake\Datasource\RulesChecker
     */
    public function buildRules(RulesChecker $rules)
    {
        return $rules;
    }

    /**
     * Returns a handy representation of this endpoint
     *
     * @return array
     */
    public function __debugInfo()
    {
        $conn = $this->connection();

        return [
            'registryAlias' => $this->registryAlias(),
            'alias' => $this->alias(),
            'endpoint' => $this->endpoint(),
            'resourceClass' => $this->resourceClass(),
            'defaultConnection' => $this->defaultConnectionName(),
            'connectionName' => $conn ? $conn->configName() : null
        ];
    }
}
