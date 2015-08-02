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

    /**
     * Constructor.
     *
     * @param array $config Custom configuration.
     */
    public function __construct($config)
    {
        $this->config($config);
        $this->initialize();
    }

    /**
     * Initialize is used to easily extend the constructor.
     *
     * @return void
     */
    abstract public function initialize();

    /**
     * Proxies the client's methods.
     *
     * @param string $method Method name.
     * @param array $args Arguments to pass-through.
     * @return mixed
     * @throws \RuntimeException If the client object has not been initialized.
     * @throws \Muffin\Webservice\Exception\UnimplementedWebserviceMethodException If the method does not exist in the client.
     */
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
