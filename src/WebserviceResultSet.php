<?php

namespace Muffin\Webservice;

class WebserviceResultSet extends \ArrayIterator implements WebserviceResultSetInterface, \Countable
{
    /**
     * The total amount of results available.
     *
     * @var int
     */
    private $total;

    /**
     * Construct a webservice result set with a set of resources
     *
     * @param array $resources The array or object to be iterated on.
     * @param int $total The total amount of results available.
     * @param int $flags Flags to control the behaviour of the ArrayObject object.
     */
    public function __construct(array $resources, $total, $flags = 0)
    {
        parent::__construct($resources, $flags);

        $this->total = $total;
    }

    /**
     * {@inheritDoc}
     */
    public function total()
    {
        return $this->total;
    }

    /**
     * Return a result set with a single resource.
     *
     * @param array $resource Resource to include.
     * @return WebserviceResultSet A result set with a single resource.
     */
    public static function createForSingleResource(array $resource)
    {
        return new WebserviceResultSet([$resource], 1);
    }

    /**
     * Return an empty result set.
     *
     * @return WebserviceResultSet An empty result set.
     */
    public static function createEmpty()
    {
        return new WebserviceResultSet([], 0);
    }
}
