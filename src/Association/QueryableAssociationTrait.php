<?php

namespace Muffin\Webservice\Association;

use InvalidArgumentException;
use Muffin\Webservice\Association;

/**
 * Represents a type of association that that can be fetched using another query
 */
trait QueryableAssociationTrait
{

    /**
     * Returns true if the eager loading process will require a set of the owning endpoint's
     * binding keys in order to use them as a filter in the finder query.
     *
     * @param array $options The options containing the strategy to be used.
     * @return bool true if a list of keys will be required
     */
    public function requiresKeys(array $options = [])
    {
        $strategy = isset($options['strategy']) ? $options['strategy'] : $this->strategy();
        return $strategy === $this::STRATEGY_QUERY;
    }

    /**
     * {@inheritDoc}
     */
    public function eagerLoader(array $options)
    {
        $options += $this->_defaultOptions();
        $fetchQuery = $this->_buildQuery($options);
        $resultMap = $this->_buildResultMap($fetchQuery, $options);
        return $this->_resultInjector($fetchQuery, $resultMap, $options);
    }

    /**
     * Returns the default options to use for the eagerLoader
     *
     * @return array
     */
    protected function _defaultOptions()
    {
        return [
            'foreignKey' => $this->foreignKey(),
            'conditions' => [],
            'strategy' => $this->strategy(),
            'nestKey' => $this->_name
        ];
    }

    /**
     * Auxiliary function to construct a new Query object to return all the records
     * in the target endpoint that are associated to those specified in $options from
     * the source endpoint
     *
     * @param array $options options accepted by eagerLoader()
     * @return \Muffin\Webservice\Query
     * @throws \InvalidArgumentException When a key is required for associations but not selected.
     */
    protected function _buildQuery($options)
    {
        $key = $this->_linkField($options);
        $filter = $options['keys'];

        $finder = isset($options['finder']) ? $options['finder'] : $this->finder();
        list($finder, $opts) = $this->_extractFinder($finder);
        $options += ['fields' => []];

        $fetchQuery = $this
            ->find($finder, $opts)
            ->where($options['conditions'])
            ->eagerLoaded(true)
            ->hydrate($options['query']->hydrate());

        $fetchQuery = $this->_addFilteringCondition($fetchQuery, $key, $filter);

        if (!empty($options['sort'])) {
            $fetchQuery->order($options['sort']);
        }

        if (!empty($options['contain'])) {
            $fetchQuery->contain($options['contain']);
        }

        if (!empty($options['queryBuilder'])) {
            $fetchQuery = $options['queryBuilder']($fetchQuery);
        }

        return $fetchQuery;
    }

    /**
     * Appends any conditions required to load the relevant set of records in the
     * target endpoint query given a filter key and some filtering values.
     *
     * @param \Muffin\Webservice\Query $query Target endpoint's query
     * @param string|array $key the fields that should be used for filtering
     * @param mixed $filter the value that should be used to match for $key
     * @return \Muffin\Webservice\Query
     */
    protected function _addFilteringCondition($query, $key, $filter)
    {
        if (is_array($key)) {
            $conditions = [];
            foreach ($key as $keyIndex => $keyName) {
                $conditions[$keyName] = [];
                foreach ($filter as $index => $value) {
                    $conditions[$keyName][$index] = $value[$keyIndex];
                }
            }
        }
        if ((is_array($filter)) && (count($filter))) {
            $filter = current($filter);
        }

        $conditions = isset($conditions) ? $conditions : [$key => $filter];
        return $query->where($conditions);
    }

    /**
     * Generates a string used as a endpoint field that contains the values upon
     * which the filter should be applied
     *
     * @param array $options The options for getting the link field.
     * @return string|array
     */
    protected abstract function _linkField($options);

    /**
     * Builds an array containing the results from fetchQuery indexed by
     * the foreignKey value corresponding to this association.
     *
     * @param \Muffin\Webservice\Query $fetchQuery The query to get results from
     * @param array $options The options passed to the eager loader
     * @return array
     */
    protected abstract function _buildResultMap($fetchQuery, $options);

    /**
     * Returns a callable to be used for each row in a query result set
     * for injecting the eager loaded rows
     *
     * @param \Muffin\Webservice\Query $fetchQuery the Query used to fetch results
     * @param array $resultMap an array with the foreignKey as keys and
     * the corresponding target endpoint results as value.
     * @param array $options The options passed to the eagerLoader method
     * @return \Closure
     */
    protected function _resultInjector($fetchQuery, $resultMap, $options)
    {
        $source = $this->source();
        $sAlias = $source->alias();
        $keys = $this->type() === $this::MANY_TO_ONE ?
            $this->foreignKey() :
            $this->bindingKey();

        $sourceKeys = [];
        foreach ((array)$keys as $key) {
            $f = $fetchQuery->aliasField($key, $sAlias);
            $sourceKeys[] = key($f);
        }

        $nestKey = $options['nestKey'];
        if (count($sourceKeys) > 1) {
            return $this->_multiKeysInjector($resultMap, $sourceKeys, $nestKey);
        }

        $sourceKey = $sourceKeys[0];
        debug($sourceKey);
        return function ($row) use ($resultMap, $sourceKey, $nestKey) {
//            debug($row);
            debug($resultMap);
            debug($row[$sourceKey]);
            debug($resultMap[$row[$sourceKey]]);

            if (isset($row[$sourceKey], $resultMap[$row[$sourceKey]])) {
                $row[$nestKey] = $resultMap[$row[$sourceKey]];
            }

            debug($row[$nestKey]);

            return $row;
        };
    }

    /**
     * Returns a callable to be used for each row in a query result set
     * for injecting the eager loaded rows when the matching needs to
     * be done with multiple foreign keys
     *
     * @param array $resultMap A keyed arrays containing the target endpoint
     * @param array $sourceKeys An array with aliased keys to match
     * @param string $nestKey The key under which results should be nested
     * @return \Closure
     */
    protected function _multiKeysInjector($resultMap, $sourceKeys, $nestKey)
    {
        return function ($row) use ($resultMap, $sourceKeys, $nestKey) {
            $values = [];
            foreach ($sourceKeys as $key) {
                $values[] = $row[$key];
            }

            $key = implode(';', $values);
            if (isset($resultMap[$key])) {
                $row[$nestKey] = $resultMap[$key];
            }
            return $row;
        };
    }
}
