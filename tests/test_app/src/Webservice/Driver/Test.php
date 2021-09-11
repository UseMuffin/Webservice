<?php
declare(strict_types=1);

namespace TestApp\Webservice\Driver;

use Muffin\Webservice\Webservice\Driver\AbstractDriver;
use Muffin\Webservice\Webservice\WebserviceInterface;
use TestApp\Webservice\EndpointTestWebservice;

class Test extends AbstractDriver
{
    /**
     * Initialize is used to easily extend the constructor.
     *
     * @return void
     */
    public function initialize(): void
    {
    }

    /**
     * @inheritDoc
     */
    protected function _createWebservice(string $className, array $options = []): WebserviceInterface
    {
        return new EndpointTestWebservice($options);
    }
}
