<?php
namespace Muffin\Webservice;

use Cake\Core\InstanceConfigTrait;
use Muffin\Webservice\Exception\UnimplementedWebserviceMethodException;
use RuntimeException;

abstract class AbstractDriver
{
    use InstanceConfigTrait;

    protected $_client;

    protected $_defaultConfig = [];

    public function __construct($config) {
        $this->config($config);
        $this->initialize();
    }

    abstract public function initialize();

    public function __call($method, $args)
    {
        if (!is_object($this->_client)) {
            throw new RuntimeException(sprintf(
                'The `%s` client has not been initialized',
                $this->config('name')
            ));
        }

        if (!method_exists($this->_client, $method)) {
            throw new UnimplementedWebserviceMethodException([
                'name' => $this->config('name'),
                'method' => $method
            ]);
        }

        return call_user_func_array([$this->_client, $method], $args);
    }
}
