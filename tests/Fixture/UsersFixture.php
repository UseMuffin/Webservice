<?php

namespace Muffin\Webservice\Test\Fixture;

use Muffin\Webservice\TestSuite\TestFixture;

class UsersFixture extends TestFixture
{
    public $records = [
        [
            'id' => 1,
            'username' => 'user1',
        ],
        [
            'id' => 2,
            'username' => 'user2',
        ],
        [
            'id' => 3,
            'username' => 'user3',
        ]
    ];
}
