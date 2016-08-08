<?php

namespace Muffin\webservice\Test\Fixture;

use Muffin\Webservice\TestSuite\TestFixture;

/**
 * Short description for class.
 *
 */
class ArticlesTagsFixture extends TestFixture
{
    /**
     * records property
     *
     * @var array
     */
    public $records = [
        ['article_id' => 1, 'tag_id' => 1],
        ['article_id' => 1, 'tag_id' => 2],
        ['article_id' => 2, 'tag_id' => 1],
        ['article_id' => 2, 'tag_id' => 3]
    ];
}
