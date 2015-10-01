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

    protected $_total;

    public function __construct(array $resources, $total = null)
    {
        $this->_results = \SplFixedArray::fromArray($resources, false);
        $this->_total = $total;
    }


    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     */
    public function current()
    {
        return $this->_current;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Rewind the Iterator to the first element
     * @link http://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     */
    public function rewind()
    {
        $this->_index = 0;
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * String representation of object
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     */
    public function serialize()
    {
        while ($this->valid()) {
            $this->next();
        }
        return serialize($this->_results);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Checks if current position is valid
     * @link http://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
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
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the key of the current element
     * @link http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     */
    public function key()
    {
        return $this->_index;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     */
    public function next()
    {
        $this->_index++;
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * Constructs the object
     * @link http://php.net/manual/en/serializable.unserialize.php
     * @param string $serialized <p>
     * The string representation of the object.
     * </p>
     * @return void
     */
    public function unserialize($serialized)
    {
        $this->_results = unserialize($serialized);
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * Count elements of an object
     * @link http://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     * </p>
     * <p>
     * The return value is cast to an integer.
     */
    public function count()
    {
        return count($this->_results);
    }

    public function total()
    {
        return $this->_total;
    }
}
