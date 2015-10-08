<?php

namespace Muffin\Webservice\Webservice;

use Muffin\Webservice\Query;

interface WebserviceInterface
{

    /**
     * Executes a query
     *
     * @param Query $query The query to execute
     * @param array $options The options to use
     *
     * @return \Muffin\Webservice\ResultSet|int|bool
     */
    public function execute(Query $query, array $options = []);
}
