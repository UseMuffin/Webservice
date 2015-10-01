<?php

namespace Muffin\Webservice\Webservice;

use Muffin\Webservice\WebserviceQuery;

interface WebserviceInterface
{

    public function execute(WebserviceQuery $query);
}
