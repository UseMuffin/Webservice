<?php
declare(strict_types=1);

namespace Muffin\Webservice\Test\test_app\Webservice;

use Muffin\Webservice\Model\Resource;
use Muffin\Webservice\Query;
use Muffin\Webservice\ResultSet;
use Muffin\Webservice\Schema;
use Muffin\Webservice\Webservice\WebserviceInterface;

class StaticWebservice implements WebserviceInterface
{
    public function execute(Query $query, array $options = [])
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

    public function describe($endpoint)
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
