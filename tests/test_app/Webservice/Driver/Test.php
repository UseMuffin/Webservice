<?php
declare(strict_types=1);

namespace Muffin\Webservice\Test\test_app\Webservice\Driver;

use Muffin\Webservice\AbstractDriver;
use Muffin\Webservice\Test\test_app\Webservice\EndpointTestWebservice;

class Test extends AbstractDriver
{
    /**
     * Initialize is used to easily extend the constructor.
     *
     * @return void
     */
    public function initialize()
    {
    }

    /**
     * @inheritDoc
     */
    protected function _createWebservice($className, array $options = [])
    {
        return new EndpointTestWebservice($options);
    }
}
