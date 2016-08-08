<?php

namespace Muffin\Webservice\Test\test_app\Webservice;

use Muffin\Webservice\Model\Resource;
use Muffin\Webservice\Query;
use Muffin\Webservice\ResultSet;
use Muffin\Webservice\Schema;
use Muffin\Webservice\Webservice\WebserviceInterface;
use Muffin\Webservice\WebserviceResultSet;

class StaticWebservice implements WebserviceInterface
{

    public function execute(Query $query, array $options = [])
    {
        return new WebserviceResultSet([
            [
                $query->endpoint()->alias() . '__id' => 1,
                $query->endpoint()->alias() . '__title' => 'Hello World'
            ],
            [
                $query->endpoint()->alias() . '__id' => 2,
                $query->endpoint()->alias() . '__title' => 'New ORM'
            ],
            [
                $query->endpoint()->alias() . '__id' => 3,
                $query->endpoint()->alias() . '__title' => 'Webservices'
            ]
        ], 3);
    }

    public function describe($endpoint)
    {
        return new Schema($endpoint, [
           'id' => [
               'type' => 'integer'
           ],
            'title' => [
                'type' => 'string'
            ]
        ]);
    }
}
