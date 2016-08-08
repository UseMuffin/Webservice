<?php

namespace Muffin\Webservice\Test\test_app\Model\Endpoint\Schema;

use Muffin\Webservice\Model\Schema;

class ArticlesTagSchema extends Schema
{
    public function initialize()
    {
        $columns = [
            'article_id' => ['type' => 'integer', 'null' => false, 'primaryKey' => true],
            'tag_id' => ['type' => 'integer', 'null' => false, 'primaryKey' => true],
        ];

        foreach ($columns as $field => $definition) {
            $this->addColumn($field, $definition);
        }
    }
}
