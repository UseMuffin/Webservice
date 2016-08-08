<?php

namespace Muffin\webservice\Test\Fixture;

use Cake\Database\Schema\Table;
use Muffin\Webservice\TestSuite\TestFixture;

/**
 * Class TagFixture
 *
 */
class TagsFixture extends TestFixture
{
    /**
     * records property
     *
     * @var array
     */
    public $records = [
        ['name' => 'tag1', 'description' => 'A big description'],
        ['name' => 'tag2', 'description' => 'Another big description'],
        ['name' => 'tag3', 'description' => 'Yet another one']
    ];
}
