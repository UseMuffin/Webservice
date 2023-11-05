<?php
declare(strict_types=1);

namespace Muffin\Webservice\Datasource;

use Cake\Collection\CollectionTrait;
use Cake\Datasource\ResultSetInterface;
use IteratorIterator;
use Muffin\Webservice\Model\Resource;

/** @package Muffin\Webservice\Datasource */
/**
 * @template T of \Cake\Datasource\EntityInterface|array
 * @implements \Cake\Datasource\ResultSetInterface<T>
 */
class ResultSet implements ResultSetInterface
{
    use CollectionTrait;

    /**
     * Points to the next record number that should be fetched
     *
     * @var int
     */
    protected int $_index = 0;

    /**
     * Last record fetched from the statement
     *
     * @var Resource
     */
    protected Resource $_current;

    /**
     * Results that have been fetched or hydrated into the results.
     *
     * @var array
     */
    protected array $_results = [];

    /**
     * Total number of results
     *
     * @var int|null
     */
    protected ?int $_total = null;

    /**
     * Construct the ResultSet
     *
     * @param array $resources The resources to attach
     * @param int|null $total The total amount of resources available
     */
    public function __construct(array $resources, ?int $total = null)
    {
        $this->_results = array_values($resources);
        $this->_total = $total;
    }

    /**
     * Returns the current record in the result iterator
     *
     * Part of Iterator interface.
     *
     * @return \Cake\Datasource\EntityInterface|array
     * @psalm-return T
     */
    #[\ReturnTypeWillChange]
    public function current()
    {
        return $this->_current;
    }

    /**
     * Rewinds a ResultSet.
     *
     * Part of Iterator interface.
     *
     * @return void
     */
    public function rewind(): void
    {
        $this->_index = 0;
    }

    /**
     * Serializes a resultset.
     *
     * Part of Serializable interface.
     *
     * @return string Serialized object
     */
    public function serialize(): string
    {
        while ($this->valid()) {
            $this->next();
        }

        return serialize($this->_results);
    }

    /**
     * Whether there are more results to be fetched from the iterator
     *
     * Part of Iterator interface.
     *
     * @return bool
     */
    public function valid(): bool
    {
        if (!isset($this->_results[$this->key()])) {
            return false;
        }

        $this->_current = $this->_results[$this->key()];

        return true;
    }

    /**
     * Returns the key of the current record in the iterator
     *
     * Part of Iterator interface.
     *
     * @return int
     */
    public function key(): int
    {
        return $this->_index;
    }

    /**
     * Advances the iterator pointer to the next record
     *
     * Part of Iterator interface.
     *
     * @return void
     */
    public function next(): void
    {
        $this->_index++;
    }

    /**
     * Unserializes a resultset.
     *
     * Part of Serializable interface.
     *
     * @param string $serialized Serialized object
     * @return void
     */
    public function unserialize(string $serialized): void
    {
        $this->_results = unserialize($serialized);
    }

    /**
     * Gives the number of rows in the result set.
     *
     * Part of the Countable interface.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->_results);
    }

    /**
     * Returns the total amount of results
     *
     * @return int|null
     */
    public function total(): ?int
    {
        return $this->_total;
    }
}
