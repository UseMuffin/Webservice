<?php

namespace Muffin\Webservice\Webservice;

use Muffin\Webservice\Query;

/**
 * Describes a webservice used to call a API
 *
 * @package Muffin\Webservice\Webservice
 */
interface WebserviceInterface
{

    /**
     * Executes a query
     *
     * @param Query $query The query to execute
     * @param array $options The options to use
     *
     * @return \Muffin\Webservice\WebserviceResultSetInterface|int|bool
     */
    public function execute(Query $query, array $options = []);

    /**
     * Returns a schema for the provided endpoint
     *
     * @param string $endpoint The endpoint to get the schema for
     * @return \Muffin\Webservice\Schema The schema to use
     */
    public function describe($endpoint);
}
