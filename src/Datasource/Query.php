<?php
declare(strict_types=1);

namespace Muffin\Webservice\Datasource;

use ArrayObject;
use Cake\Collection\Iterator\MapReduce;
use Cake\Database\ExpressionInterface;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Datasource\QueryCacher;
use Cake\Datasource\QueryInterface;
use Cake\Datasource\RepositoryInterface;
use Cake\Datasource\ResultSetDecorator;
use Cake\Datasource\ResultSetInterface;
use Cake\Utility\Hash;
use Closure;
use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;
use Muffin\Webservice\Model\Endpoint;
use Muffin\Webservice\Model\Resource;
use Muffin\Webservice\Webservice\WebserviceInterface;
use Traversable;
use UnexpectedValueException;

class Query implements IteratorAggregate, JsonSerializable, QueryInterface
{
    public const ACTION_CREATE = 1;
    public const ACTION_READ = 2;
    public const ACTION_UPDATE = 3;
    public const ACTION_DELETE = 4;

    /**
     * Indicates that the operation should append to the list
     *
     * @var int
     */
    public const APPEND = 0;

    /**
     * Indicates that the operation should prepend to the list
     *
     * @var int
     */
    public const PREPEND = 1;

    /**
     * Indicates that the operation should overwrite the list
     *
     * @var bool
     */
    public const OVERWRITE = true;

    /**
     * True if the beforeFind event has already been triggered for this query
     *
     * @var bool
     */
    protected bool $_beforeFindFired = false;

    /**
     * Whether the query is standalone or the product of an eager load operation.
     *
     * @var bool
     */
    protected bool $_eagerLoaded = false;

    /**
     * Indicates whether internal state of this query was changed, this is used to
     * discard internal cached objects such as the transformed query or the reference
     * to the executed statement.
     *
     * @var bool
     */
    protected bool $_dirty = false;

    /**
     * Parts being used to in the query
     *
     * @var array
     */
    protected array $_parts = [
        'order' => [],
        'set' => [],
        'where' => [],
        'select' => [],
    ];

    /**
     * Holds any custom options passed using applyOptions that could not be processed
     * by any method in this class.
     *
     * @var array
     */
    protected array $_options = [];

    /**
     * Instance of the webservice to use
     *
     * @var \Muffin\Webservice\Webservice\WebserviceInterface
     */
    protected WebserviceInterface $_webservice;

    /**
     * The result from the webservice
     *
     * @var \Muffin\Webservice\Model\Resource|\Muffin\Webservice\Datasource\ResultSet|int|bool
     */
    protected mixed $_results = null;

    /**
     * Instance of a endpoint object this query is bound to
     *
     * @var \Cake\Datasource\RepositoryInterface
     */
    protected RepositoryInterface $_endpoint;

    /**
     * List of map-reduce routines that should be applied over the query
     * result
     *
     * @var array
     */
    protected array $_mapReduce = [];

    /**
     * List of formatter classes or callbacks that will post-process the
     * results when fetched
     *
     * @var array<\Closure>
     */
    protected array $_formatters = [];

    /**
     * A query cacher instance if this query has caching enabled.
     *
     * @var \Cake\Datasource\QueryCacher|null
     */
    protected ?QueryCacher $_cache = null;

    /**
     * Construct the query
     *
     * @param \Muffin\Webservice\Webservice\WebserviceInterface $webservice The webservice to use
     * @param \Muffin\Webservice\Model\Endpoint $endpoint The endpoint this is executed from
     */
    public function __construct(WebserviceInterface $webservice, Endpoint $endpoint)
    {
        $this->setWebservice($webservice);
        $this->setEndpoint($endpoint);
    }

    /**
     * Executes this query and returns a results iterator. This function is required
     * for implementing the IteratorAggregate interface and allows the query to be
     * iterated without having to call execute() manually, thus making it look like
     * a result set instead of the query itself.
     *
     * @return \Traversable
     */
    public function getIterator(): Traversable
    {
        return $this->all();
    }

    /**
     * @inheritDoc
     */
    public function aliasFields(array $fields, ?string $defaultAlias = null): array
    {
        $aliased = [];
        foreach ($fields as $alias => $field) {
            if (is_numeric($alias) && is_string($field)) {
                $aliased += $this->aliasField($field, $defaultAlias);
                continue;
            }
            $aliased[$alias] = $field;
        }

        return $aliased;
    }

    /**
     * Fetch the results for this query.
     *
     * Will return either the results set through setResult(), or execute this query
     * and return the ResultSetDecorator object ready for streaming of results.
     *
     * ResultSetDecorator is a traversable object that implements the methods found
     * on Cake\Collection\Collection.
     *
     * @return \Cake\Datasource\ResultSetInterface
     */
    public function all(): ResultSetInterface
    {
        if ($this->_results !== null) {
            if (!($this->_results instanceof ResultSetInterface)) {
                $this->_results = $this->decorateResults($this->_results);
            }

            return $this->_results;
        }

        $results = null;
        if ($this->_cache) {
            $results = $this->_cache->fetch($this);
        }
        if ($results === null) {
            $results = $this->decorateResults($this->_execute());
            if ($this->_cache) {
                $this->_cache->store($this, $results);
            }
        }
        $this->_results = $results;

        return $this->_results;
    }

    public function orderBy(Closure|array|string $fields, bool $overwrite = false): void
    {
    }

    /**
     * Returns an array representation of the results after executing the query.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->all()->toArray();
    }

    /**
     * Set the default repository object that will be used by this query.
     *
     * @param \Cake\Datasource\RepositoryInterface $repository The default repository object to use.
     * @return $this
     */
    public function setRepository(RepositoryInterface $repository)
    {
        $this->_endpoint = $repository;

        return $this;
    }

    /**
     * Returns the default repository object that will be used by this query,
     * that is, the table that will appear in the from clause.
     *
     * @return \Muffin\Webservice\Model\Endpoint
     */
    public function getRepository(): ?RepositoryInterface
    {
        return $this->_endpoint;
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
     * @return mixed
     */
    public function clause(string $name): mixed
    {
        if (isset($this->_parts[$name])) {
            return $this->_parts[$name];
        }

        return null;
    }

    /**
     * Set the endpoint to be used
     *
     * @param \Muffin\Webservice\Model\Endpoint $endpoint The endpoint to use
     * @return $this
     * @psalm-suppress LessSpecificReturnStatement
     */
    public function setEndpoint(Endpoint $endpoint)
    {
        $this->_endpoint = $endpoint;

        return $this;
    }

    /**
     * Set the endpoint to be used
     *
     * @return \Muffin\Webservice\Model\Endpoint
     * @psalm-suppress MoreSpecificReturnType
     */
    public function getEndpoint(): Endpoint
    {
        /** @var \Muffin\Webservice\Model\Endpoint */
        return $this->_endpoint;
    }

    /**
     * Set the webservice to be used
     *
     * @param \Muffin\Webservice\Webservice\WebserviceInterface $webservice The webservice to use
     * @return $this
     */
    public function setWebservice(WebserviceInterface $webservice)
    {
        $this->_webservice = $webservice;

        return $this;
    }

    /**
     * Get the webservice used
     *
     * @return \Muffin\Webservice\Webservice\WebserviceInterface
     */
    public function getWebservice(): WebserviceInterface
    {
        return $this->_webservice;
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
     * @param mixed ...$args Arguments that match up to finder-specific parameters
     * @return static Returns a modified query.
     */
    public function find(string $finder, mixed ...$args): static
    {
        return $this->_endpoint->callFinder($finder, $this, $args);
    }

    /**
     * Get the first result from the executing query or raise an exception.
     *
     * @return mixed The first result from the ResultSet.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When there is no first record.
     */
    public function firstOrFail(): mixed
    {
        $entity = $this->first();
        if ($entity) {
            return $entity;
        }
        /** @psalm-suppress UndefinedInterfaceMethod */
        throw new RecordNotFoundException(sprintf(
            'Record not found in endpoint "%s"',
            $this->_endpoint->getName()
        ));
    }

    /**
     * Alias a field with the endpoint's current alias.
     *
     * @param string $field The field to alias.
     * @param string|null $alias Not being used
     * @return array The field prefixed with the endpoint alias.
     */
    public function aliasField(string $field, ?string $alias = null): array
    {
        return [$field => $field];
    }

    /**
     * Apply conditions to the query
     *
     * @param \Closure|array|string|null $conditions The list of conditions.
     * @param array $types Not used, required to comply with QueryInterface.
     * @param bool $overwrite Whether or not to replace previous queries.
     * @return $this
     */
    public function where(
        Closure|array|string|null $conditions = null,
        array $types = [],
        bool $overwrite = false
    ) {
        if ($conditions === null) {
            return $this->clause('where');
        }

        $this->_parts['where'] = !$overwrite ? Hash::merge($this->clause('where'), $conditions) : $conditions;

        return $this;
    }

    /**
     * Add AND conditions to the query
     *
     * @param array|string $conditions The conditions to add with AND.
     * @param array $types associative array of type names used to bind values to query
     * @return $this
     * @see \Cake\Database\Query::where()
     * @see \Cake\Database\Type
     * @psalm-suppress PossiblyInvalidArgument
     */
    public function andWhere(string|array $conditions, array $types = [])
    {
        $this->where($conditions, $types);

        return $this;
    }

    /**
     * Charge this query's action
     *
     * @param int $action Action to use
     * @return $this
     */
    public function action(int $action)
    {
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
     * @param int $num The page number you want.
     * @param int $limit The number of rows you want in the page. If null
     *  the current limit clause will be used.
     * @return $this
     */
    public function page(int $num, ?int $limit = null)
    {
        if ($num < 1) {
            throw new InvalidArgumentException('Pages must start at 1.');
        }

        if ($limit !== null) {
            $this->limit($limit);
        }

        $this->_parts['page'] = $num;

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
     * @param ?int $limit number of records to be returned
     * @return $this
     */
    public function limit(?int $limit)
    {
        $this->_parts['limit'] = $limit;

        return $this;
    }

    /**
     * Set fields to save in resources
     *
     * @param array|null $fields The field to set
     * @return $this|array
     */
    public function set(?array $fields = null)
    {
        if ($fields === null) {
            return $this->clause('set');
        }

        if (!in_array($this->clause('action'), [self::ACTION_CREATE, self::ACTION_UPDATE])) {
            throw new UnexpectedValueException('The action of this query needs to be either create update');
        }

        $this->_parts['set'] = $fields;

        return $this;
    }

    /**
     * @inheritDoc
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
     * @param \Cake\Database\ExpressionInterface|\Closure|array|string $fields fields to be added to the list
     * @param bool $overwrite whether to reset order with field list or not
     * @return $this
     */
    public function order(array|ExpressionInterface|Closure|string $fields, bool $overwrite = false)
    {
        $this->_parts['order'] = !$overwrite ? Hash::merge($this->clause('order'), $fields) : $fields;

        return $this;
    }

    /**
     * Populates or adds parts to current query clauses using an array.
     * This is handy for passing all query clauses at once.
     *
     * @param array $options the options to be applied
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
    public function count(): int
    {
        if ($this->clause('action') !== self::ACTION_READ) {
            return 0;
        }

        if (!$this->_results) {
            $this->_execute();
        }

        if ($this->_results) {
            return (int)$this->_results->total();
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
    public function first(): mixed
    {
        if ($this->_dirty) {
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
    public function triggerBeforeFind(): void
    {
        if (!$this->_beforeFindFired && $this->clause('action') === self::ACTION_READ) {
            /** @var \Muffin\Webservice\Model\Endpoint $endpoint */
            $endpoint = $this->getRepository();
            $this->_beforeFindFired = true;
            $endpoint->dispatchEvent('Model.beforeFind', [
                $this,
                new ArrayObject($this->_options),
                !$this->isEagerLoaded(),
            ]);
        }
    }

    /**
     * Execute the query
     *
     * @return \Muffin\Webservice\Model\Resource|\Muffin\Webservice\Datasource\ResultSet|int|bool
     */
    public function execute(): bool|int|Resource|ResultSetInterface
    {
        if ($this->clause('action') === self::ACTION_READ) {
            return $this->_execute();
        }

        return $this->_result = $this->_webservice->execute($this);
    }

    /**
     * Executes this query and returns a traversable object containing the results
     *
     * @return \Cake\Datasource\ResultSetInterface
     */
    protected function _execute(): ResultSetInterface
    {
        $this->triggerBeforeFind();
        if ($this->_results) {
            $decorator = $this->decoratorClass();

            return new $decorator($this->_results);
        }

        /** @var \Cake\Datasource\ResultSetInterface */
        return $this->_results = $this->_webservice->execute($this);
    }

    /**
     * Return a handy representation of the query
     *
     * @return array
     */
    public function __debugInfo(): array
    {
        return [
            '(help)' => 'This is a Query object, to get the results execute or iterate it.',
            'action' => $this->clause('action'),
            'formatters' => $this->_formatters,
            'offset' => $this->clause('offset'),
            'page' => $this->clause('page'),
            'limit' => $this->clause('limit'),
            'set' => $this->set(),
            'sort' => $this->clause('order'),
            'extraOptions' => $this->getOptions(),
            'conditions' => $this->where(),
            'repository' => $this->getEndpoint(),
            'webservice' => $this->getWebservice(),
        ];
    }

    /**
     * Executes the query and converts the result set into JSON.
     *
     * Part of JsonSerializable interface.
     *
     * @return \Cake\Datasource\ResultSetInterface The data to convert to JSON.
     */
    public function jsonSerialize(): ResultSetInterface
    {
        return $this->all();
    }

    /**
     * Adds fields to be selected from _source.
     *
     * Calling this function multiple times will append more fields to the
     * list of fields to be selected from _source.
     *
     * If `true` is passed in the second argument, any previous selections
     * will be overwritten with the list passed in the first argument.
     *
     * @param \Cake\Database\ExpressionInterface|\Closure|array|string|float|int $fields The list of fields to select from _source.
     * @param bool $overwrite Whether or not to replace previous selections.
     * @return $this
     */
    public function select(ExpressionInterface|Closure|array|string|int|float $fields, bool $overwrite = false)
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

    /**
     * Returns the name of the class to be used for decorating results
     *
     * @return class-string<\Cake\Datasource\ResultSetInterface>
     */
    protected function decoratorClass(): string
    {
        return ResultSetDecorator::class;
    }

    /**
     * Decorates the results iterator with MapReduce routines and formatters
     *
     * @param iterable $result Original results
     * @return \Cake\Datasource\ResultSetInterface
     */
    protected function decorateResults(iterable $result): ResultSetInterface
    {
        $decorator = $this->decoratorClass();

        if (!empty($this->_mapReduce)) {
            foreach ($this->_mapReduce as $functions) {
                $result = new MapReduce($result, $functions['mapper'], $functions['reducer']);
            }
            $result = new $decorator($result);
        }

        if (!($result instanceof ResultSetInterface)) {
            $result = new $decorator($result);
        }

        if (!empty($this->_formatters)) {
            foreach ($this->_formatters as $formatter) {
                $result = $formatter($result, $this);
            }

            if (!($result instanceof ResultSetInterface)) {
                $result = new $decorator($result);
            }
        }

        return $result;
    }

    /**
     * Register a new MapReduce routine to be executed on top of the database results
     *
     * The MapReduce routing will only be run when the query is executed and the first
     * result is attempted to be fetched.
     *
     * If the third argument is set to true, it will erase previous map reducers
     * and replace it with the arguments passed.
     *
     * @param \Closure|null $mapper The mapper function
     * @param \Closure|null $reducer The reducing function
     * @param bool $overwrite Set to true to overwrite existing map + reduce functions.
     * @return $this
     * @see \Cake\Collection\Iterator\MapReduce for details on how to use emit data to the map reducer.
     */
    public function mapReduce(?Closure $mapper = null, ?Closure $reducer = null, bool $overwrite = false)
    {
        if ($overwrite) {
            $this->_mapReduce = [];
        }
        if ($mapper === null) {
            if (!$overwrite) {
                throw new InvalidArgumentException('$mapper can be null only when $overwrite is true.');
            }

            return $this;
        }
        $this->_mapReduce[] = compact('mapper', 'reducer');

        return $this;
    }

    /**
     * Returns an array with the custom options that were applied to this query
     * and that were not already processed by another method in this class.
     *
     * ### Example:
     *
     * ```
     *  $query->applyOptions(['doABarrelRoll' => true, 'fields' => ['id', 'name']);
     *  $query->getOptions(); // Returns ['doABarrelRoll' => true]
     * ```
     *
     * @see \Cake\Datasource\QueryInterface::applyOptions() to read about the options that will
     * be processed by this class and not returned by this function
     * @return array
     * @see applyOptions()
     */
    public function getOptions(): array
    {
        return $this->_options;
    }

    /**
     * Returns the current configured query `_eagerLoaded` value
     *
     * @return bool
     */
    public function isEagerLoaded(): bool
    {
        return $this->_eagerLoaded;
    }

    /**
     * Sets the query instance to be an eager loaded query. If no argument is
     * passed, the current configured query `_eagerLoaded` value is returned.
     *
     * @param bool $value Whether to eager load.
     * @return $this
     */
    public function eagerLoaded(bool $value)
    {
        $this->_eagerLoaded = $value;

        return $this;
    }

    /**
     * Registers a new formatter callback function that is to be executed when trying
     * to fetch the results from the database.
     *
     * If the second argument is set to true, it will erase previous formatters
     * and replace them with the passed first argument.
     *
     * Callbacks are required to return an iterator object, which will be used as
     * the return value for this query's result. Formatter functions are applied
     * after all the `MapReduce` routines for this query have been executed.
     *
     * Formatting callbacks will receive two arguments, the first one being an object
     * implementing `\Cake\Collection\CollectionInterface`, that can be traversed and
     * modified at will. The second one being the query instance on which the formatter
     * callback is being applied.
     *
     * ### Examples:
     *
     * Return all results from the table indexed by id:
     *
     * ```
     * $query->select(['id', 'name'])->formatResults(function ($results) {
     *     return $results->indexBy('id');
     * });
     * ```
     *
     * Add a new column to the ResultSet:
     *
     * ```
     * $query->select(['name', 'birth_date'])->formatResults(function ($results) {
     *     return $results->map(function ($row) {
     *         $row['age'] = $row['birth_date']->diff(new DateTime)->y;
     *
     *         return $row;
     *     });
     * });
     * ```
     *
     * @param \Closure|null $formatter The formatting function
     * @param int|bool $mode Whether to overwrite, append or prepend the formatter.
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function formatResults(?Closure $formatter = null, int|bool $mode = self::APPEND)
    {
        if ($mode === self::OVERWRITE) {
            $this->_formatters = [];
        }
        if ($formatter === null) {
            /** @psalm-suppress RedundantCondition */
            if ($mode !== self::OVERWRITE) {
                throw new InvalidArgumentException('$formatter can be null only when $mode is overwrite.');
            }

            return $this;
        }

        if ($mode === self::PREPEND) {
            array_unshift($this->_formatters, $formatter);

            return $this;
        }

        $this->_formatters[] = $formatter;

        return $this;
    }
}
