<?php

namespace Muffin\Webservice;

use Cake\Collection\CollectionTrait;
use Cake\Datasource\ResultSetInterface;

class ResultSet implements ResultSetInterface
{

    use CollectionTrait;

    /**
     * Points to the next record number that should be fetched
     *
     * @var int
     */
    protected $_index = 0;

    /**
     * Last record fetched from the statement
     *
     * @var array
     */
    protected $_current;

    /**
     * Results that have been fetched or hydrated into the results.
     *
     * @var array
     */
    protected $_results = [];

    /**
     * Total number of results
     *
     * @var int|null
     */
    protected $_total;

    /**
     * Construct the ResultSet
     *
     * @param array $resources The resources to attach
     * @param int|null $total The total amount of resources available
     */
    public function __construct(array $resources, $total = null)
    {
        $this->_results = \SplFixedArray::fromArray($resources, false);
        $this->_total = $total;
    }

    /**
     * {@inheritDoc}
     */
    public function current()
    {
        return $this->_current;
    }

    /**
     * {@inheritDoc}
     */
    public function rewind()
    {
        $this->_index = 0;
    }

    /**
     * {@inheritDoc}
     */
    public function serialize()
    {
        while ($this->valid()) {
            $this->next();
        }

        return serialize($this->_results);
    }

    /**
     * {@inheritDoc}
     */
    public function valid()
    {
        if (!isset($this->_results[$this->key()])) {
            return false;
        }

        $this->_current = $this->_results[$this->key()];

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function key()
    {
        return $this->_index;
    }

    /**
     * {@inheritDoc}
     */
    public function next()
    {
        $this->_index++;
    }

    /**
     * {@inheritDoc}
     */
    public function unserialize($serialized)
    {
        $this->_results = unserialize($serialized);
    }

    /**
     * {@inheritDoc}
     */
    public function count()
    {
        return count($this->_results);
    }

    /**
     * Returns the total amount of results
     *
     * @return int|null
     */
    public function total()
    {
        return $this->_total;
    }
}
