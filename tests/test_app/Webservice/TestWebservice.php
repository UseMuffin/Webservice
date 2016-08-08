<?php

namespace Muffin\Webservice\Test\test_app\Webservice;

use Muffin\Webservice\Query;
use Muffin\Webservice\Schema;
use Muffin\Webservice\Webservice\Webservice;

class TestWebservice extends Webservice
{
    public function createResult($query, array $data)
    {
        return $this->_createResult($query, $data);
    }

    public function transformResults(Query $query, array $results)
    {
        return $this->_transformResults($query, $results);
    }

    public function describe($endpoint)
    {
        $schema = new Schema($endpoint);
        $schema->addColumn('id', [
            'type' => 'int'
        ]);
        $schema->addColumn('title', [
            'type' => 'string'
        ]);
        $schema->addColumn('body', [
            'type' => 'string'
        ]);
        
        return $schema;
    }
}
