<?php

namespace Muffin\webservice\Test\Fixture;

use Muffin\Webservice\TestSuite\TestFixture;

/**
 * A fixture for a join table containing additional data
 *
 */
class SpecialTagsFixture extends TestFixture
{
    /**
     * records property
     *
     * @var array
     */
    public $records = [
        ['article_id' => 1, 'tag_id' => 3, 'highlighted' => false, 'highlighted_time' => null, 'author_id' => 1],
        ['article_id' => 2, 'tag_id' => 1, 'highlighted' => true, 'highlighted_time' => '2014-06-01 10:10:00', 'author_id' => 2],
        ['article_id' => 10, 'tag_id' => 10, 'highlighted' => true, 'highlighted_time' => '2014-06-01 10:10:00', 'author_id' => null]
    ];
}
