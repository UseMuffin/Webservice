<?php

namespace Muffin\Webservice\Test\test_app\Model\Endpoint\Schema;

use Muffin\Webservice\Model\Schema;

class SpecialTagSchema extends Schema
{
    public function initialize()
    {
        $columns = [
            'id' => ['type' => 'integer', 'primaryKey' => true],
            'article_id' => ['type' => 'integer', 'null' => false],
            'tag_id' => ['type' => 'integer', 'null' => false],
            'highlighted' => ['type' => 'boolean', 'null' => true],
            'highlighted_time' => ['type' => 'timestamp', 'null' => true],
            'author_id' => ['type' => 'integer', 'null' => true],
        ];

        foreach ($columns as $field => $definition) {
            $this->addColumn($field, $definition);
        }
    }
}
