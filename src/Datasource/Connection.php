<?php
declare(strict_types=1);

namespace Muffin\Webservice\Datasource;

use Cake\Core\App;
use Muffin\Webservice\Datasource\Exception\MissingConnectionException;
use Muffin\Webservice\Webservice\Driver\AbstractDriver;
use Muffin\Webservice\Webservice\Exception\MissingDriverException;
use Muffin\Webservice\Webservice\Exception\UnexpectedDriverException;

/**
 * Class Connection
 *
 * @method \Muffin\Webservice\Webservice\Driver\AbstractDriver setWebservice(string $name, \Muffin\Webservice\Webservice\WebserviceInterface $webservice) Proxy method through to the Driver
 * @method \Muffin\Webservice\Webservice\WebserviceInterface getWebservice(string $name) Proxy method through to the Driver
 * @method string configName() Proxy method through to the Driver
 */
class Connection
{
    /**
     * Driver
     *
     * @var \Muffin\Webservice\Webservice\Driver\AbstractDriver
     */
    protected $_driver;

    /**
     * Constructor
     *
     * @param array $config Custom configuration.
     * @throws \Muffin\Webservice\Webservice\Exception\UnexpectedDriverException If the driver is not an instance of `Muffin\Webservice\AbstractDriver`.
     */
    public function __construct(array $config)
    {
        $config = $this->_normalizeConfig($config);
        /** @psalm-var class-string<\Muffin\Webservice\Webservice\Driver\AbstractDriver> */
        $driver = $config['driver'];
        unset($config['driver'], $config['service']);

        $this->_driver = new $driver($config);

        /** @psalm-suppress TypeDoesNotContainType */
        if (!($this->_driver instanceof AbstractDriver)) {
            throw new UnexpectedDriverException(['driver' => $driver]);
        }
    }

    /**
     * Validates certain custom configuration values.
     *
     * @param array $config Raw custom configuration.
     * @return array
     * @throws \Muffin\Webservice\Datasource\Exception\MissingConnectionException If the connection does not exist.
     * @throws \Muffin\Webservice\Webservice\Exception\MissingDriverException If the driver does not exist.
     */
    protected function _normalizeConfig(array $config): array
    {
        if (empty($config['driver'])) {
            if (empty($config['service'])) {
                throw new MissingConnectionException(['name' => $config['name']]);
            }

            $config['driver'] = App::className($config['service'], 'Webservice/Driver');
            if (!$config['driver']) {
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
