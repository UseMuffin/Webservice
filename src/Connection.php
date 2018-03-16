<?php
namespace Muffin\Webservice;

use Cake\Core\App;
use Muffin\Webservice\Exception\MissingConnectionException;
use Muffin\Webservice\Exception\MissingDriverException;
use Muffin\Webservice\Exception\UnexpectedDriverException;

/**
 * Class Connection
 *
 * @method Webservice\WebserviceInterface setWebservice(string $name) Proxy method through to the Driver
 * @method Webservice\WebserviceInterface getWebservice(string $name) Proxy method through to the Driver
 * @method string configName() Proxy method through to the Driver
 */
class Connection
{
    /**
     * Driver
     *
     * @var \Muffin\Webservice\AbstractDriver
     */
    protected $_driver;

    /**
     * Constructor
     *
     * @param array $config Custom configuration.
     * @throws \Muffin\Webservice\Exception\UnexpectedDriverException If the driver is not an instance of `Muffin\Webservice\AbstractDriver`.
     */
    public function __construct($config)
    {
        $config = $this->_normalizeConfig($config);
        $driver = $config['driver'];
        unset($config['driver'], $config['service']);

        $this->_driver = new $driver($config);

        if (!($this->_driver instanceof AbstractDriver)) {
            throw new UnexpectedDriverException(['driver' => $driver]);
        }
    }

    /**
     * Validates certain custom configuration values.
     *
     * @param array $config Raw custom configuration.
     * @return array
     * @throws \Muffin\Webservice\Exception\MissingConnectionException If the connection does not exist.
     * @throws \Muffin\Webservice\Exception\MissingDriverException If the driver does not exist.
     */
    protected function _normalizeConfig($config)
    {
        if (empty($config['driver'])) {
            if (empty($config['service'])) {
                throw new MissingConnectionException(['name' => $config['name']]);
            }

            if (!$config['driver'] = App::className($config['service'], 'Webservice/Driver')) {
                throw new MissingDriverException(['driver' => $config['driver']]);
            }
        }

        return $config;
    }

    /**
     * Proxies the driver's methods.
     *
     * @param string $method Method name.
     * @param array $args Arguments to pass-through
     * @return mixed
     */
    public function __call($method, $args)
    {
        return call_user_func_array([$this->_driver, $method], $args);
    }
}
