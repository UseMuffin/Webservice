<?php

namespace Muffin\Webservice;

interface WebserviceResultSetInterface extends \Iterator
{
    /**
     * Return the total amount of results available.
     *
     * @return int The total amount of results available.
     */
    public function total();
}
