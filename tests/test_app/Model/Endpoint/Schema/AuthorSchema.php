<?php

namespace Muffin\Webservice\Test\test_app\Model\Endpoint\Schema;

use Muffin\Webservice\Model\Schema;

class AuthorSchema extends Schema
{
    public function initialize()
    {
        $columns = [
            'id' => ['type' => 'integer', 'primaryKey' => true],
            'name' => ['type' => 'string', 'default' => null],
        ];

        foreach ($columns as $field => $definition) {
            $this->addColumn($field, $definition);
        }
    }
}
