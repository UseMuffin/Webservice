<?php

namespace Muffin\Webservice;

class StreamingWebserviceResultSet implements WebserviceResultSetInterface, \OuterIterator
{
    /**
     * @var \Generator
     */
    private $generator;

    /**
     * Construct the StreamingWebserviceResultSet.
     *
     * @param \Generator $generator The Generator to use.
     */
    public function __construct(\Generator $generator)
    {
        $this->generator = $generator;
    }

    /**
     * {@inheritDoc}
     */
    public function current()
    {
        return $this->generator->current();
    }

    /**
     * {@inheritDoc}
     */
    public function next()
    {
        $this->generator->next();
    }

    /**
     * {@inheritDoc}
     */
    public function key()
    {
        return $this->generator->key();
    }

    /**
     * {@inheritDoc}
     */
    public function valid()
    {
        return $this->generator->valid();
    }

    /**
     * {@inheritDoc}
     */
    public function rewind()
    {
        $this->generator->rewind();
    }

    /**
     * {@inheritDoc}
     */
    public function getInnerIterator()
    {
        return $this->generator;
    }

    /**
     * There's no total when using a streaming webservice.
     *
     * @return null Return null.
     */
    public function total()
    {
        return null;
    }
}
