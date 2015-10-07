<?php

namespace Muffin\Webservice\Webservice;

use Muffin\Webservice\Query;

interface WebserviceInterface
{

    public function execute(Query $query);
}
