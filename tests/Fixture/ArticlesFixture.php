<?php

namespace Muffin\Webservice\Test\Fixture;

use Muffin\Webservice\TestSuite\TestFixture;

/**
 * Short description for class.
 *
 */
class ArticlesFixture extends TestFixture
{
    /**
     * records property
     *
     * @var array
     */
    public $records = [
        ['id' => 1, 'author_id' => 1, 'title' => 'First Article', 'body' => 'First Article Body', 'published' => 'Y'],
        ['id' => 2, 'author_id' => 3, 'title' => 'Second Article', 'body' => 'Second Article Body', 'published' => 'Y'],
        ['id' => 3, 'author_id' => 1, 'title' => 'Third Article', 'body' => 'Third Article Body', 'published' => 'Y']
    ];
}
