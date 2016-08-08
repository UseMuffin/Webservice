<?php

namespace Muffin\Webservice\Test\test_app\Model\Endpoint\Schema;

use Muffin\Webservice\Model\Schema;

class ArticleSchema extends Schema
{
    public function initialize()
    {
        $columns = [
            'id' => ['type' => 'integer', 'primaryKey' => true],
            'author_id' => ['type' => 'integer', 'null' => true],
            'title' => ['type' => 'string', 'null' => true],
            'body' => 'text',
            'published' => ['type' => 'string', 'length' => 1, 'default' => 'N'],
        ];

        foreach ($columns as $field => $definition) {
            $this->addColumn($field, $definition);
        }
    }
}
