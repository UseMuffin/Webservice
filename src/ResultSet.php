<?php

namespace Muffin\Webservice;

use Cake\Collection\Collection;
use Cake\Collection\CollectionTrait;
use Cake\Core\Exception\Exception;
use Cake\Datasource\QueryInterface;
use Cake\Datasource\ResultSetInterface;
use Cake\Utility\Hash;
use Iterator;
use SplFixedArray;

class ResultSet implements ResultSetInterface
{

    use CollectionTrait;

    /**
     * Feed with the results
     *
     * @var Iterator
     */
    protected $_feed;

    /**
     * Points to the next record number that should be fetched
     *
     * @var int
     */
    protected $_index = 0;

    /**
     * Last record fetched from the feed.
     *
     * @var array
     */
    protected $_current;

    /**
     * Default repository instance.
     *
     * @var \Cake\Datasource\RepositoryInterface
     */
    protected $_defaultRepository;

    /**
     * The default repository alias
     *
     * @var string
     */
    protected $_defaultAlias;

    /**
     * List of matching associations and the column keys to expect
     * from each of them.
     *
     * @var array
     */
    protected $_matchingMapColumns = [];

    /**
     * Results that have been fetched or hydrated into the results.
     *
     * @var array
     */
    protected $_results = [];

    /**
     * Whether to hydrate results into objects or not
     *
     * @var bool
     */
    protected $_hydrate = true;

    /**
     * The fully namespaced name of the class to use for hydrating results
     *
     * @var string
     */
    protected $_resourceClass;

    /**
     * Whether or not to buffer results fetched from the statement
     *
     * @var bool
     */
    protected $_useBuffering = false;

    /**
     * Holds the count of records in this result set
     *
     * @var int
     */
    protected $_count;

    protected $_total;

    /**
     * Construct the ResultSet
     *
     * @param Query $query The query to use in the ResultSet.
     * @param \Muffin\Webservice\Model\Resource[]|\Iterator $resources The resources to attach
     * @param int|null $total The total amount of resources available
     */
    public function __construct(Query $query, Iterator $resources, $total = null)
    {
        $this->_feed = $resources;
        $this->_total = $total;

        $repository = $query->repository();
        $this->_defaultRepository = $query->repository();
        $this->_calculateAssociationMap($query);
        $this->_hydrate = $query->hydrate();
        $this->_resourceClass = $repository->resourceClass();
        $this->_useBuffering = $this->_feed instanceof \Countable;
        $this->_defaultAlias = $this->_defaultRepository->alias();
        $this->_calculateColumnMap($query);

        if ($this->_useBuffering) {
            $count = $this->count();
            $this->_results = new SplFixedArray($count);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function current()
    {
        return $this->_current;
    }

    /**
     * Helper function to fetch the next result from the statement or
     * seeded results.
     *
     * @return mixed
     */
    protected function _fetchResult()
    {
        $groupResult = $this->_groupResult($this->_feed->current());

        $this->_feed->next();

        return $groupResult;
    }

    /**
     * Correctly nests results keys including those coming from associations
     *
     * @param mixed $row Array containing columns and values or false if there is no results
     * @return array Results
     */
    protected function _groupResult($row)
    {
        $defaultAlias = $this->_defaultAlias;
        $results = $presentAliases = [];
        $options = [
            'useSetters' => false,
            'markClean' => true,
            'markNew' => false,
            'guard' => false
        ];

        foreach ($this->_matchingMapColumns as $alias => $keys) {
            $matching = $this->_matchingMap[$alias];
            $results['_matchingData'][$alias] = array_combine(
                $keys,
                array_intersect_key($row, $keys)
            );
            if ($this->_hydrate) {
                $options['source'] = $matching['instance']->registryAlias();
                $entity = new $matching['entityClass']($results['_matchingData'][$alias], $options);
                $entity->clean();
                $results['_matchingData'][$alias] = $entity;
            }
        }

        foreach ($this->_map as $endpoint => $keys) {
            $results[$endpoint] = array_combine($keys, array_intersect_key($row, $keys));
            $presentAliases[$endpoint] = true;
        }

        unset($presentAliases[$defaultAlias]);

        foreach ($this->_containMap as $assoc) {
            $alias = $assoc['nestKey'];

            if ($assoc['canBeJoined'] && empty($this->_map[$alias])) {
                continue;
            }

            $instance = $assoc['instance'];

            if (!$assoc['canBeJoined'] && !isset($row[$alias])) {
                $results = $instance->defaultRowValue($results, $assoc['canBeJoined']);
                continue;
            }

            if (!$assoc['canBeJoined']) {
                $results[$alias] = $row[$alias];
            }

            $target = $instance->target();
            $options['source'] = $target->registryAlias();
            unset($presentAliases[$alias]);

            if ($assoc['canBeJoined']) {
                $hasData = false;
                foreach ($results[$alias] as $v) {
                    if ($v !== null && $v !== []) {
                        $hasData = true;
                        break;
                    }
                }

                if (!$hasData) {
                    $results[$alias] = null;
                }
            }

            if ($this->_hydrate && $results[$alias] !== null && $assoc['canBeJoined']) {
                $entity = new $assoc['resourceClass']($results[$alias], $options);
                $entity->clean();
                $results[$alias] = $entity;
            }

            $results = $instance->transformRow($results, $alias, $assoc['canBeJoined']);
        }

        foreach ($presentAliases as $alias => $present) {
            if (!isset($results[$alias])) {
                continue;
            }
            $results[$defaultAlias][$alias] = $results[$alias];
        }

        if (isset($results['_matchingData'])) {
            $results[$defaultAlias]['_matchingData'] = $results['_matchingData'];
        }

        $options['source'] = $this->_defaultRepository->registryAlias();
        if (isset($results[$defaultAlias])) {
            $results = $results[$defaultAlias];
        }
        if ($this->_hydrate && !($results instanceof EntityInterface)) {
            $results = new $this->_resourceClass($results, $options);
        }

        return $results;
    }

    /**
     * {@inheritDoc}
     */
    public function rewind()
    {
        if ($this->_index == 0) {
            return;
        }

        if (!$this->_useBuffering) {
            $msg = 'You cannot rewind an un-buffered ResultSet. Use Query::bufferResults() to get a buffered ResultSet.';
            throw new Exception($msg);
        }

        $this->_index = 0;
    }

    /**
     * {@inheritDoc}
     */
    public function serialize()
    {
        while ($this->valid()) {
            $this->next();
        }
        return serialize($this->_results);
    }

    /**
     * {@inheritDoc}
     */
    public function valid()
    {
        if ($this->_useBuffering) {
            $valid = $this->_index < $this->_count;
            if ($valid && $this->_results[$this->_index] !== null) {
                $this->_current = $this->_results[$this->_index];
                return true;
            }
            if (!$valid) {
                return $valid;
            }
        }

        $this->_current = $this->_fetchResult();
        $valid = $this->_current !== false;

        if ($valid && $this->_useBuffering) {
            $this->_results[$this->_index] = $this->_current;
        }

        return $valid;
    }

    /**
     * Get the first record from a result set.
     *
     * This method will also close the underlying statement cursor.
     *
     * @return array|object
     */
    public function first()
    {
        foreach ($this as $result) {
            return $result;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function key()
    {
        return $this->_index;
    }

    /**
     * {@inheritDoc}
     */
    public function next()
    {
        $this->_index++;
    }

    /**
     * {@inheritDoc}
     */
    public function unserialize($serialized)
    {
        $this->_results = unserialize($serialized);
        $this->_useBuffering = true;
        $this->_count = count($this->_results);
    }

    /**
     * {@inheritDoc}
     */
    public function count()
    {
        if ($this->_count !== null) {
            return $this->_count;
        }

        return $this->_count = $this->_feed->count();
    }

    /**
     * Calculates the list of associations that should get eager loaded
     * when fetching each record
     *
     * @param \Cake\Datasource\QueryInterface $query The query from where to derive the associations
     * @return void
     */
    protected function _calculateAssociationMap($query)
    {
        $map = $query->eagerLoader()->associationsMap($this->_defaultRepository);
        $this->_matchingMap = (new Collection($map))
            ->match(['matching' => true])
            ->indexBy('alias')
            ->toArray();

        $this->_containMap = (new Collection(array_reverse($map)))
            ->match(['matching' => false])
            ->indexBy('nestKey')
            ->toArray();
    }

    /**
     * Creates a map of row keys out of the query select clause that can be
     * used to hydrate nested result sets more quickly.
     *
     * @param \Muffin\Webservice\Query $query The query from where to derive the column map
     * @return void
     */
    protected function _calculateColumnMap($query)
    {
        $map = [];

        /* @var \Cake\Datasource\RepositoryInterface[] $repositories */
        $repositories = [
            $this->_defaultAlias => $query->repository()
        ];
        foreach ($this->_containMap as $alias => $option) {
            if (!$option['canBeJoined']) {
                continue;
            }

            $repositories[$alias] = $option['instance']->target();
        }
        foreach ($repositories as $alias => $repository) {
            foreach ($repository->schema()->columns() as $column) {
                $map[$alias][$alias . '__' . $column] = $column;
            }
        }

        foreach ($this->_matchingMap as $alias => $assoc) {
            if (!isset($map[$alias])) {
                continue;
            }
            $this->_matchingMapColumns[$alias] = $map[$alias];
            unset($map[$alias]);
        }

        $this->_map = $map;
    }

    /**
     * Returns the total amount of results
     *
     * @return int|null
     */
    public function total()
    {
        return $this->_total;
    }
}
