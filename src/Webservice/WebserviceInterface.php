<?php
declare(strict_types=1);

namespace Muffin\Webservice\Webservice;

use Muffin\Webservice\Datasource\Query;
use Muffin\Webservice\Datasource\ResultSet;
use Muffin\Webservice\Datasource\Schema;
use Muffin\Webservice\Model\Resource;

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
     * @return \Muffin\Webservice\Model\Resource|\Muffin\Webservice\Datasource\ResultSet|int|bool
     */
    public function execute(Query $query, array $options = []): bool|int|Resource|ResultSet;

    /**
     * Returns a schema for the provided endpoint
     *
     * @param string $endpoint The endpoint to get the schema for
     * @return \Muffin\Webservice\Datasource\Schema The schema to use
     * @throws \Muffin\Webservice\Model\Exception\MissingEndpointSchemaException If no schema can be found
     */
    public function describe(string $endpoint): Schema;
}
