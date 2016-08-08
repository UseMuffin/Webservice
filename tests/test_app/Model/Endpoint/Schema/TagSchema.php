<?php

namespace Muffin\Webservice\Test\test_app\Model\Endpoint\Schema;

use Cake\Database\Schema\Table;
use Muffin\Webservice\Model\Schema;

class TagSchema extends Schema
{
    public function initialize()
    {
        $columns = [
            'id' => ['type' => 'integer', 'null' => false, 'primaryKey' => true],
            'name' => ['type' => 'string', 'null' => false],
            'description' => ['type' => 'text', 'length' => Table::LENGTH_MEDIUM],
        ];

        foreach ($columns as $field => $definition) {
            $this->addColumn($field, $definition);
        }
    }
}
