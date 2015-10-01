<?php

namespace Muffin\Webservice\Model;

use Cake\Core\App;
use Cake\Datasource\EntityInterface;
use Cake\Datasource\Exception\InvalidPrimaryKeyException;
use Cake\Datasource\RepositoryInterface;
use Cake\Network\Exception\NotImplementedException;
use Cake\Utility\Inflector;
use Muffin\Webservice\Exception\MissingResourceClassException;
use Muffin\Webservice\Webservice\WebserviceInterface;
use Muffin\Webservice\WebserviceQuery;

class Endpoint implements RepositoryInterface
{

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
     * @var \Muffin\Webservice\Webservice\WebserviceInterface
     */
    protected $_webservice;
    protected $_alias;

    /**
     * Initializes a new instance
     *
     * The $config array understands the following keys:
     *
     * - endpoint: Name of the endpoint to represent
     * - alias: Alias to be assigned to this endpoint (default to endpoint name)
     * - connection: The connection instance to use
     * - resourceClass: The fully namespaced class name of the resource class that will
     *   represent rows in this endpoint.
     * - schema: A \Muffin\Webservice\Schema object or an array that can be
     *   passed to it.
     * - eventManager: An instance of an event manager to use for internal events
     * - behaviors: A BehaviorRegistry. Generally not used outside of tests.
     * - associations: An AssociationCollection instance.
     * - validator: A Validator instance which is assigned as the "default"
     *   validation set, or an associative array, where key is the name of the
     *   validation set and value the Validator instance.
     *
     * @param array $config List of options for this endpoint
     */
    public function __construct(array $config = [])
    {
        if (!empty($config['alias'])) {
            $this->alias($config['alias']);
        }
        if (!empty($config['endpoint'])) {
            $this->endpoint($config['endpoint']);
        }
        if (!empty($config['connection'])) {
            $this->connection($config['connection']);
        }
        if (!empty($config['schema'])) {
            $this->schema($config['schema']);
        }
        if (!empty($config['resourceClass'])) {
            $this->resourceClass($config['resourceClass']);
        }

        $this->initialize($config);
    }


    /**
     * Initialize a endpoint instance. Called after the constructor.
     *
     * You can use this method to define associations, attach behaviors
     * define validation and do any other initialization logic you need.
     *
     * ```
     *  public function initialize(array $config)
     *  {
     *      $this->belongsTo('Users');
     *      $this->belongsToMany('Tagging.Tags');
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
     * Returns the endpoint alias or sets a new one
     *
     * @param string|null $alias the new endpoint alias
     * @return string
     */
    public function alias($alias = null)
    {
        if ($alias === null) {
            return $this->_alias;
        }

        $this->_alias = $alias;

        return $this;
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
            if (empty($endpoint)) {
                $endpoint = $this->alias();
            }
            $this->_endpoint = Inflector::underscore($endpoint);
        }
        return $this->_endpoint;
    }

    /**
     * Returns the schema table object describing this endpoint's properties.
     *
     * If an \Muffin\Webservice\Schema is passed, it will be used for this endpoint
     * instead of the default one.
     *
     * If an array is passed, a new \Muffin\Webservice\Schema will be constructed
     * out of it and used as the schema for this endpoint.
     *
     * @param array|\Muffin\Webservice\Schema|null $schema New schema to be used for this endpoint
     * @return \Muffin\Webservice\Schema
     */
    public function schema($schema = null)
    {
        if ($schema === null) {
            return $this->_schema;
        }

        return $this->_schema = $schema;
    }

    /**
     * Returns the class used to hydrate resources for this endpoint or sets
     * a new one
     *
     * @param string|null $name the name of the class to use
     * @throws \Cake\ORM\Exception\MissingEntityException when the entity class cannot be found
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

    public function connection($connection = null)
    {
        if ($connection === null) {
            return $this->_connection;
        }

        return $this->_connection = $connection;
    }

    /**
     * @param WebserviceInterface|string|null $webservice
     * @return $this|WebserviceInterface
     */
    public function webservice($webservice = null)
    {
        if ((is_string($webservice)) || ($this->_webservice === null)) {
            if ($webservice === null) {
                $webservice = $this->endpoint();
            }

            $this->_webservice = $this->connection()->webservice($webservice);

            return $this->_webservice;
        }
        if ($webservice === null) {
            return $this->_webservice;
        }

        $this->_webservice = $webservice;

        return $this;
    }

    /**
     * Test to see if a Repository has a specific field/column.
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
            $key = (array)$this->schema()->primaryKey();
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
            $schema = $this->schema();
            $primary = (array)$this->primaryKey();
            $this->_displayField = array_shift($primary);
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
     * @return \Muffin\Webservice\WebserviceQuery
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
     * @param \Muffin\Webservice\WebserviceQuery $query The query to find with
     * @param array $options The options to use for the find
     * @return \Muffin\Webservice\WebserviceQuery The query builder
     */
    public function findAll(WebserviceQuery $query, array $options)
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
     * @param \Muffin\Webservice\WebserviceQuery $query The query to find with
     * @param array $options The options for the find
     * @return \Muffin\Webservice\WebserviceQuery The query builder
     */
    public function findList(WebserviceQuery $query, array $options)
    {
        $options += [
            'keyField' => $this->primaryKey(),
            'valueField' => $this->displayField(),
            'groupField' => null
        ];

        if (isset($options['idField'])) {
            $options['keyField'] = $options['idField'];
            unset($options['idField']);
            trigger_error('Option "idField" is deprecated, use "keyField" instead.', E_USER_WARNING);
        }

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
     * @see RepositoryInterface::find()
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

        $query = $this->find($finder, $options)->conditions($conditions);

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
     * Creates a new Query instance for this repository
     *
     * @return WebserviceQuery
     */
    public function query()
    {
        return new WebserviceQuery($this->webservice(), $this);
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
        // TODO: Implement updateAll() method.
    }

    /**
     * Delete all matching records.
     *
     * Deletes all records matching the provided conditions.
     *
     * This method will *not* trigger beforeDelete/afterDelete events. If you
     * need those first load a collection of records and delete them.
     *
     * This method will *not* execute on associations' `cascade` attribute.
     *
     * @param mixed $conditions Conditions to be used, accepts anything Query::where()
     * can take.
     * @return int Count Returns the affected rows.
     * @see RepositoryInterface::delete()
     */
    public function deleteAll($conditions)
    {
        // TODO: Implement deleteAll() method.
    }

    /**
     * Returns true if there is any record in this repository matching the specified
     * conditions.
     *
     * @param array|\ArrayAccess $conditions list of conditions to pass to the query
     * @return bool
     */
    public function exists($conditions)
    {
        // TODO: Implement exists() method.
    }

    /**
     * Persists an resource based on the fields that are marked as dirty and
     * returns the same resource after a successful save or false in case
     * of any error.
     *
     * @param \Cake\Datasource\EntityInterface $resource the resource to be saved
     * @param array|\ArrayAccess $options The options to use when saving.
     * @return \Cake\Datasource\EntityInterface|bool
     */
    public function save(EntityInterface $resource, $options = [])
    {
        // TODO: Implement save() method.
    }

    /**
     * Delete a single resource.
     *
     * Deletes an resource and possibly related associations from the webservice
     * based on the 'dependent' option used when defining the association.
     *
     * @param \Cake\Datasource\EntityInterface $resource The resource to remove.
     * @param array|\ArrayAccess $options The options for the delete.
     * @return bool success
     */
    public function delete(EntityInterface $resource, $options = [])
    {
        // TODO: Implement delete() method.
    }

    /**
     * Create a new resource + associated entities from an array.
     *
     * This is most useful when hydrating request data back into entities.
     * For example, in your controller code:
     *
     * ```
     * $article = $this->Articles->newEntity($this->request->data());
     * ```
     *
     * The hydrated resource will correctly do an insert/update based
     * on the primary key data existing in the webservice when the resource
     * is saved. Until the resource is saved, it will be a detached record.
     *
     * @param array|null $data The data to build an resource with.
     * @param array $options A list of options for the object hydration.
     * @return \Cake\Datasource\EntityInterface
     */
    public function newEntity($data = null, array $options = [])
    {
        // TODO: Implement newEntity() method.
    }

    /**
     * Create a list of entities + associated entities from an array.
     *
     * This is most useful when hydrating request data back into entities.
     * For example, in your controller code:
     *
     * ```
     * $articles = $this->Articles->newEntities($this->request->data());
     * ```
     *
     * The hydrated entities can then be iterated and saved.
     *
     * @param array $data The data to build an resource with.
     * @param array $options A list of options for the objects hydration.
     * @return array An array of hydrated records.
     */
    public function newEntities(array $data, array $options = [])
    {
        // TODO: Implement newEntities() method.
    }

    /**
     * Merges the passed `$data` into `$resource` respecting the accessible
     * fields configured on the resource. Returns the same resource after being
     * altered.
     *
     * This is most useful when editing an existing resource using request data:
     *
     * ```
     * $article = $this->Articles->patchEntity($article, $this->request->data());
     * ```
     *
     * @param \Cake\Datasource\EntityInterface $resource the resource that will get the
     * data merged in
     * @param array $data key value list of fields to be merged into the resource
     * @param array $options A list of options for the object hydration.
     * @return \Cake\Datasource\EntityInterface
     */
    public function patchEntity(EntityInterface $resource, array $data, array $options = [])
    {
        throw new NotImplementedException('patchEntity has not been implemented yet');
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
     * @return array
     */
    public function patchEntities($entities, array $data, array $options = [])
    {
        throw new NotImplementedException('patchEntities has not been implemented yet');
    }

    /**
     * Calls a finder method directly and applies it to the passed query,
     * if no query is passed a new one will be created and returned
     *
     * @param string $type name of the finder to be called
     * @param \Muffin\Webservice\WebserviceQuery $query The query object to apply the finder options to
     * @param array $options List of options to pass to the finder
     * @return \Muffin\Webservice\WebserviceQuery
     */
    public function callFinder($type, WebserviceQuery $query, array $options = [])
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

    public static function defaultConnectionName()
    {
        $namespaceParts = explode('\\', get_called_class());
        $plugin = array_slice(array_reverse($namespaceParts), 3, 2);

        return Inflector::underscore(current($plugin));
    }

    public function __debugInfo()
    {
        return [
            'alias' => $this->alias(),
            'connection' => $this->connection(),
            'endpoint' => $this->endpoint(),
            'resourceClass' => $this->resourceClass()
        ];
    }
}
