<?php

namespace Muffin\Webservice\Test\test_app\Webservice;

use Cake\Utility\Hash;
use Muffin\Webservice\Model\Resource;
use Muffin\Webservice\Query;
use Muffin\Webservice\Webservice\Webservice;
use Muffin\Webservice\WebserviceResultSet;

class EndpointTestWebservice extends Webservice
{

    public $resources;

    public function initialize()
    {
        parent::initialize();

        $this->resources['posts'] = [
            [
                'id' => 1,
                'title' => 'Hello World',
                'body' => 'Some text'
            ],
            [
                'id' => 2,
                'title' => 'New ORM',
                'body' => 'Some more text'
            ],
            [
                'id' => 3,
                'title' => 'Webservices',
                'body' => 'Even more text'
            ]
        ];
    }

    protected function _executeQuery(Query $query, array $options = [])
    {
        if (!isset($this->resources[$query->endpoint()->endpoint()])) {
            $this->resources[$query->endpoint()->endpoint()] = [];
        }

        return parent::_executeQuery($query, $options);
    }

    protected function _executeCreateQuery(Query $query, array $options = [])
    {
        $fields = $query->set();

        if (!isset($fields['id'])) {
            $highestId = collection($this->resources[$query->endpoint()->endpoint()])->max('id');

            $fields = ['id' => ($highestId) ? $highestId['id'] + 1: 1] + $fields;
        }

        if (!is_numeric($fields['id'])) {
            return false;
        }

        $this->resources[$query->endpoint()->endpoint()][] = $fields;

        return WebserviceResultSet::createForSingleResource($this->_transformResource($query, $fields));
    }

    protected function _executeReadQuery(Query $query, array $options = [])
    {
        $endpoint = $query->endpoint()->endpoint();

        $conditions = $query->where();
        debug($conditions);
        foreach ($conditions as $condition => $value) {
            $splitCondition = explode('.', $condition);
            if ($splitCondition[0] !== $query->endpoint()->alias()) {
                continue;
            }

            unset($conditions[$condition]);
            $conditions[$splitCondition[1]] = $value;
        }

        $multiKey = false;
        $multiKeySize = 0;
        foreach ($conditions as $key => $value) {
            if (!is_array($value)) {
                continue;
            }

            $multiKey = true;
            $multiKeySize = count($value);
        }

        if (array_keys($conditions) !== range(0, count($conditions) - 1)) {
            $conditions = [$conditions];

            $multiKey = true;
        }

        foreach ($conditions as &$conditionGroup) {
            foreach ($conditionGroup as $condition => $value) {
                if (is_array($value)) {
                    debug($conditionGroup);
                    stackTrace();

                    exit();
                }
                $splitCondition = explode('.', $condition);
                if ($splitCondition[0] !== $query->endpoint()->alias()) {
                    continue;
                }

                unset($conditionGroup[$condition]);
                $conditionGroup[$splitCondition[1]] = $value;
            }
        }

//        debug($this->resources[$endpoint]);
        $resources = collection($this->resources[$endpoint]);
        if ($multiKey) {
            $conditionGroups = $conditions;
//            foreach ($conditions as $key => $valueGroup) {
//                if (is_array($valueGroup)) {
//                    foreach ($valueGroup as $index => $value) {
//                        $conditionGroups[$index][$key] = $value;
//                    }
//                } else {
//                    for ($i = 0; $i < $multiKeySize; $i++) {
//                        $conditionGroups[$i][$key] = $valueGroup;
//                    }
//                }
//            }
            $resources = $resources->filter(function ($resource) use ($conditionGroups) {
//                debug($resource);
                foreach ($conditionGroups as $conditionGroup) {
                    $match = true;
                    foreach ($conditionGroup as $key => $value) {
                        if (substr($key, -2) === '!=') {
                            if ($resource[substr($key, 0, -3)] != $value) {
//                                debug('Yep !=');
                                continue;
                            }
                        } elseif (substr($key, -2) === '>=') {
//                            debug($key);
//                            debug($resource[substr($key, 0, -3)]);
//                            debug('>=');
//                            debug($value);
//                            debug($resource[substr($key, 0, -3)] >= $value);
                            if ($resource[substr($key, 0, -3)] >= $value) {
//                                debug('Yep >=');
                                continue;
                            } else {
//                                debug(substr($key, 0, -3) . ': ' . $resource[substr($key, 0, -3)] . ' >= ' . $value);
                            }
                        } else {
                            if (!isset($resource[$key])) {
                                debug($key);
                                debug($resource);exit();
                            }
                            if ($resource[$key] == $value) {
//                                debug('Match!');
//                                debug('Yep ==');
                                continue;
                            } else {
//                                debug($key . ': ' . $resource[$key] . ' == ' . $value);
                            }
                        }

                        $match = false;
                    }

//                    debug($match);
                    if ($match) {
                        return true;
                    }
                }

//                debug($resource['id'] . ' didn\'t match');
                return false;
            });
//            debug($resources->toList());

            return new WebserviceResultSet($this->_transformResults($query, $resources->toList()), count($resources->toList()));
        }

        $resources = $resources->match($conditions);

//        debug($resources->toList());

        return new WebserviceResultSet($this->_transformResults($query, $resources->toList()), count($resources->toList()));
    }

    protected function _executeUpdateQuery(Query $query, array $options = [])
    {
        $index = $this->conditionsToIndex($query->where());

        debug($query->where());

        debug($this->resources[$query->endpoint()->endpoint()]);
        $resources = collection($this->resources[$query->endpoint()->endpoint()])
            ->match($query->where())
            ->map(function ($resource) use ($query) {
                return Hash::merge($resource, $query->set());
            });
        debug($resources->toList());

        $this->resources[$query->endpoint()->endpoint()][$index] = Hash::merge($this->resources[$query->endpoint()->endpoint()][$index], $query->set());

        return 1;
    }

    protected function _executeDeleteQuery(Query $query, array $options = [])
    {
        $conditions = $query->where();

        $endpoint = $query->endpoint()->endpoint();
        if (count($conditions) === 0) {
            $count = $this->resources[$endpoint];

            $this->resources[$endpoint] = [];

            return $count;
        }

        $conditionSets = [];
        foreach ($conditions as $key => $values) {
            if (!is_array($values)) {
                continue;
            }

            foreach ($values as $index => $value) {
                $conditionSets[$index][$key] = $value;
            }
        }
        if (count($conditionSets) !== 0) {
            $count = 0;
            foreach ($conditionSets as $conditionSet) {

                $count += count(collection($this->resources[$endpoint])->match($conditionSet)->each(function ($resource, $index) use ($endpoint) {
                    unset($this->resources[$endpoint][$index]);
                })->toList());
            }

            return $count;
        }
        return count(collection($this->resources[$endpoint])->match($conditions)->each(function ($resource, $index) use ($endpoint) {
            unset($this->resources[$endpoint][$index]);
        })->toList());

        if (isset($conditions['id'])) {
            if (is_int($conditions['id'])) {
                $exists = isset($this->resources[$endpoint][$this->conditionsToIndex($conditions)]);

                unset($this->resources[$endpoint][$this->conditionsToIndex($conditions)]);

                return ($exists) ? 1 : 0;
            } elseif (is_array($conditions['id'])) {
                $deleted = 0;

                foreach ($conditions['id'] as $id) {
                    if (!isset($this->resources[$endpoint][$id - 1])) {
                        continue;
                    }

                    $deleted++;
                    unset($this->resources[$endpoint][$id - 1]);
                }

                return $deleted;
            }
        }

        return 0;
    }

    public function conditionsToIndex(array $conditions)
    {
        return $conditions['id'] - 1;
    }
}
