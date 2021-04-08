<?php
declare(strict_types=1);

namespace Muffin\Webservice\Webservice;

use Muffin\Webservice\Datasource\Query;
use Muffin\Webservice\Datasource\Schema;

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
     * @param \Muffin\Webservice\Datasource\Query $query The query to execute
     * @param array $options The options to use
     * @return bool|int|\Muffin\Webservice\Model\Resource|\Muffin\Webservice\Datasource\ResultSet
     */
    public function execute(Query $query, array $options = []);

    /**
     * Returns a schema for the provided endpoint
     *
     * @param string $endpoint The endpoint to get the schema for
     * @return \Muffin\Webservice\Datasource\Schema The schema to use
     * @throws \Muffin\Webservice\Model\Exception\MissingEndpointSchemaException If no schema can be found
     */
    public function describe(string $endpoint): Schema;
}
