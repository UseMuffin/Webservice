<?php

namespace Muffin\Webservice\Test\test_app\Model\Endpoint\Schema;

use Muffin\Webservice\Model\Schema;

class UserSchema extends Schema
{
    public function initialize()
    {
        $columns = [
            'id' => ['type' => 'integer', 'primaryKey' => true],
            'username' => ['type' => 'string', 'null' => true],
            'password' => ['type' => 'string', 'null' => true],
            'created' => ['type' => 'timestamp', 'null' => true],
            'updated' => ['type' => 'timestamp', 'null' => true],
        ];

        foreach ($columns as $field => $definition) {
            $this->addColumn($field, $definition);
        }
    }
}
