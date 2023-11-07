<?php
declare(strict_types=1);

namespace Muffin\Webservice\Datasource;

use Cake\Core\App;
use Cake\Datasource\ConnectionInterface;
use Muffin\Webservice\Datasource\Exception\MissingConnectionException;
use Muffin\Webservice\Webservice\Driver\AbstractDriver;
use Muffin\Webservice\Webservice\Exception\MissingDriverException;
use Muffin\Webservice\Webservice\Exception\UnexpectedDriverException;
use Psr\SimpleCache\CacheInterface;

/**
 * Class Connection
 *
 * @method \Muffin\Webservice\Webservice\Driver\AbstractDriver setWebservice(string $name, \Muffin\Webservice\Webservice\WebserviceInterface $webservice) Proxy method through to the Driver
 * @method \Muffin\Webservice\Webservice\WebserviceInterface getWebservice(string $name) Proxy method through to the Driver
 * @method string configName() Proxy method through to the Driver
 */
class Connection implements ConnectionInterface
{
    /**
     * Driver
     *
     * @var \Muffin\Webservice\Webservice\Driver\AbstractDriver
     */
    protected ?AbstractDriver $_driver = null;

    protected CacheInterface $cacher;

    /**
     * The connection name in the connection manager.
     */
    protected string $configName = '';

    /**
     * Constructor
     *
     * @param array $config Custom configuration.
     * @throws \Muffin\Webservice\Webservice\Exception\UnexpectedDriverException If the driver is not an instance of `Muffin\Webservice\AbstractDriver`.
     */
    public function __construct(array $config)
    {
        if (isset($config['name'])) {
            $this->configName = $config['name'];
        }
        $config = $this->_normalizeConfig($config);
        $driver = $config['driver'];
        unset($config['driver'], $config['service']);

        $this->_driver = new $driver($config);

        if (!($this->_driver instanceof AbstractDriver)) {
            throw new UnexpectedDriverException(['driver' => $driver]);
        }
    }

    /**
     * @param \Psr\SimpleCache\CacheInterface $cacher
     * @return void
     */
    public function setCacher(CacheInterface $cacher): void
    {
    }

    /** @return \Psr\SimpleCache\CacheInterface  */
    public function getCacher(): CacheInterface
    {
    }

    /**
     * {@inheritDoc}
     *
     * @see \Cake\Datasource\ConnectionInterface::getDriver()
     * @return \Muffin\Webservice\Webservice\Driver\AbstractDriver
     */
    public function getDriver(string $role = self::ROLE_WRITE): object
    {
        return $this->_driver;
    }

    /**
     * Get the configuration name for this connection.
     *
     * @return string
     */
    public function configName(): string
    {
        return $this->configName;
    }

    /**
     * Get the config data for this connection.
     *
     * @return array
     */
    public function config(): array
    {
        return $this->_driver->getConfig();
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

            $config['driver'] = App::className($config['service'], 'Webservice/Driver', 'Driver');
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
    public function __call(string $method, array $args): mixed
    {
        return call_user_func_array([$this->_driver, $method], $args);
    }
}
