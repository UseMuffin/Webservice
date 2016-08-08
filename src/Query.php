<?php

namespace Muffin\Webservice;

use ArrayObject;
use Cake\Core\App;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Datasource\QueryInterface;
use Cake\Datasource\QueryTrait;
use Cake\Utility\Hash;
use DebugKit\DebugTimer;
use IteratorAggregate;
use Muffin\Webservice\EagerLoader;
use Muffin\Webservice\Model\Endpoint;
use Muffin\Webservice\Webservice\WebserviceInterface;

class Query implements QueryInterface, IteratorAggregate
{

    use QueryTrait;

    const ACTION_CREATE = 1;
    const ACTION_READ = 2;
    const ACTION_UPDATE = 3;
    const ACTION_DELETE = 4;

    /**
     * Indicates that the operation should append to the list
     *
     * @var int
     */
    const APPEND = 0;

    /**
     * Indicates that the operation should prepend to the list
     *
     * @var int
     */
    const PREPEND = 1;

    /**
     * Indicates that the operation should overwrite the list
     *
     * @var bool
     */
    const OVERWRITE = true;

    /**
     * True if the beforeFind event has already been triggered for this query
     *
     * @var bool
     */
    protected $_beforeFindFired = false;

    /**
     * Indicates whether internal state of this query was changed, this is used to
     * discard internal cached objects such as the transformed query or the reference
     * to the executed statement.
     *
     * @var bool
     */
    protected $_dirty = false;

    /**
     * Parts being used to in the query
     *
     * @var array
     */
    protected $_parts = [
        'order' => [],
        'set' => [],
        'where' => []
    ];

    /**
     * Instance of the webservice to use
     *
     * @var \Muffin\Webservice\Webservice\WebserviceInterface
     */
    protected $_webservice;

    /**
     * The results from the webservice
     *
     * @var ResultSet
     */
    protected $__resultSet;

    /**
     * Whether to hydrate results into entity objects
     *
     * @var bool
     */
    protected $_hydrate = true;

    /**
     * Instance of a class responsible for storing association containments and
     * for eager loading them when this query is executed
     *
     * @var \Muffin\Webservice\EagerLoader
     */
    protected $_eagerLoader;

    /**
     * Construct the query
     *
     * @param WebserviceInterface $webservice The webservice to use
     * @param Endpoint $endpoint The endpoint this is executed from
     */
    public function __construct(WebserviceInterface $webservice, Endpoint $endpoint)
    {
        $this->webservice($webservice);
        $this->endpoint($endpoint);
    }

    /**
     * Mark the query as create
     *
     * @return $this
     */
    public function create()
    {
        $this->action(self::ACTION_CREATE);

        return $this;
    }

    /**
     * Mark the query as read
     *
     * @return $this
     */
    public function read()
    {
        $this->action(self::ACTION_READ);

        return $this;
    }

    /**
     * Mark the query as update
     *
     * @return $this
     */
    public function update()
    {
        $this->action(self::ACTION_UPDATE);

        return $this;
    }

    /**
     * Mark the query as delete
     *
     * @return $this
     */
    public function delete()
    {
        $this->action(self::ACTION_DELETE);

        return $this;
    }

    /**
     * Returns any data that was stored in the specified clause.
     *
     * - where: QueryExpression, returns null when not set
     * - order: OrderByExpression, returns null when not set
     * - limit: integer or QueryExpression, null when not set
     * - offset: integer or QueryExpression, null when not set
     *
     * @param string $name name of the clause to be returned
     *
     * @return mixed
     */
    public function clause($name)
    {
        if (isset($this->_parts[$name])) {
            return $this->_parts[$name];
        }

        return null;
    }

    /**
     * Set the endpoint to be used
     *
     * @param \Muffin\Webservice\Model\Endpoint|null $endpoint The endpoint to use
     *
     * @return \Muffin\Webservice\Model\Endpoint|$this
     */
    public function endpoint(Endpoint $endpoint = null)
    {
        if ($endpoint === null) {
            return $this->repository();
        }

        $this->repository($endpoint);

        return $this;
    }

    /**
     * Set the webservice to be used
     *
     * @param null|\Muffin\Webservice\Webservice\WebserviceInterface $webservice The webservice to use
     *
     * @return \Muffin\Webservice\Webservice\WebserviceInterface|self
     */
    public function webservice(WebserviceInterface $webservice = null)
    {
        if ($webservice === null) {
            return $this->_webservice;
        }

        $this->_webservice = $webservice;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function find($finder, array $options = [])
    {
        return $this->repository()->callFinder($finder, $this, $options);
    }

    /**
     * Marks a query as dirty, removing any preprocessed information
     * from in memory caching such as previous results
     *
     * @return void
     */
    protected function _dirty()
    {
        $this->_results = null;
        $this->_resultsCount = null;
    }

    /**
     * Get the first result from the executing query or raise an exception.
     *
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When there is no first record.
     *
     * @return mixed The first result from the ResultSet.
     */
    public function firstOrFail()
    {
        $entity = $this->first();
        if ($entity) {
            return $entity;
        }
        throw new RecordNotFoundException(sprintf(
            'Record not found in endpoint "%s"',
            $this->repository()->endpoint()
        ));
    }

//    /**
//     * Alias a field with the endpoint's current alias.
//     *
//     * @param string $field The field to alias.
//     * @param null $alias Not being used
//     *
//     * @return string The field prefixed with the endpoint alias.
//     */
//    public function aliasField($field, $alias = null)
//    {
//        return [$field => $field];
//    }

    /**
     * Apply conditions to the query
     *
     * @param array|null $conditions The conditions to apply
     * @param array|null $types Not used
     * @param bool $overwrite Whether to overwrite the current conditions
     *
     * @return $this|array
     */
    public function where($conditions = null, $types = [], $overwrite = false)
    {
        if ($conditions === null) {
            return $this->clause('where');
        }

        if ($overwrite) {
            $this->_parts['where'] = $conditions;

            return $this;
        }
        if (count($conditions) === 0) {
            return $this;
        }

        if (($this->isConditionSet($this->_parts['where'])) && ($this->isConditionSet($conditions))) {
            $this->_parts['where'] = array_merge($this->_parts['where'], $conditions);

            return $this;
        }
        if ((!$this->isConditionSet($this->_parts['where'])) && (!$this->isConditionSet($conditions))) {
            $this->_parts['where'] = Hash::merge($this->clause('where'), $conditions);

            return $this;
        }

        $regularConditions = (!$this->isConditionSet($conditions)) ? $conditions : $this->_parts['where'];
        $conditionSet = ($this->isConditionSet($this->_parts['where'])) ? $this->_parts['where'] : $conditions;

        $this->_parts['where'] = $this->mergeConditionsIntoSet($regularConditions, $conditionSet);

        return $this;
    }

    /**
     * Charge this query's action
     *
     * @param int|null $action Action to use
     *
     * @return $this|int
     */
    public function action($action = null)
    {
        if ($action === null) {
            return $this->clause('action');
        }

        $this->_parts['action'] = $action;

        return $this;
    }

    /**
     * Set the page of results you want.
     *
     * This method provides an easier to use interface to set the limit + offset
     * in the record set you want as results. If empty the limit will default to
     * the existing limit clause, and if that too is empty, then `25` will be used.
     *
     * Pages should start at 1.
     *
     * @param int $page The page number you want.
     * @param int $limit The number of rows you want in the page. If null
     *  the current limit clause will be used.
     *
     * @return $this
     */
    public function page($page = null, $limit = null)
    {
        if ($page === null) {
            return $this->clause('page');
        }
        if ($limit !== null) {
            $this->limit($limit);
        }

        $this->_parts['page'] = $page;

        return $this;
    }

    /**
     * Sets the number of records that should be retrieved from the webservice,
     * accepts an integer or an expression object that evaluates to an integer.
     * In some webservices, this operation might not be supported or will require
     * the query to be transformed in order to limit the result set size.
     *
     * ### Examples
     *
     * ```
     * $query->limit(10) // generates LIMIT 10
     * ```
     *
     * @param int $limit number of records to be returned
     *
     * @return $this
     */
    public function limit($limit = null)
    {
        if ($limit === null) {
            return $this->clause('limit');
        }

        $this->_parts['limit'] = $limit;

        return $this;
    }

    /**
     * Set fields to save in resources
     *
     * @param array|null $fields The field to set
     *
     * @return $this|array
     */
    public function set($fields = null)
    {
        if ($fields === null) {
            return $this->clause('set');
        }

        if (!in_array($this->action(), [self::ACTION_CREATE, self::ACTION_UPDATE])) {
            throw new \UnexpectedValueException(__('The action of this query needs to be either create update'));
        }

        $this->_parts['set'] = $fields;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function offset($num)
    {
        $this->_parts['offset'] = $num;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function order($fields, $overwrite = false)
    {
        $this->_parts['order'] = (!$overwrite) ? Hash::merge($this->clause('order'), $fields) : $fields;

        return $this;
    }

    /**
     * {@inheritDoc}
     *
     * Populates or adds parts to current query clauses using an array.
     * This is handy for passing all query clauses at once. The option array accepts:
     *
     * - conditions: Maps to the where method
     * - limit: Maps to the limit method
     * - order: Maps to the order method
     * - offset: Maps to the offset method
     * - group: Maps to the group method
     * - having: Maps to the having method
     * - contain: Maps to the contain options for eager loading
     * - page: Maps to the page method
     *
     * ### Example:
     *
     * ```
     * $query->applyOptions([
     *   'fields' => ['id', 'name'],
     *   'conditions' => [
     *     'created >=' => '2013-01-01'
     *   ],
     *   'limit' => 10
     * ]);
     * ```
     *
     * Is equivalent to:
     *
     * ```
     *  $query
     *  ->select(['id', 'name'])
     *  ->where(['created >=' => '2013-01-01'])
     *  ->limit(10)
     * ```
     */
    public function applyOptions(array $options)
    {
        $valid = [
            'conditions' => 'where',
            'order' => 'order',
            'limit' => 'limit',
            'offset' => 'offset',
            'group' => 'group',
            'having' => 'having',
            'contain' => 'contain',
            'page' => 'page',
        ];

        ksort($options);
        foreach ($options as $option => $values) {
            if (isset($valid[$option], $values)) {
                $this->{$valid[$option]}($values);
            } else {
                $this->_options[$option] = $values;
            }
        }

        return $this;
    }

    /**
     * Sets the instance of the eager loader class to use for loading associations
     * and storing containments. If called with no arguments, it will return the
     * currently configured instance.
     *
     * @param \Muffin\Webservice\EagerLoader|null $instance The eager loader to use. Pass null
     *   to get the current eagerloader.
     * @return \Muffin\Webservice\EagerLoader|$this
     */
    public function eagerLoader(EagerLoader $instance = null)
    {
        if ($instance === null) {
            if ($this->_eagerLoader === null) {
                $this->_eagerLoader = new EagerLoader;
            }
            return $this->_eagerLoader;
        }
        $this->_eagerLoader = $instance;
        return $this;
    }

    /**
     * Sets the list of associations that should be eagerly loaded along with this
     * query. The list of associated endpoints passed must have been previously set as
     * associations using the Endpoint API.
     *
     * ### Example:
     *
     * ```
     *  // Bring articles' author information
     *  $query->contain('Author');
     *
     *  // Also bring the category and tags associated to each article
     *  $query->contain(['Category', 'Tag']);
     * ```
     *
     * Associations can be arbitrarily nested using dot notation or nested arrays,
     * this allows this object to calculate joins or any additional queries that
     * must be executed to bring the required associated data.
     *
     * ### Example:
     *
     * ```
     *  // Eager load the product info, and for each product load other 2 associations
     *  $query->contain(['Product' => ['Manufacturer', 'Distributor']);
     *
     *  // Which is equivalent to calling
     *  $query->contain(['Products.Manufactures', 'Products.Distributors']);
     *
     *  // For an author query, load his region, state and country
     *  $query->contain('Regions.States.Countries');
     * ```
     *
     * It is possible to control the conditions and fields selected for each of the
     * contained associations:
     *
     * ### Example:
     *
     * ```
     *  $query->contain(['Tags' => function ($q) {
     *      return $q->where(['Tags.is_popular' => true]);
     *  }]);
     *
     *  $query->contain(['Products.Manufactures' => function ($q) {
     *      return $q->select(['name'])->where(['Manufactures.active' => true]);
     *  }]);
     * ```
     *
     * Each association might define special options when eager loaded, the allowed
     * options that can be set per association are:
     *
     * - foreignKey: Used to set a different field to match both endpoints, if set to false
     *   no join conditions will be generated automatically. `false` can only be used on
     *   joinable associations and cannot be used with hasMany or belongsToMany associations.
     * - fields: An array with the fields that should be fetched from the association
     * - queryBuilder: Equivalent to passing a callable instead of an options array
     *
     * ### Example:
     *
     * ```
     * // Set options for the hasMany articles that will be eagerly loaded for an author
     * $query->contain([
     *   'Articles' => [
     *     'fields' => ['title', 'author_id']
     *   ]
     * ]);
     * ```
     *
     * When containing associations, it is important to include foreign key columns.
     * Failing to do so will trigger exceptions.
     *
     * ```
     * // Use special join conditions for getting an Articles's belongsTo 'authors'
     * $query->contain([
     *   'Authors' => [
     *     'foreignKey' => false,
     *     'queryBuilder' => function ($q) {
     *       return $q->where(...); // Add full filtering conditions
     *     }
     *   ]
     * ]);
     * ```
     *
     * If called with no arguments, this function will return an array with
     * with the list of previously configured associations to be contained in the
     * result.
     *
     * If called with an empty first argument and $override is set to true, the
     * previous list will be emptied.
     *
     * @param array|string|null $associations list of endpoint aliases to be queried
     * @param bool $override whether override previous list with the one passed
     * defaults to merging previous list with the new one.
     * @return array|$this
     */
    public function contain($associations = null, $override = false)
    {
        $loader = $this->eagerLoader();
        if ($override === true) {
            $loader->clearContain();
            $this->_dirty();
        }

        if ($associations === null) {
            return $loader->contain();
        }

        $result = $loader->contain($associations);
//        $this->_addAssociationsToTypeMap($this->repository(), $this->typeMap(), $result);
        return $this;
    }

    /**
     * Returns the total amount of results for this query
     *
     * @return bool|int
     */
    public function count()
    {
        if ($this->action() !== self::ACTION_READ) {
            return false;
        }

        if (!$this->__resultSet) {
            $this->_execute();
        }

        return $this->__resultSet->total();
    }

    /**
     * Returns the first result out of executing this query, if the query has not been
     * executed before, it will set the limit clause to 1 for performance reasons.
     *
     * ### Example:
     *
     * ```
     * $singleUser = $query->first();
     * ```
     *
     * @return mixed the first result from the ResultSet
     */
    public function first()
    {
        if (!$this->__resultSet) {
            $this->limit(1);
        }

        return $this->all()->first();
    }

    /**
     * Toggle hydrating entities.
     *
     * If set to false array results will be returned
     *
     * @param bool|null $enable Use a boolean to set the hydration mode.
     *   Null will fetch the current hydration mode.
     * @return bool|$this A boolean when reading, and $this when setting the mode.
     */
    public function hydrate($enable = null)
    {
        if ($enable === null) {
            return $this->_hydrate;
        }

        $this->_dirty();
        $this->_hydrate = (bool)$enable;
        return $this;
    }

    /**
     * Trigger the beforeFind event on the query's repository object.
     *
     * Will not trigger more than once, and only for select queries.
     *
     * @return void
     */
    public function triggerBeforeFind()
    {
        if (!$this->_beforeFindFired && $this->action() === self::ACTION_READ) {
            $endpoint = $this->repository();
            $this->_beforeFindFired = true;
            $endpoint->dispatchEvent('Model.beforeFind', [
                $this,
                new ArrayObject($this->_options),
                !$this->eagerLoaded()
            ]);
        }
    }

    /**
     * Execute the query
     *
     * @return \Traversable
     */
    public function execute()
    {
        return $this->_execute();
    }

    public function isConditionSet($conditions)
    {
        if (count($conditions) === 0) {
            return false;
        }

        return array_keys($conditions) === range(0, count($conditions) - 1);
    }

    public function mergeConditionsIntoSet($regularConditions, $set)
    {
        foreach ($set as &$conditions) {
            $conditions = Hash::merge($conditions, $regularConditions);
        }

        return $set;
    }

    /**
     * Executes this query and returns a traversable object containing the results
     *
     * @return \Traversable
     */
    protected function _execute()
    {
        $this->triggerBeforeFind();
        if ($this->__resultSet) {
            $decorator = $this->_decoratorClass();
            return new $decorator($this->__resultSet);
        }

        $start = microtime(true);
        $result = $this->_webservice->execute($this);
        if (!$result instanceof WebserviceResultSetInterface) {
            return $result;
        }

        QueryLog::log(clone $this, (microtime(true) - $start) * 1000, $result->total());

        $resultSet = $this->eagerLoader()->loadExternal($this, $result);

        return $this->__resultSet = new ResultSet($this, $resultSet, $resultSet->total());
    }

    /**
     * Return a handy representation of the query
     *
     * @return array
     */
    public function __debugInfo()
    {
        $eagerLoader = $this->eagerLoader();
        return [
            '(help)' => 'This is a Query object, to get the results execute or iterate it.',
            'action' => $this->action(),
            'formatters' => $this->_formatters,
            'mapReducers' => count($this->_mapReduce),
            'contain' => $eagerLoader ? $eagerLoader->contain() : [],
            'matching' => $eagerLoader ? $eagerLoader->matching() : [],
            'offset' => $this->clause('offset'),
            'page' => $this->page(),
            'limit' => $this->limit(),
            'set' => $this->set(),
            'sort' => $this->clause('order'),
            'extraOptions' => $this->getOptions(),
            'conditions' => $this->where(),
            'repository' => $this->endpoint(),
            'webservice' => $this->webservice(),
        ];
    }
}
