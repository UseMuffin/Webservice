<?php

namespace Muffin\Webservice;

use Cake\Core\ConventionsTrait;
use Cake\Datasource\AssociationInterface;
use Cake\Datasource\QueryInterface;
use Cake\Datasource\RepositoryInterface;
use Cake\Utility\Inflector;
use InvalidArgumentException;
use Muffin\Webservice\Model\EndpointRegistry;
use RuntimeException;

/**
 * An Association is a relationship established between two endpoints and is used
 * to configure and customize the way interconnected records are retrieved.
 */
abstract class Association implements AssociationInterface
{
    use ConventionsTrait;

    /**
     * Strategy name for associations that need to tell the webservice to include the association
     *
     * @var string
     */
    const STRATEGY_INCLUDE = 'include';

    /**
     * Strategy name for associations that are included in existing results.
     *
     * @var string
     */
    const STRATEGY_INCLUDED = 'included';

    /**
     * Strategy name to use joins for fetching associated records.
     *
     * @var string
     */
    const STRATEGY_SINGLEQUERY = 'single_query';

    /**
     * Strategy name for associations that require another query.
     *
     * @var string
     */
    const STRATEGY_QUERY = 'query';

    /**
     * Name given to the association, it usually represents the alias
     * assigned to the target associated endpoint
     *
     * @var string
     */
    protected $_name;

    /**
     * The class name of the target endpoint object
     *
     * @var string
     */
    protected $_className;

    /**
     * The field name in the owning side endpoint that is used to match with the foreignKey
     *
     * @var string|array
     */
    protected $_bindingKey;

    /**
     * The name of the field representing the foreign key to the endpoint to load
     *
     * @var string|array
     */
    protected $_foreignKey;

    /**
     * A list of conditions to be always included when fetching records from
     * the target association
     *
     * @var array
     */
    protected $_conditions = [];

    /**
     * Whether the records on the target endpoint are dependent on the source endpoint,
     * often used to indicate that records should be removed if the owning record in
     * the source endpoint is deleted.
     *
     * @var bool
     */
    protected $_dependent = false;

    /**
     * Whether or not cascaded deletes should also fire callbacks.
     *
     * @var string
     */
    protected $_cascadeCallbacks = false;

    /**
     * Source endpoint instance
     *
     * @var \Cake\Datasource\RepositoryInterface
     */
    protected $_sourceRepository;

    /**
     * Target endpoint instance
     *
     * @var \Cake\Datasource\RepositoryInterface
     */
    protected $_targetRepository;

    /**
     * The property name that should be filled with data from the target endpoint
     * in the source endpoint record.
     *
     * @var string
     */
    protected $_propertyName;

    /**
     * The property name that should be filled with data from the target endpoint
     * in the source endpoint record.
     *
     * @var string
     */
    protected $_path;

    /**
     * The default finder name to use for fetching rows from the target endpoint
     *
     * @var string
     */
    protected $_finder = 'all';

    /**
     * Valid strategies for this association. Subclasses can narrow this down.
     *
     * @var array
     */
    protected $_validStrategies = [
        self::STRATEGY_INCLUDED, self::STRATEGY_INCLUDE,
        self::STRATEGY_QUERY, self::STRATEGY_SINGLEQUERY
    ];

    /**
     * Constructor. Subclasses can override _options function to get the original
     * list of passed options if expecting any other special key
     *
     * @param string $alias The name given to the association
     * @param array $options A list of properties to be set on this object
     */
    public function __construct($alias, array $options = [])
    {
        $defaults = [
            'cascadeCallbacks',
            'className',
            'conditions',
            'dependent',
            'finder',
            'bindingKey',
            'foreignKey',
            'joinType',
            'propertyName',
            'sourceRepository',
            'targetRepository',
            'path'
        ];
        foreach ($defaults as $property) {
            if (isset($options[$property])) {
                $this->{'_' . $property} = $options[$property];
            }
        }

        if (empty($this->_className) && strpos($alias, '.')) {
            $this->_className = $alias;
        }

        list(, $name) = pluginSplit($alias);
        $this->_name = $name;

        $this->_options($options);

        if (!empty($options['strategy'])) {
            $this->strategy($options['strategy']);
        }
    }

    /**
     * Sets the name for this association. If no argument is passed then the current
     * configured name will be returned
     *
     * @param string|null $name Name to be assigned
     * @return string
     */
    public function name($name = null)
    {
        if ($name !== null) {
            $this->_name = $name;
        }
        return $this->_name;
    }

    /**
     * Sets whether or not cascaded deletes should also fire callbacks. If no
     * arguments are passed, the current configured value is returned
     *
     * @param bool|null $cascadeCallbacks cascade callbacks switch value
     * @return bool
     */
    public function cascadeCallbacks($cascadeCallbacks = null)
    {
        if ($cascadeCallbacks !== null) {
            $this->_cascadeCallbacks = $cascadeCallbacks;
        }
        return $this->_cascadeCallbacks;
    }

    /**
     * The class name of the target endpoint object
     *
     * @return string
     */
    public function className()
    {
        return $this->_className;
    }

    /**
     * Sets the endpoint instance for the source side of the association. If no arguments
     * are passed, the current configured endpoint instance is returned
     *
     * @param \Cake\Datasource\RepositoryInterface|null $repository the instance to be assigned as source side
     * @return \Cake\Datasource\RepositoryInterface
     */
    public function source(RepositoryInterface $repository = null)
    {
        if ($repository === null) {
            return $this->_sourceRepository;
        }
        return $this->_sourceRepository = $repository;
    }

    /**
     * Sets the endpoint instance for the target side of the association. If no arguments
     * are passed, the current configured endpoint instance is returned
     *
     * @param \Cake\Datasource\RepositoryInterface|null $repository the instance to be assigned as target side
     * @return \Cake\Datasource\RepositoryInterface
     */
    public function target(RepositoryInterface $repository = null)
    {
        if ($repository === null && $this->_targetRepository) {
            return $this->_targetRepository;
        }

        if ($repository !== null) {
            return $this->_targetRepository = $repository;
        }

        if (strpos($this->_className, '.')) {
            list($plugin) = pluginSplit($this->_className, true);
            $registryAlias = $plugin . $this->_name;
        } else {
            $registryAlias = $this->_name;
        }

        $config = [];
        if (!EndpointRegistry::exists($registryAlias)) {
            $config = ['className' => $this->_className];
        }
        $this->_targetRepository = EndpointRegistry::get($registryAlias, $config);

        return $this->_targetRepository;
    }

    /**
     * Sets a list of conditions to be always included when fetching records from
     * the target association. If no parameters are passed the current list is returned
     *
     * @param array|null $conditions list of conditions to be used
     * @see \Muffin\Webservice\Query::where() for examples on the format of the array
     * @return array
     */
    public function conditions($conditions = null)
    {
        if ($conditions !== null) {
            $this->_conditions = $conditions;
        }
        return $this->_conditions;
    }

    /**
     * Sets the name of the field representing the binding field with the target endpoint.
     * When not manually specified the primary key of the owning side endpoint is used.
     *
     * If no parameters are passed the current field is returned
     *
     * @param string|null $key the endpoint field to be used to link both endpoints together
     * @return string|array
     */
    public function bindingKey($key = null)
    {
        if ($key !== null) {
            $this->_bindingKey = $key;
        }

        if ($this->_bindingKey === null) {
            $this->_bindingKey = $this->isOwningSide($this->source()) ?
                $this->source()->primaryKey() :
                $this->target()->primaryKey();
        }

        return $this->_bindingKey;
    }

    /**
     * Sets the name of the field representing the foreign key to the target endpoint.
     * If no parameters are passed the current field is returned
     *
     * @param string|null $key the key to be used to link both endpoints together
     * @return string|array
     */
    public function foreignKey($key = null)
    {
        if ($key !== null) {
            $this->_foreignKey = $key;
        }
        return $this->_foreignKey;
    }

    /**
     * Sets whether the records on the target endpoint are dependent on the source endpoint.
     *
     * This is primarily used to indicate that records should be removed if the owning record in
     * the source endpoint is deleted.
     *
     * If no parameters are passed the current setting is returned.
     *
     * @param bool|null $dependent Set the dependent mode. Use null to read the current state.
     * @return bool
     */
    public function dependent($dependent = null)
    {
        if ($dependent !== null) {
            $this->_dependent = $dependent;
        }
        return $this->_dependent;
    }

    /**
     * Whether this association can be expressed directly in a query join
     *
     * @param array $options custom options key that could alter the return value
     * @return bool
     */
    public function canBeJoined(array $options = [])
    {
        $strategy = isset($options['strategy']) ? $options['strategy'] : $this->strategy();
        return $strategy == $this::STRATEGY_INCLUDED;
    }

    /**
     * Sets the property name that should be filled with data from the target endpoint
     * in the source endpoint record.
     * If no arguments are passed, the currently configured type is returned.
     *
     * @param string|null $name The name of the association property. Use null to read the current value.
     * @return string
     */
    public function property($name = null)
    {
        if ($name !== null) {
            $this->_propertyName = $name;
        }
        if ($name === null && !$this->_propertyName) {
            $this->_propertyName = $this->_propertyName();
            if (!$this->_sourceRepository) {
                stackTrace();
            }
            if (in_array($this->_propertyName, $this->_sourceRepository->schema()->columns())) {
                $msg = 'Association property name "%s" clashes with field of same name of endpoint "%s".' .
                    ' You should explicitly specify the "propertyName" option.';
                trigger_error(
                    sprintf($msg, $this->_propertyName, $this->_sourceRepository->endpoint()),
                    E_USER_WARNING
                );
            }
        }
        return $this->_propertyName;
    }

    /**
     * Returns default property name based on association name.
     *
     * @return string
     */
    protected function _propertyName()
    {
        list(, $name) = pluginSplit($this->_name);
        return Inflector::underscore($name);
    }

    /**
     * Sets the strategy name to be used to fetch associated records. Keep in mind
     * that some association types might not implement but a default strategy,
     * rendering any changes to this setting void.
     * If no arguments are passed, the currently configured strategy is returned.
     *
     * @param string|null $name The strategy type. Use null to read the current value.
     * @return string
     * @throws \InvalidArgumentException When an invalid strategy is provided.
     */
    public function strategy($name = null)
    {
        if ($name !== null) {
            if (!in_array($name, $this->_validStrategies)) {
                throw new InvalidArgumentException(
                    sprintf('Invalid strategy "%s" was provided', $name)
                );
            }
            $this->_strategy = $name;
        }
        return $this->_strategy;
    }

    /**
     * Sets the default finder to use for fetching rows from the target endpoint.
     * If no parameters are passed, it will return the currently configured
     * finder name.
     *
     * @param string|null $finder the finder name to use
     * @return string
     */
    public function finder($finder = null)
    {
        if ($finder !== null) {
            $this->_finder = $finder;
        }
        return $this->_finder;
    }

    /**
     * Override this function to initialize any concrete association class, it will
     * get passed the original list of options used in the constructor
     *
     * @param array $options List of options used for initialization
     * @return void
     */
    protected function _options(array $options)
    {
    }

    /**
     * Alters a Query object to include the associated target endpoint data in the final
     * result
     *
     * The options array accept the following keys:
     *
     * - includeFields: Whether to include target model fields in the result or not
     * - foreignKey: The name of the field to use as foreign key, if false none
     *   will be used
     * - conditions: array with a list of conditions to filter the join with, this
     *   will be merged with any conditions originally configured for this association
     * - fields: a list of fields in the target endpoint to include in the result
     * - type: The type of join to be used (e.g. INNER)
     *   the records found on this association
     * - aliasPath: A dot separated string representing the path of association names
     *   followed from the passed query main endpoint to this association.
     * - propertyPath: A dot separated string representing the path of association
     *   properties to be followed from the passed query main entity to this
     *   association
     * - joinType: The SQL join type to use in the query.
     * - negateMatch: Will append a condition to the passed query for excluding matches.
     *   with this association.
     *
     * @param \Cake\Datasource\QueryInterface $query the query to be altered to include the target endpoint data
     * @param array $options Any extra options or overrides to be taken in account
     * @return void
     * @throws \RuntimeException if the query builder passed does not return a query
     * object
     */
    public function attachTo(QueryInterface $query, array $options = [])
    {
        $target = $this->target();

        $endpoint = $target->endpoint();
        $options += [
            'includeFields' => true,
            'foreignKey' => $this->foreignKey(),
            'conditions' => [],
            'fields' => [],
            'endpoint' => $endpoint,
            'finder' => $this->finder()
        ];

        if (!empty($options['foreignKey'])) {
            $foreignKey = (array)$options['foreignKey'];
            $bindingKey = (array)$this->bindingKey();

            if (count($foreignKey) !== count($bindingKey)) {
                $msg = 'Cannot match provided foreignKey for "%s", got "(%s)" but expected foreign key for "(%s)"';
                throw new RuntimeException(sprintf(
                    $msg,
                    $this->_name,
                    implode(', ', $foreignKey),
                    implode(', ', $bindingKey)
                ));
            }
        }

        list($finder, $opts) = $this->_extractFinder($options['finder']);
        $dummy = $this
            ->find($finder, $opts)
            ->eagerLoaded(true);
        if (!empty($options['queryBuilder'])) {
            $dummy = $options['queryBuilder']($dummy);
            if (!($dummy instanceof Query)) {
                throw new RuntimeException(sprintf(
                    'Query builder for association "%s" did not return a query',
                    $this->name()
                ));
            }
        }

        $dummy->where($options['conditions']);
        $this->_dispatchBeforeFind($dummy);

        $options['conditions'] = $dummy->clause('where');

        $this->_bindNewAssociations($query, $dummy, $options);
    }

    /**
     * Correctly nests a result row associated values into the correct array keys inside the
     * source results.
     *
     * @param array $row The row to transform
     * @param string $nestKey The array key under which the results for this association
     *   should be found
     * @param bool $joined Whether or not the row is a result of a direct join
     *   with this association
     * @return array
     */
    public function transformRow($row, $nestKey, $joined)
    {
        $sourceAlias = $this->source()->alias();
        $nestKey = $nestKey ?: $this->_name;
        if (isset($row[$sourceAlias])) {
            $row[$sourceAlias][$this->property()] = $row[$nestKey];
            unset($row[$nestKey]);
        }
        return $row;
    }

    /**
     * Returns a modified row after appending a property for this association
     * with the default empty value according to whether the association was
     * joined or fetched externally.
     *
     * @param array $row The row to set a default on.
     * @param bool $joined Whether or not the row is a result of a direct join
     *   with this association
     * @return array
     */
    public function defaultRowValue($row, $joined)
    {
        $sourceAlias = $this->source()->alias();
        if (isset($row[$sourceAlias])) {
            $row[$sourceAlias][$this->property()] = null;
        }
        return $row;
    }

    /**
     * Proxies the finding operation to the target endpoint's find method
     * and modifies the query accordingly based of this association
     * configuration
     *
     * @param string|array|null $type the type of query to perform, if an array is passed,
     *   it will be interpreted as the `$options` parameter
     * @param array $options The options to for the find
     * @see \Cake\Datasource\RepositoryInterface::find()
     * @return \Cake\Datasource\QueryInterface
     */
    public function find($type = null, array $options = [])
    {
        $type = $type ?: $this->finder();
        list($type, $opts) = $this->_extractFinder($type);
        return $this->target()
            ->find($type, $options + $opts)
            ->where($this->conditions());
    }

    /**
     * Proxies the update operation to the target endpoint's updateAll method
     *
     * @param array $fields A hash of field => new value.
     * @param mixed $conditions Conditions to be used, accepts anything Query::where()
     * can take.
     * @see \Cake\Datasource\RepositoryInterface::updateAll()
     * @return bool Success Returns true if one or more rows are affected.
     */
    public function updateAll($fields, $conditions)
    {
        $target = $this->target();
        $expression = $target->query()
            ->where($this->conditions())
            ->where($conditions)
            ->clause('where');
        return $target->updateAll($fields, $expression);
    }

    /**
     * Proxies the delete operation to the target endpoint's deleteAll method
     *
     * @param mixed $conditions Conditions to be used, accepts anything Query::where()
     * can take.
     * @return bool Success Returns true if one or more rows are affected.
     * @see \Cake\Datasource\RepositoryInterface::deleteAll()
     */
    public function deleteAll($conditions)
    {
        $target = $this->target();
        $expression = $target->query()
            ->where($this->conditions())
            ->where($conditions)
            ->clause('where');
        return $target->deleteAll($expression);
    }

    /**
     * Triggers beforeFind on the target endpoint for the query this association is
     * attaching to
     *
     * @param \Muffin\Webservice\Query $query the query this association is attaching itself to
     * @return void
     */
    protected function _dispatchBeforeFind($query)
    {
        $query->triggerBeforeFind();
    }

    /**
     * Helper method to infer the requested finder and its options.
     *
     * Returns the inferred options from the finder $type.
     *
     * ### Examples:
     *
     * The following will call the finder 'translations' with the value of the finder as its options:
     * $query->contain(['Comments' => ['finder' => ['translations']]]);
     * $query->contain(['Comments' => ['finder' => ['translations' => []]]]);
     * $query->contain(['Comments' => ['finder' => ['translations' => ['locales' => ['en_US']]]]]);
     *
     * @param string|array $finderData The finder name or an array having the name as key
     * and options as value.
     * @return array
     */
    protected function _extractFinder($finderData)
    {
        $finderData = (array)$finderData;

        if (is_numeric(key($finderData))) {
            return [current($finderData), []];
        }

        return [key($finderData), current($finderData)];
    }

    /**
     * Proxies property retrieval to the target endpoint. This is handy for getting this
     * association's associations
     *
     * @param string $property the property name
     * @return \Muffin\Webservice\Association
     * @throws \RuntimeException if no association with such name exists
     */
    public function __get($property)
    {
        return $this->target()->{$property};
    }

    /**
     * Proxies the isset call to the target endpoint. This is handy to check if the
     * target endpoint has another association with the passed name
     *
     * @param string $property the property name
     * @return bool true if the property exists
     */
    public function __isset($property)
    {
        return isset($this->target()->{$property});
    }

    /**
     * Proxies method calls to the target endpoint.
     *
     * @param string $method name of the method to be invoked
     * @param array $argument List of arguments passed to the function
     * @return mixed
     * @throws \BadMethodCallException
     */
    public function __call($method, $argument)
    {
        return call_user_func_array([$this->target(), $method], $argument);
    }

    /**
     * Applies all attachable associations to `$query` out of the containments found
     * in the `$surrogate` query.
     *
     * Copies all contained associations from the `$surrogate` query into the
     * passed `$query`. Containments are altered so that they respect the associations
     * chain from which they originated.
     *
     * @param \Cake\ORM\Query $query the query that will get the associations attached to
     * @param \Cake\ORM\Query $surrogate the query having the containments to be attached
     * @param array $options options passed to the method `attachTo`
     * @return void
     */
    protected function _bindNewAssociations($query, $surrogate, $options)
    {
        $loader = $surrogate->eagerLoader();
        $contain = $loader->contain();
        $matching = $loader->matching();

        if (!$contain && !$matching) {
            return;
        }

        $newContain = [];
        foreach ($contain as $alias => $value) {
            $newContain[$options['aliasPath'] . '.' . $alias] = $value;
        }

        $eagerLoader = $query->eagerLoader();
        $eagerLoader->contain($newContain);

        foreach ($matching as $alias => $value) {
            $eagerLoader->matching(
                $options['aliasPath'] . '.' . $alias,
                $value['queryBuilder'],
                $value
            );
        }
    }
}
