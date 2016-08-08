<?php

namespace Muffin\Webservice\Test\test_app\Model\Endpoint;

use Muffin\Webservice\Model\Endpoint;

/**
 * Tag endpoint class
 *
 */
class ArticlesTagsEndpoint extends Endpoint
{
    public function initialize(array $config)
    {
        $this->belongsTo('articles');
        $this->belongsTo('tags');
    }
}
