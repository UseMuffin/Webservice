<?php

namespace Muffin\Webservice\Test\test_app\Model\Endpoint\Schema;

use Muffin\Webservice\Model\Schema;

class CommentSchema extends Schema
{
    public function initialize()
    {
        $columns = [
            'id' => ['type' => 'integer', 'primaryKey' => true],
            'article_id' => ['type' => 'integer', 'null' => false],
            'user_id' => ['type' => 'integer', 'null' => false],
            'comment' => ['type' => 'text'],
            'published' => ['type' => 'string', 'length' => 1, 'default' => 'N'],
            'created' => ['type' => 'datetime'],
            'updated' => ['type' => 'datetime'],
        ];

        foreach ($columns as $field => $definition) {
            $this->addColumn($field, $definition);
        }
    }
}
