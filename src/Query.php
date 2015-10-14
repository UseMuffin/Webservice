<?php

namespace Muffin\Webservice;

use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Datasource\QueryInterface;
use Cake\Datasource\QueryTrait;
use Cake\Utility\Hash;
use Muffin\Webservice\Model\Endpoint;
use Muffin\Webservice\Webservice\WebserviceInterface;

class Query implements QueryInterface
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
     * Indicates whether internal state of this query was changed, this is used to
     * discard internal cached objects such as the transformed query or the reference
     * to the executed statement.
     *
     * @var bool
     */
    protected $_dirty = false;

    private $_action;
    private $_conditions = [];
    private $_page;
    private $_limit;
    private $_fields = [];
    private $_offset = [];
    private $_order = [];

    /**
     * @var \Muffin\Webservice\Webservice\WebserviceInterface
     */
    protected $_webservice;

    /**
     * @var ResultSet
     */
    protected $_resultSet;

    /**
     * Construct the query
     *
     * @param WebserviceInterface $webservice The webservice to use
     * @param Endpoint $endpoint The endpoint this is executed from
     */
    public function __construct(WebserviceInterface $webservice, Endpoint $endpoint)
    {
        $this->_webservice = $webservice;
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
     * Set the endpoint to be used
     *
     * @param Endpoint|null $endpoint The endpoint to use
     *
     * @return Endpoint|$this
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
     * {@inheritDoc}
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
     * @return string The field prefixed with the endpoint alias.
     */
    public function aliasField($field)
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
    public function where(array $conditions = null, $types = [], $overwrite = false)
    {
        if ($conditions === null) {
            return $this->_conditions;
        }

        $this->_conditions = (!$overwrite) ? Hash::merge($this->_conditions, $conditions) : $conditions;

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
            return $this->_action;
        }

        $this->_action = $action;

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
            return $this->_page;
        }
        if ($limit !== null) {
            $this->limit($limit);
        }

        $this->_page = $page;

        return $this;
    }

    /**
     * Sets the number of records that should be retrieved from database,
     * accepts an integer or an expression object that evaluates to an integer.
     * In some databases, this operation might not be supported or will require
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
            return $this->_limit;
        }

        $this->_limit = $limit;

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
        if (!in_array($this->action(), [self::ACTION_CREATE, self::ACTION_UPDATE])) {
            throw new \UnexpectedValueException(__('The action of this query needs to be either create update'));
        }

        if ($fields === null) {
            return $this->_fields;
        }

        $this->_fields = $fields;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function offset($num)
    {
        $this->_offset = $num;

        return $this;
    }

    /**
     * Set the order in which results should be
     *
     * @param array|null $fields The array of fields and their direction
     *
     * @return $this|array
     */
    public function order(array $fields = null)
    {
        if ($fields === null) {
            return $this->_order;
        }

        $this->_order = $fields;

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

        $this->_options = Hash::merge($this->_options, $options);

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

        if (!$this->_resultSet) {
            $this->_execute();
        }

        return $this->_resultSet->total();
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
        if (!$this->_resultSet) {
            $this->limit(1);
        }

        return $this->all()->first();
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
        return $this->_resultSet = $this->_webservice->execute($this, [
            'resourceClass' => $this->endpoint()->resourceClass()
        ]);
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
            'page' => $this->page(),
            'limit' => $this->limit(),
            'set' => $this->set(),
            'sort' => $this->order(),
            'extraOptions' => $this->getOptions(),
            'conditions' => $this->where(),
            'repository' => $this->endpoint(),
            'webservice' => $this->_webservice
        ];
    }
}
