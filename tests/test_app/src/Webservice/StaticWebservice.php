<?php
declare(strict_types=1);

namespace TestApp\Webservice;

use Muffin\Webservice\Datasource\Query;
use Muffin\Webservice\Datasource\ResultSet;
use Muffin\Webservice\Datasource\Schema;
use Muffin\Webservice\Model\Resource;
use Muffin\Webservice\Webservice\WebserviceInterface;

class StaticWebservice implements WebserviceInterface
{
    public function execute(Query $query, array $options = []): ResultSet
    {
        return new ResultSet([
            new Resource([
                'id' => 1,
                'title' => 'Hello World',
            ]),
            new Resource([
                'id' => 2,
                'title' => 'New ORM',
            ]),
            new Resource([
                'id' => 3,
                'title' => 'Webservices',
            ]),
        ], 3);
    }

    public function describe(string $endpoint): Schema
    {
        return new Schema($endpoint, [
           'id' => [
               'type' => 'integer',
           ],
            'title' => [
                'type' => 'string',
            ],
        ]);
    }
}
