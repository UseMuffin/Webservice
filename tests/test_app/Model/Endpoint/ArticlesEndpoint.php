<?php

namespace Muffin\Webservice\Test\test_app\Model\Endpoint;

use Muffin\Webservice\Model\Endpoint;

/**
 * Article endpoint class
 *
 */
class ArticlesEndpoint extends Endpoint
{
    public function initialize(array $config)
    {
        $this->belongsTo('authors');
        $this->belongsToMany('tags');
        $this->hasMany('ArticlesTags');
    }

    /**
     * Find published
     *
     * @param \Cake\ORM\Query $query The query
     * @return \Cake\ORM\Query
     */
    public function findPublished($query)
    {
        return $query->where(['published' => 'Y']);
    }

    /**
     * Example public method
     *
     * @return void
     */
    public function doSomething()
    {
    }

    /**
     * Example Secondary public method
     *
     * @return void
     */
    public function doSomethingElse()
    {
    }

    /**
     * Example protected method
     *
     * @return void
     */
    protected function _innerMethod()
    {
    }
}
