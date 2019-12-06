<?php
declare(strict_types=1);

namespace Muffin\Webservice;

use Cake\Core\App;
use Cake\Core\InstanceConfigTrait;
use Cake\Utility\Inflector;
use Muffin\Webservice\Exception\MissingWebserviceClassException;
use Muffin\Webservice\Exception\UnimplementedWebserviceMethodException;
use Muffin\Webservice\Webservice\WebserviceInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use RuntimeException;

abstract class AbstractDriver implements LoggerAwareInterface
{
    use InstanceConfigTrait;
    use LoggerAwareTrait;

    protected $_client;

    protected $_defaultConfig = [];

    /**
     * Whatever queries should be logged
     *
     * @var bool
     */
    protected $_logQueries = false;

    /**
     * The list of webservices to be used
     *
     * @var array
     */
    protected $_webservices = [];

    /**
     * Constructor.
     *
     * @param array $config Custom configuration.
     */
    public function __construct($config = [])
    {
        if (!empty($config)) {
            $this->setConfig($config);
        }

        $this->initialize();
    }

    /**
     * Initialize is used to easily extend the constructor.
     *
     * @return void
     */
    abstract public function initialize();

    /**
     * Set or return an instance of the client used for communication
     *
     * @param object $client The client to use
     * @return object
     * @deprecated 2.0.0 Use setClient() and getClient() instead.
     */
    public function client($client = null)
    {
        if ($client === null) {
            return $this->getClient();
        }

        return $this->setClient($client);
    }

    /**
     * Set the client instance this driver will use to make requests
     *
     * @param object $client Client instance
     * @return $this
     */
    public function setClient($client)
    {
        $this->_client = $client;

        return $this;
    }

    /**
     * Get the client instance configured for this driver
     *
     * @return object
     */
    public function getClient()
    {
        return $this->_client;
    }

    /**
     * Set or get a instance of a webservice
     *
     * @param string $name The name of the webservice
     * @param \Muffin\Webservice\Webservice\WebserviceInterface|null $webservice The instance of the webservice you'd like to set
     * @return $this|\Muffin\Webservice\Webservice\WebserviceInterface
     * @deprecated 2.0.0 Use setWebservice() or getWebservice() instead.
     */
    public function webservice($name, ?WebserviceInterface $webservice = null)
    {
        if ($webservice !== null) {
            $this->setWebservice($name, $webservice);
        }

        return $this->getWebservice($name);
    }

    /**
     * Set the webservice instance used by the driver
     *
     * @param string $name The registry alias for the webservice instance
     * @param \Muffin\Webservice\Webservice\WebserviceInterface $webservice Instance of the webservice
     * @return $this
     */
    public function setWebservice($name, WebserviceInterface $webservice)
    {
        $this->_webservices[$name] = $webservice;

        return $this;
    }

    /**
     * Fetch a webservice instance from the driver registry
     *
     * @param string $name Registry alias to fetch
     * @return \Muffin\Webservice\Webservice\WebserviceInterface|null
     */
    public function getWebservice($name)
    {
        if (!isset($this->_webservices[$name])) {
            [$pluginName] = pluginSplit(App::shortName(static::class, 'Webservice/Driver'));

            $webserviceClass = implode('.', array_filter([$pluginName, Inflector::camelize($name)]));

            $webservice = $this->_createWebservice($webserviceClass, [
                'endpoint' => $name,
                'driver' => $this,
            ]);

            $this->_webservices[$name] = $webservice;
        }

        return $this->_webservices[$name];
    }

    /**
     * Returns a logger instance
     *
     * @return \Psr\Log\LoggerInterface
     *
     * @deprecated 1.4.0 Use getLogger() instead.
     */
    public function logger()
    {
        return $this->logger;
    }

    /**
     * Returns a logger instance
     *
     * @return \Psr\Log\LoggerInterface|null
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Returns the connection name used in the configuration
     *
     * @return string
     */
    public function configName()
    {
        return (string)$this->_config['name'];
    }

    /**
     * Enables or disables query logging for this driver
     *
     * @param bool|null $enable whether to turn logging on or disable it. Use null to read current value.
     * @return bool
     *
     * @deprecated 1.4.0 Use enableQueryLogging()/disableQueryLogging()/isQueryLoggingEnabled() instead.
     */
    public function logQueries($enable = null)
    {
        if ($enable === null) {
            return $this->_logQueries;
        }

        $this->_logQueries = $enable;
    }

    /**
     * Enable query logging for the driver
     *
     * @return $this
     */
    public function enableQueryLogging()
    {
        $this->_logQueries = true;

        return $this;
    }

    /**
     * Disable query logging for the driver
     *
     * @return $this
     */
    public function disableQueryLogging()
    {
        $this->_logQueries = false;

        return $this;
    }

    /**
     * Check if query logging is enabled.
     *
     * @return bool
     */
    public function isQueryLoggingEnabled()
    {
        return $this->_logQueries;
    }

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
        if (!is_object($this->getClient())) {
            throw new RuntimeException(sprintf(
                'The `%s` client has not been initialized',
                $this->getConfig('name')
            ));
        }

        if (!method_exists($this->getClient(), $method)) {
            throw new UnimplementedWebserviceMethodException([
                'name' => $this->getConfig('name'),
                'method' => $method,
            ]);
        }

        return call_user_func_array([$this->_client, $method], $args);
    }

    /**
     * Returns a handy representation of this driver
     *
     * @return array
     */
    public function __debugInfo()
    {
        return [
            'client' => $this->getClient(),
            'logger' => $this->logger(),
            'query_logging' => $this->isQueryLoggingEnabled(),
            'webservices' => array_keys($this->_webservices),
        ];
    }

    /**
     * Creates a Webservice instance.
     *
     * It provides a fallback to PluginNameWebservice.
     *
     * @param string $className Class name of the webservice to initialize
     * @param array $options Set of options to pass to the constructor
     * @return \Muffin\Webservice\Webservice\WebserviceInterface
     * @throws \Muffin\Webservice\Exception\MissingWebserviceClassException If no webservice class can be found
     */
    protected function _createWebservice($className, array $options = [])
    {
        $webservice = App::className($className, 'Webservice', 'Webservice');
        if ($webservice) {
            return new $webservice($options);
        }

        $namespaceParts = explode('\\', static::class);
        $fallbackWebserviceClass = end($namespaceParts);

        [$pluginName] = pluginSplit($className);
        if ($pluginName) {
            $fallbackWebserviceClass = $pluginName . '.' . $fallbackWebserviceClass;
        }

        $fallbackWebservice = App::className($fallbackWebserviceClass, 'Webservice', 'Webservice');
        if ($fallbackWebservice) {
            return new $fallbackWebservice($options);
        }

        throw new MissingWebserviceClassException([
            'class' => $className,
            'fallbackClass' => $fallbackWebserviceClass,
        ]);
    }
}
