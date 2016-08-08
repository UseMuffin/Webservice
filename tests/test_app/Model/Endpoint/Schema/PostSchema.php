<?php

namespace Muffin\Webservice\Test\test_app\Model\Endpoint\Schema;

use Muffin\Webservice\Model\Schema;

class PostSchema extends Schema
{
    public function initialize()
    {
        $this->addColumn('id', [
            'type' => 'int',
            'primaryKey' => true
        ]);
        $this->addColumn('title', [
            'type' => 'string'
        ]);
        $this->addColumn('body', [
            'type' => 'string'
        ]);
    }
}
