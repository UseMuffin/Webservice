<?php
declare(strict_types=1);

namespace Muffin\Webservice\Model;

use ArrayAccess;
use ArrayObject;
use BadMethodCallException;
use Cake\Collection\CollectionInterface;
use Cake\Core\App;
use Cake\Datasource\EntityInterface;
use Cake\Datasource\Exception\InvalidPrimaryKeyException;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Datasource\QueryInterface;
use Cake\Datasource\RepositoryInterface;
use Cake\Datasource\ResultSetInterface;
use Cake\Datasource\RulesAwareTrait;
use Cake\Datasource\RulesChecker;
use Cake\Event\EventDispatcherInterface;
use Cake\Event\EventDispatcherTrait;
use Cake\Event\EventListenerInterface;
use Cake\ORM\Exception\PersistenceFailedException;
use Cake\Utility\Inflector;
use Cake\Validation\ValidatorAwareTrait;
use Closure;
use Exception;
use Muffin\Webservice\Datasource\Connection;
use Muffin\Webservice\Datasource\Marshaller;
use Muffin\Webservice\Datasource\Query;
use Muffin\Webservice\Datasource\Schema;
use Muffin\Webservice\Model\Exception\MissingResourceClassException;
use Muffin\Webservice\Webservice\WebserviceInterface;
use Psr\SimpleCache\CacheInterface;
use function Cake\Core\namespaceSplit;

/**
 * The table equivalent of a webservice endpoint
 *
 * @package Muffin\Webservice\Model
 * @template TSubject of object
 * @implements \Cake\Event\EventDispatcherInterface<TSubject>
 */
class Endpoint implements RepositoryInterface, EventListenerInterface, EventDispatcherInterface
{
    /**
     * @use \Cake\Event\EventDispatcherTrait<\Muffin\Webservice\Model\Endpoint>
     */
    use EventDispatcherTrait;
    use RulesAwareTrait;
    use ValidatorAwareTrait;

    /**
     * Name of default validation set.
     *
     * @var string
     */
    public const DEFAULT_VALIDATOR = 'default';

    /**
     * The alias this object is assigned to validators as.
     *
     * @var string
     */
    public const VALIDATOR_PROVIDER_NAME = 'endpoint';

    /**
     * The webservice instance to call
     *
     * @var \Muffin\Webservice\Webservice\WebserviceInterface|null
     */
    protected ?WebserviceInterface $_webservice = null;

    /**
     * The alias to use for the endpoint
     *
     * @var string|null
     */
    protected ?string $_alias = null;

    /**
     * Connection instance this endpoint uses
     *
     * @var \Muffin\Webservice\Datasource\Connection|null
     */
    protected ?Connection $_connection = null;

    /**
     * The schema object containing a description of this endpoint fields
     *
     * @var \Muffin\Webservice\Datasource\Schema|null
     */
    protected ?Schema $_schema = null;

    /**
     * The name of the field that represents the primary key in the endpoint
     *
     * @var list<string>|string|null
     */
    protected array|string|null $_primaryKey = null;

    /**
     * The name of the field that represents a human readable representation of a row
     *
     * @var array<string>|string|null
     */
    protected array|string|null $_displayField = null;

    /**
     * The name of the endpoint to contact
     *
     * @var string|null
     */
    protected ?string $_name = null;

    /**
     * The name of the class that represent a single resource for this endpoint
     *
     * @var string|null
     * @psalm-var class-string<\Muffin\Webservice\Model\Resource>
     */
    protected ?string $_resourceClass = null;

    /**
     * Registry key used to create this endpoint object
     *
     * @var string|null
     */
    protected ?string $_registryAlias = null;

    /**
     * The inflect method to use for endpoint routes
     *
     * @var string
     */
    protected string $_inflectionMethod = 'underscore';

    /**
     * Initializes a new instance
     *
     * The $config array understands the following keys:
     *
     * - alias: Alias to be assigned to this endpoint (default to endpoint name)
     * - connection: The connection instance to use
     * - name: Name of the endpoint to represent
     * - endpoint: Deprecated, please pass name instead
     * - resourceClass: The fully namespaced class name of the resource class that will
     *   represent rows in this endpoint.
     * - schema: A \Muffin\Webservice\Schema object or an array that can be
     *   passed to it.
     *
     * @param array $config List of options for this endpoint
     */
    final public function __construct(array $config = [])
    {
        if (!empty($config['alias'])) {
            $this->setAlias($config['alias']);
        }
        if (!empty($config['connection'])) {
            $this->setConnection($config['connection']);
        }
        if (!empty($config['displayField'])) {
            $this->setDisplayField($config['displayField']);
        }
        if (!empty($config['inflect'])) {
            $this->setInflectionMethod($config['inflect']);
        }
        if (!empty($config['name'])) {
            $this->setName($config['name']);
        }
        if (!empty($config['endpoint'])) {
            $this->setName($config['endpoint']);
        }
        if (!empty($config['primaryKey'])) {
            $this->setPrimaryKey($config['primaryKey']);
        }
        if (!empty($config['schema'])) {
            $this->setSchema($config['schema']);
        }
        if (!empty($config['registryAlias'])) {
            $this->setRegistryAlias($config['registryAlias']);
        }
        if (!empty($config['resourceClass'])) {
            $this->setResourceClass($config['resourceClass']);
        }

        if (!empty($config['eventManager'])) {
            $this->setEventManager($config['eventManager']);
        }

        $this->initialize($config);
        $this->getEventManager()->on($this);
        $this->dispatchEvent('Model.initialize');
    }

    /**
     * Get the default connection name.
     *
     * This method is used to get the fallback connection name if an
     * instance is created through the EndpointRegistry without a connection.
     *
     * @return string
     * @throws \Exception When the plugin name cannot be found
     * @see \Muffin\Webservice\Model\EndpointRegistry::get()
     */
    public static function defaultConnectionName(): string
    {
        $namespaceParts = explode('\\', static::class);
        $plugin = current(array_slice(array_reverse($namespaceParts), 3, 2));

        if ($plugin === false) {
            throw new Exception('Could not find plugin name');
        }

        if ($plugin === 'App') {
            return 'webservice';
        }

        return Inflector::underscore($plugin);
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
    public function initialize(array $config): void
    {
    }

    /**
     * Set the name of this endpoint
     *
     * @param string $name The name for this endpoint instance
     * @return $this
     */
    public function setName(string $name): Endpoint
    {
        $inflectMethod = $this->getInflectionMethod();
        $this->_name = Inflector::{$inflectMethod}($name);

        return $this;
    }

    /**
     * Get the name of this endpoint
     *
     * @return string
     * @throws \Exception When the endpoint could not be determined from the name
     */
    public function getName(): string
    {
        if ($this->_name === null) {
            $endpoint = namespaceSplit(static::class);
            $name = end($endpoint);

            if ($name === false) {
                throw new Exception('Could not find the name of the endpoint');
            }

            $endpoint = substr($name, 0, -8);

            $inflectMethod = $this->getInflectionMethod();
            $this->_name = Inflector::{$inflectMethod}($endpoint);
        }

        return $this->_name;
    }

    /**
     * Alias a field with the endpoint's current alias.
     *
     * @param string $field The field to alias.
     * @return string The field prefixed with the endpoint alias.
     */
    public function aliasField(string $field): string
    {
        return $this->getAlias() . '.' . $field;
    }

    /**
     * Sets the table registry key used to create this table instance.
     *
     * @param string $registryAlias The key used to access this object.
     * @return $this
     */
    public function setRegistryAlias(string $registryAlias): Endpoint
    {
        $this->_registryAlias = $registryAlias;

        return $this;
    }

    /**
     * Returns the table registry key used to create this table instance.
     *
     * @return string
     */
    public function getRegistryAlias(): string
    {
        if ($this->_registryAlias === null) {
            $this->_registryAlias = $this->getAlias();
        }

        return $this->_registryAlias;
    }

    /**
     * Sets the connection driver.
     *
     * @param \Muffin\Webservice\Datasource\Connection $connection Connection instance
     * @return $this
     */
    public function setConnection(Connection $connection): Endpoint
    {
        $this->_connection = $connection;

        return $this;
    }

    /**
     * Returns the connection driver.
     *
     * @return \Muffin\Webservice\Datasource\Connection|null
     */
    public function getConnection(): ?Connection
    {
        return $this->_connection;
    }

    /**
     * Set the endpoints schema
     *
     * If a \Muffin\Webservice\Schema is passed, it will be used for this endpoint
     * instead of the default one.
     *
     * If an array is passed, a new \Muffin\Webservice\Schema will be constructed
     * out of it and used as the schema for this endpoint.
     *
     * @param \Muffin\Webservice\Datasource\Schema|array $schema Either an array of fields and config, or a schema object
     * @return $this
     */
    public function setSchema(Schema|array $schema): Endpoint
    {
        if (is_array($schema)) {
            $schema = new Schema($this->getName(), $schema);
        }

        $this->_schema = $schema;

        return $this;
    }

    /**
     * Returns the schema endpoint object describing this endpoint's properties.
     *
     * @return \Muffin\Webservice\Datasource\Schema|null
     */
    public function getSchema(): ?Schema
    {
        if ($this->_schema === null) {
            $this->_schema = $this->getWebservice()?->describe($this->getName());
        }

        return $this->_schema;
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
    public function hasField(string $field): bool
    {
        $schema = $this->getSchema();

        return $schema?->getColumn($field) !== null;
    }

    /**
     * Returns the current endpoint
     *
     * @param list<string>|string|null $key sets a new name to be used as primary key
     * @return $this
     */
    public function setPrimaryKey(string|array|null $key): Endpoint
    {
        $this->_primaryKey = $key;

        return $this;
    }

    /**
     * Get the endpoints primary key, if one is not set, fetch it from the schema
     *
     * @return list<string>|string|null
     * @throws \Muffin\Webservice\Webservice\Exception\UnexpectedDriverException When no schema exists to fetch the key from
     */
    public function getPrimaryKey(): array|string|null
    {
        if ($this->_primaryKey === null) {
            $schema = $this->getSchema();
            $key = $schema?->getPrimaryKey();
            if ($key !== null && count($key) === 1) {
                $key = $key[0];
            }
            $this->_primaryKey = $key;
        }

        return $this->_primaryKey;
    }

    /**
     * Sets the endpoint display field
     *
     * @param array<string>|string $field The new field to use as the display field
     * @return $this
     */
    public function setDisplayField(string|array $field): Endpoint
    {
        $this->_displayField = $field;

        return $this;
    }

    /**
     * Get the endpoints current display field
     *
     * @return array<string>|string|null
     * @throws \Muffin\Webservice\Webservice\Exception\UnexpectedDriverException When no schema exists to fetch the key from
     */
    public function getDisplayField(): string|array|null
    {
        if ($this->_displayField === null) {
            $primary = (array)$this->getPrimaryKey();
            $this->_displayField = array_shift($primary);

            $schema = $this->getSchema();
            if ($schema?->getColumn('title') !== null) {
                $this->_displayField = 'title';
            }
            if ($schema?->getColumn('name') !== null) {
                $this->_displayField = 'name';
            }
        }

        return $this->_displayField;
    }

    /**
     * Set the resource class name used to hydrate resources for this endpoint
     *
     * @param string $name Name of the class to use
     * @return $this
     * @throws \Muffin\Webservice\Model\Exception\MissingResourceClassException If the resource class specified does not exist
     */
    public function setResourceClass(string $name): Endpoint
    {
        /** @psalm-var class-string<\Muffin\Webservice\Model\Resource>|null $className */
        $className = App::className($name, 'Model/Resource');
        if (!$className) {
            throw new MissingResourceClassException([$name]);
        }

        $this->_resourceClass = $className;

        return $this;
    }

    /**
     * Get the resource class name used to hydrate resources for this endpoint
     *
     * @return string
     * @psalm-return class-string<\Muffin\Webservice\Model\Resource>
     */
    public function getResourceClass(): string
    {
        if (!$this->_resourceClass) {
            $default = Resource::class;
            $self = static::class;
            $parts = explode('\\', $self);

            if ($self === self::class || count($parts) < 3) {
                return $this->_resourceClass = $default;
            }

            $alias = Inflector::singularize(substr(array_pop($parts), 0, -8));
            /** @psalm-var class-string<\Muffin\Webservice\Model\Resource> $alias */
            $name = implode('\\', array_slice($parts, 0, -1)) . '\Resource\\' . $alias;
            /** @psalm-var class-string<\Muffin\Webservice\Model\Resource> $name */
            if (!class_exists($name)) {
                return $this->_resourceClass = $default;
            }

            return $this->_resourceClass = $name;
        }

        return $this->_resourceClass;
    }

    /**
     * Set a new inflection method
     *
     * @param string $method The name of the inflection method
     * @return $this
     */
    public function setInflectionMethod(string $method): Endpoint
    {
        $this->_inflectionMethod = $method;

        return $this;
    }

    /**
     * Get the inflection method
     *
     * @return string
     */
    public function getInflectionMethod(): string
    {
        return $this->_inflectionMethod;
    }

    /**
     * Set the webservice instance to be used for this endpoint
     *
     * @param string $alias Alias for the webservice
     * @param \Muffin\Webservice\Webservice\WebserviceInterface $webservice The webservice instance
     * @return $this
     * @throws \Muffin\Webservice\Webservice\Exception\UnexpectedDriverException When no driver exists for the endpoint
     */
    public function setWebservice(string $alias, WebserviceInterface $webservice): Endpoint
    {
        $connection = $this->getConnection();
        if ($connection !== null) {
            $connection->setWebservice($alias, $webservice);
            $this->_webservice = $connection->getWebservice($alias);
        }

        return $this;
    }

    /**
     * Get this endpoints associated webservice
     *
     * @return \Muffin\Webservice\Webservice\WebserviceInterface|null
     */
    public function getWebservice(): ?WebserviceInterface
    {
        if ($this->_webservice === null) {
            $this->_webservice = $this->getConnection()?->getWebservice($this->getName());
        }

        return $this->_webservice;
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
     * @param mixed ...$args Arguments that match up to finder-specific parameters
     * @return \Cake\Datasource\QueryInterface
     */
    public function find(string $type = 'all', mixed ...$args): QueryInterface
    {
        $query = $this->query()->read();

        return $this->callFinder($type, $query, $args);
    }

    /**
     * Returns the query as passed.
     *
     * By default findAll() applies no conditions, you
     * can override this method in subclasses to modify how `find('all')` works.
     *
     * @param \Muffin\Webservice\Datasource\Query $query The query to find with
     * @param array $options The options to use for the find
     * @return \Muffin\Webservice\Datasource\Query The query builder
     */
    public function findAll(Query $query, array $options): Query
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
     * @param \Muffin\Webservice\Datasource\Query $query The query to find with
     * @param array $options The options for the find
     * @return \Muffin\Webservice\Datasource\Query The query builder
     */
    public function findList(Query $query, array $options): Query
    {
        if (isset($options[0])) {
            $options = $options[0];
        }
        $options += [
            'keyField' => $this->getPrimaryKey(),
            'valueField' => $this->getDisplayField(),
            'groupField' => null,
        ];

        $options = $this->_setFieldMatchers(
            $options,
            ['keyField', 'valueField', 'groupField']
        );

        return $query->formatResults(function (CollectionInterface $results) use ($options) {
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
    protected function _setFieldMatchers(array $options, array $keys): array
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
     * @param array|string $finder The finder to use. Passing an options array is deprecated.
     * @param \Psr\SimpleCache\CacheInterface|string|null $cache The cache config to use.
     *   Defaults to `null`, i.e. no caching.
     * @param \Closure|string|null $cacheKey The cache key to use. If not provided
     *   one will be autogenerated if `$cache` is not null.
     * @param mixed ...$args Additional arguments for configuring things like caching.
     * @psalm-suppress InvalidReturnType For backwards compatibility. This function can also return array<array-key, mixed>
     * @return \Cake\Datasource\EntityInterface
     * @see \Cake\Datasource\RepositoryInterface::find()
     */
    public function get(
        mixed $primaryKey,
        array|string $finder = 'all',
        CacheInterface|string|null $cache = null,
        Closure|string|null $cacheKey = null,
        mixed ...$args
    ): EntityInterface {
        $key = (array)$this->getPrimaryKey();
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
                $this->getName(),
                implode(', ', $primaryKey)
            ));
        }
        $conditions = array_combine($key, $primaryKey);

        $cacheConfig = $args['cache'] ?? false;
        $cacheKey = $args['key'] ?? false;
        $finder = $args['finder'] ?? 'all';
        unset($args['key'], $args['cache'], $args['finder']);

        $query = $this->find($finder, $args)->where($conditions);

        if (($cacheConfig !== false && $cacheConfig !== null) && is_callable($cache)) {
            if ($cacheKey !== null) {
                $cacheKey = sprintf(
                    'get:%s.%s%s',
                    $this->getConnection()?->configName() ?? 'None',
                    $this->getName(),
                    json_encode($primaryKey)
                );
            }
            $cache($cacheKey, $cacheConfig);
        }

        $result = $query->firstOrFail();
        if ($result instanceof EntityInterface) {
            return $result;
        }

        throw new RecordNotFoundException();
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
     * @param mixed $search The criteria to find existing records by.
     * @param callable|null $callback A callback that will be invoked for newly
     *   created entities. This callback will be called *before* the entity
     *   is persisted.
     * @return \Cake\Datasource\EntityInterface|array An entity.
     * @throws \Cake\ORM\Exception\PersistenceFailedException When the entity couldn't be saved
     */
    public function findOrCreate(mixed $search, ?callable $callback = null): EntityInterface|array
    {
        $query = $this->find()->where($search);
        $row = $query->first();
        if ($row) {
            return $row;
        }

        $entity = $this->newEntity();
        $entity->set($search, ['guard' => false]);
        if (is_callable($callback)) {
            $callback($entity);
        }

        $result = $this->save($entity);
        if ($result === false) {
            throw new PersistenceFailedException($entity, ['findOrCreate']);
        }

        return $entity;
    }

    /**
     * Creates a new Query instance for this repository
     *
     * @return \Muffin\Webservice\Datasource\Query
     * @throws \Exception When non webservice is set
     */
    public function query(): Query
    {
        $webservice = $this->getWebservice();
        if ($webservice === null) {
            throw new Exception('Webservice not initialized, cannot create query');
        }

        return new Query($webservice, $this);
    }

    /**
     * Update all matching records.
     *
     * Sets the $fields to the provided values based on $conditions.
     * This method will *not* trigger beforeSave/afterSave events. If you need those
     * first load a collection of records and update them.
     *
     * @param \Closure|array|string $fields = array(); $fields A hash of field => new value.
     * @param \Closure|array|string|null $conditions Conditions to be used, accepts anything Query::where() can take.
     * @return int Count Returns the affected rows.
     * @psalm-suppress MoreSpecificImplementedParamType
     */
    public function updateAll(Closure|array|string $fields, Closure|array|string|null $conditions): int
    {
        $res = $this->query()->update()->where($conditions)->set($fields)->execute();

        if ($res instanceof ResultSetInterface) {
            return $res->count();
        }
        if ($res === false) {
            return 0;
        }
        if (is_integer($res)) {
            return $res;
        }

        // The other datatypes indicate only a single entity is updated
        return 1;
    }

    /**
     * Delete all matching records.
     *
     * Deletes all records matching the provided conditions.
     *
     * This method will *not* trigger beforeDelete/afterDelete events. If you
     * need those first load a collection of records and delete them.
     *
     * @param mixed $conditions Conditions to be used, accepts anything Query::where() can take.
     * @return int Count of affected rows.
     * @throws \Exception When the delete action could not be executed
     * @see \Muffin\Webservice\Endpoint::delete()
     * @psalm-suppress InvalidReturnStatement
     * @psalm-suppress InvalidReturnType
     */
    public function deleteAll(mixed $conditions): int
    {
        $res = $this->query()->delete()->where($conditions)->execute();

        if ($res instanceof ResultSetInterface) {
            return $res->count();
        }
        if ($res === false) {
            return 0;
        }
        if (is_integer($res)) {
            return $res;
        }

        // The other datatypes indicate only a single entity is updated
        return 1;
    }

    /**
     * Returns true if there is any record in this repository matching the specified
     * conditions.
     *
     * @param mixed $conditions list of conditions to pass to the query
     * @return bool
     */
    public function exists(mixed $conditions): bool
    {
        return $this->find()->where($conditions)->count() > 0;
    }

    /**
     * Persists an resource based on the fields that are marked as dirty and
     * returns the same resource after a successful save or false in case
     * of any error.
     *
     * @param \Cake\Datasource\EntityInterface $entity the resource to be saved
     * @param \ArrayAccess|array $options The options to use when saving.
     * @return \Cake\Datasource\EntityInterface|false
     */
    public function save(EntityInterface $entity, array|ArrayAccess $options = []): EntityInterface|false
    {
        $options = new ArrayObject((array)$options + [
                'checkRules' => true,
                'checkExisting' => false,
            ]);

        if ($entity->getErrors()) {
            return false;
        }

        if ($entity->isNew() === false && !$entity->isDirty()) {
            return $entity;
        }

        $primaryColumns = (array)$this->getPrimaryKey();

        if ($options['checkExisting'] && $primaryColumns && $entity->isNew() && $entity->has($primaryColumns)) {
            $alias = $this->getAlias();
            $conditions = [];
            foreach ($entity->extract($primaryColumns) as $k => $v) {
                $conditions["$alias.$k"] = $v;
            }
            $entity->setNew(!$this->exists($conditions));
        }

        $mode = $entity->isNew() ? RulesChecker::CREATE : RulesChecker::UPDATE;
        if ($options['checkRules'] && !$this->checkRules($entity, $mode, $options)) {
            return false;
        }

        $event = $this->dispatchEvent('Model.beforeSave', compact('entity', 'options'));

        if ($event->isStopped()) {
            return $event->getResult();
        }

        $data = $entity->extract($this->getSchema()?->columns() ?? [], true);

        if ($entity->isNew()) {
            $query = $this->query()->create();
        } else {
            $query = $this->query()->update()->where($entity->extract($primaryColumns));
        }
        $query->set($data);

        $result = $query->execute();
        if ($result === false) {
            return false;
        }

        if ($entity->isNew() && ($result instanceof EntityInterface)) {
            return $result;
        }

        /** @psalm-var class-string<\Cake\Datasource\EntityInterface> $className */
        $className = get_class($entity);

        return new $className($entity->toArray(), [
            'markNew' => false,
            'markClean' => true,
        ]);
    }

    /**
     * Delete a single resource.
     *
     * @param \Cake\Datasource\EntityInterface $entity The resource to remove.
     * @param \ArrayAccess|array $options The options for the delete.
     * @return bool
     */
    public function delete(EntityInterface $entity, array|ArrayAccess $options = []): bool
    {
        $primaryKeys = (array)$this->getPrimaryKey();
        $values = $entity->extract($primaryKeys);

        return (bool)$this->query()->delete()->where(array_combine($primaryKeys, $values))->execute();
    }

    /**
     * Returns true if the finder exists for the endpoint
     *
     * @param string $type name of finder to check
     * @return bool
     */
    public function hasFinder(string $type): bool
    {
        $finder = 'find' . $type;

        return method_exists($this, $finder);
    }

    /**
     * Calls a finder method directly and applies it to the passed query,
     * if no query is passed a new one will be created and returned
     *
     * @param string $type name of the finder to be called
     * @param \Muffin\Webservice\Datasource\Query $query The query object to apply the finder options to
     * @param array $options List of options to pass to the finder
     * @return \Muffin\Webservice\Datasource\Query
     * @throws \BadMethodCallException If the requested finder cannot be found
     */
    public function callFinder(string $type, Query $query, array $options = []): Query
    {
        $query->applyOptions($options);
        $options = $query->getOptions();
        $finder = 'find' . $type;
        if (method_exists($this, $finder)) {
            return $this->{$finder}($query, $options);
        }

        throw new BadMethodCallException(
            sprintf('Unknown finder method "%s"', $type)
        );
    }

    /**
     * Provides the dynamic findBy and findByAll methods.
     *
     * @param string $method The method name that was fired.
     * @param array $args List of arguments passed to the function.
     * @return \Cake\Datasource\QueryInterface
     * @throws \BadMethodCallException when there are missing arguments, or when and & or are combined.
     */
    protected function _dynamicFinder(string $method, array $args): QueryInterface
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
                'OR' => $makeConditions($fields, $args),
            ];
        } else {
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
     * @return \Cake\Datasource\QueryInterface
     * @throws \BadMethodCallException If the request dynamic finder cannot be found
     */
    public function __call(string $method, array $args): QueryInterface
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
     * @return \Muffin\Webservice\Datasource\Marshaller
     */
    public function marshaller(): Marshaller
    {
        return new Marshaller($this);
    }

    /**
     * Create a new entity + associated entities from an array.
     *
     * This is most useful when hydrating web service data back into entities.
     *
     * The hydrated entity will correctly do an insert/update based
     * on the primary key data existing in the database when the entity
     * is saved. Until the entity is saved, it will be a detached record.
     *
     * @param array $data The data to build an entity with.
     * @param array $options A list of options for the object hydration.
     * @return \Cake\Datasource\EntityInterface
     */
    public function newEntity(array $data = [], array $options = []): EntityInterface
    {
        $marshaller = $this->marshaller();

        return $marshaller->one($data, $options);
    }

    /**
     * {@inheritDoc}
     *
     * @return \Cake\Datasource\EntityInterface
     * @psalm-suppress InvalidReturnStatement
     * @psalm-suppress InvalidReturnType
     */
    public function newEmptyEntity(): EntityInterface
    {
        $class = $this->getResourceClass();

        return new $class([], ['source' => $this->getRegistryAlias()]);
    }

    /**
     * @inheritDoc
     */
    public function newEntities(array $data, array $options = []): array
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
     * @return \Cake\Datasource\EntityInterface
     */
    public function patchEntity(EntityInterface $entity, array $data, array $options = []): EntityInterface
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
     * @param iterable<\Cake\Datasource\EntityInterface> $entities the entities that will get the
     * data merged in
     * @param array $data list of arrays to be merged into the entities
     * @param array $options A list of options for the objects hydration.
     * @return array
     * @psalm-return array<array-key, \Cake\Datasource\EntityInterface>
     */
    public function patchEntities(iterable $entities, array $data, array $options = []): array
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
     * @return array<string, mixed>
     */
    public function implementedEvents(): array
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
     * Returns a RulesChecker object after modifying the one that was supplied.
     *
     * Subclasses should override this method in order to initialize the rules to be applied to
     * entities saved by this instance.
     *
     * @param \Cake\Datasource\RulesChecker $rules The rules object to be modified.
     * @return \Cake\Datasource\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        return $rules;
    }

    /**
     * Returns a handy representation of this endpoint
     *
     * @return array
     * @throws \Exception When the name of this endpoint could not be determined
     */
    public function __debugInfo(): array
    {
        $connectionName = '';
        if ($this->getConnection() !== null) {
            /** @psalm-suppress PossiblyNullReference getConnection cannot be null, as checked before entering this scope **/
            $connectionName = $this->getConnection()->configName();
        }

        return [
            'registryAlias' => $this->getRegistryAlias(),
            'alias' => $this->getAlias(),
            'endpoint' => $this->getName(),
            'resourceClass' => $this->getResourceClass(),
            'defaultConnection' => $this->defaultConnectionName(),
            'connectionName' => $connectionName,
            'inflector' => $this->getInflectionMethod(),
        ];
    }

    /**
     * Set the endpoint alias
     *
     * @param string $alias Alias for this endpoint
     * @return $this
     */
    public function setAlias(string $alias): Endpoint
    {
        $this->_alias = $alias;

        return $this;
    }

    /**
     * Get the endpoint alias
     *
     * @return string
     */
    public function getAlias(): string
    {
        if ($this->_alias === null) {
            $this->_alias = $this->getName();
        }

        return $this->_alias;
    }
}
