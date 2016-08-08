<?php

namespace Muffin\Webservice;

use Cake\Core\App;

class QueryLog
{
    /**
     * Logged queries.
     *
     * @var array
     */
    protected static $_queries = [];

    /**
     * Log a query.
     *
     * @param Query $query The query the log.
     * @param float $took How long the query took in ms.
     * @param int $results The amount of results.
     * @return void
     */
    public static function log(Query $query, $took, $results)
    {
        static::$_queries[] = [
            'action' => $query->action(),
            'alias' => $query->endpoint()->alias(),
            'where' => $query->where(),
            'offset' => $query->clause('offset'),
            'page' => $query->clause('page'),
            'limit' => $query->clause('limit'),
            'sort' => $query->clause('sort'),
            'options' => $query->getOptions(),
            'endpoint' => App::shortName(get_class($query->endpoint()), 'Model/Endpoint', 'Endpoint'),
            'webservice' => App::shortName(get_class($query->webservice()), 'Webservice', 'Webservice'),
            'took' => round($took, 4),
            'results' => $results
        ];
    }

    /**
     * Return the logged queries.
     *
     * @return array An array of queries.
     */
    public static function queries()
    {
        return static::$_queries;
    }
}
