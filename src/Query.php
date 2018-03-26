<?php

namespace Muffin\Webservice;

use ArrayObject;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Datasource\QueryInterface;
use Cake\Datasource\QueryTrait;
use Cake\Utility\Hash;
use IteratorAggregate;
use JsonSerializable;
use Muffin\Webservice\Model\Endpoint;
use Muffin\Webservice\Webservice\WebserviceInterface;

class Query implements IteratorAggregate, JsonSerializable, QueryInterface
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
        'where' => [],
        'select' => []
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
     * Apply custom finds to against an existing query object.
     *
     * Allows custom find methods to be combined and applied to each other.
     *
     * ```
     * $repository->find('all')->find('recent');
     * ```
     *
     * The above is an example of stacking multiple finder methods onto
     * a single query.
     *
     * @param string $finder The finder method to use.
     * @param array $options The options for the finder.
     * @return $this Returns a modified query.
     */
    public function find($finder, array $options = [])
    {
        return $this->repository()->callFinder($finder, $this, $options);
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

    /**
     * Alias a field with the endpoint's current alias.
     *
     * @param string $field The field to alias.
     * @param null $alias Not being used
     *
     * @return array The field prefixed with the endpoint alias.
     */
    public function aliasField($field, $alias = null)
    {
        return [$field => $field];
    }

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

        $this->_parts['where'] = (!$overwrite) ? Hash::merge($this->clause('where'), $conditions) : $conditions;

        return $this;
    }

    /**
     * Add AND conditions to the query
     *
     * @param string|array|\Cake\Database\ExpressionInterface|callable $conditions The conditions to add with AND.
     * @param array $types associative array of type names used to bind values to query
     * @see \Cake\Database\Query::where()
     * @see \Cake\Database\Type
     * @return $this
     */
    public function andWhere($conditions, $types = [])
    {
        $this->where($conditions, $types);

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
     * Adds a single or multiple fields to be used in the ORDER clause for this query.
     * Fields can be passed as an array of strings, array of expression
     * objects, a single expression or a single string.
     *
     * If an array is passed, keys will be used as the field itself and the value will
     * represent the order in which such field should be ordered. When called multiple
     * times with the same fields as key, the last order definition will prevail over
     * the others.
     *
     * By default this function will append any passed argument to the list of fields
     * to be selected, unless the second argument is set to true.
     *
     * @param array|string $fields fields to be added to the list
     * @param bool $overwrite whether to reset order with field list or not
     * @return $this
     */
    public function order($fields, $overwrite = false)
    {
        $this->_parts['order'] = (!$overwrite) ? Hash::merge($this->clause('order'), $fields) : $fields;

        return $this;
    }

    /**
     * Populates or adds parts to current query clauses using an array.
     * This is handy for passing all query clauses at once.
     *
     * @param array $options the options to be applied
     *
     * @return $this This object
     */
    public function applyOptions(array $options)
    {
        if (isset($options['page'])) {
            $this->page($options['page']);

            unset($options['page']);
        }
        if (isset($options['limit'])) {
            $this->limit($options['limit']);

            unset($options['limit']);
        }
        if (isset($options['order'])) {
            $this->order($options['order']);

            unset($options['order']);
        }
        if (isset($options['conditions'])) {
            $this->where($options['conditions']);

            unset($options['conditions']);
        }

        $this->_options = Hash::merge($this->_options, $options);

        return $this;
    }

    /**
     * Returns the total amount of results for this query
     *
     * @return int
     */
    public function count()
    {
        if ($this->action() !== self::ACTION_READ) {
            return 0;
        }

        if (!$this->__resultSet) {
            $this->_execute();
        }

        if ($this->__resultSet) {
            return $this->__resultSet->total();
        }

        return 0;
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
                !$this->isEagerLoaded()
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

        return $this->__resultSet = $this->_webservice->execute($this);
    }

    /**
     * Return a handy representation of the query
     *
     * @return array
     */
    public function __debugInfo()
    {
        return [
            '(help)' => 'This is a Query object, to get the results execute or iterate it.',
            'action' => $this->action(),
            'formatters' => $this->_formatters,
            'offset' => $this->clause('offset'),
            'page' => $this->page(),
            'limit' => $this->limit(),
            'set' => $this->set(),
            'sort' => $this->clause('order'),
            'extraOptions' => $this->getOptions(),
            'conditions' => $this->where(),
            'repository' => $this->endpoint(),
            'webservice' => $this->webservice()
        ];
    }

    /**
     * Executes the query and converts the result set into JSON.
     *
     * Part of JsonSerializable interface.
     *
     * @return \Cake\Datasource\ResultSetInterface The data to convert to JSON.
     */
    public function jsonSerialize()
    {
        return $this->all();
    }

    /**
     * Select the fields to include in the query
     *
     * @param array|\Cake\Database\ExpressionInterface|string|callable $fields fields to be added to the list.
     * @param bool $overwrite whether to reset fields with passed list or not
     * @return $this
     */
    public function select($fields = [], $overwrite = false)
    {
        if (!is_string($fields) && is_callable($fields)) {
            $fields = $fields($this);
        }

        if (!is_array($fields)) {
            $fields = [$fields];
        }

        if ($overwrite) {
            $this->_parts['select'] = $fields;
        } else {
            $this->_parts['select'] = array_merge($this->_parts['select'], $fields);
        }

        return $this;
    }
}
