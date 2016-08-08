<?php
namespace Muffin\Webservice;

use Cake\Core\App;
use Cake\Datasource\ConnectionInterface;
use Muffin\Webservice\Exception\MissingConnectionException;
use Muffin\Webservice\Exception\MissingDriverException;
use Muffin\Webservice\Exception\UnexpectedDriverException;
use Muffin\Webservice\Model\Schema\Collection;

class Connection implements ConnectionInterface
{
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

    public function schemaCollection()
    {
        return new Collection($this);
    }

    /**
     * Get the configuration name for this connection.
     *
     * @return string
     */
    public function configName()
    {
        // TODO: Implement configName() method.
    }

    /**
     * Get the configuration data used to create the connection.
     *
     * @return array
     */
    public function config()
    {
        // TODO: Implement config() method.
    }

    /**
     * Executes a callable function inside a transaction, if any exception occurs
     * while executing the passed callable, the transaction will be rolled back
     * If the result of the callable function is `false`, the transaction will
     * also be rolled back. Otherwise the transaction is committed after executing
     * the callback.
     *
     * The callback will receive the connection instance as its first argument.
     *
     * @param callable $transaction The callback to execute within a transaction.
     * @return mixed The return value of the callback.
     * @throws \Exception Will re-throw any exception raised in $callback after
     *   rolling back the transaction.
     */
    public function transactional(callable $transaction)
    {
        return $transaction($this);
    }

    /**
     * Run an operation with constraints disabled.
     *
     * Constraints should be re-enabled after the callback succeeds/fails.
     *
     * @param callable $operation The callback to execute within a transaction.
     * @return mixed The return value of the callback.
     * @throws \Exception Will re-throw any exception raised in $callback after
     *   rolling back the transaction.
     */
    public function disableConstraints(callable $operation)
    {
        return $operation($this);
    }

    /**
     * Enables or disables query logging for this connection.
     *
     * @param bool|null $enable whether to turn logging on or disable it.
     *   Use null to read current value.
     * @return bool
     */
    public function logQueries($enable = null)
    {
        // TODO: Implement logQueries() method.
    }

    /**
     * Sets the logger object instance. When called with no arguments
     * it returns the currently setup logger instance.
     *
     * @param object|null $instance logger object instance
     * @return object logger instance
     */
    public function logger($instance = null)
    {
        // TODO: Implement logger() method.
    }
}
