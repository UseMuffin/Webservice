<?php
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

    protected $_logQueries = false;

    protected $_webservices = [];

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
     * Set or return an instance of the client used for communication
     *
     * @param object $client The client to use
     *
     * @return $this
     */
    public function client($client = null)
    {
        if ($client === null) {
            return $this->_client;
        }

        $this->_client = $client;

        return $this;
    }

    /**
     * Set or get a instance of a webservice
     *
     * @param string $name The name of the webservice
     * @param WebserviceInterface|null $webservice The instance of the webservice you'd like to set
     *
     * @return $this
     */
    public function webservice($name, WebserviceInterface $webservice = null)
    {
        if ($webservice !== null) {
            $this->_webservices[$name] = $webservice;

            return $this;
        }

        if (!isset($this->_webservices[$name])) {
            // Split the driver class namespace in chunks
            $namespaceParts = explode('\\', get_class($this));

            // Get the plugin name out of the namespace
            $pluginName = implode('/', array_reverse(array_slice(array_reverse($namespaceParts), -2)));

            $webserviceClass = $pluginName . '.' . Inflector::camelize($name);

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
     */
    public function logger()
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
     * @param bool|null $enable whether to turn logging on or disable it.
     *   Use null to read current value.
     *
     * @return bool
     */
    public function logQueries($enable = null)
    {
        if ($enable === null) {
            return $this->_logQueries;
        }

        $this->_logQueries = $enable;
    }

    /**
     * Proxies the client's methods.
     *
     * @param string $method Method name.
     * @param array $args Arguments to pass-through.
     *
     * @return mixed
     *
     * @throws \RuntimeException If the client object has not been initialized.
     * @throws \Muffin\Webservice\Exception\UnimplementedWebserviceMethodException If the method does not exist in the client.
     */
    public function __call($method, $args)
    {
        if (!is_object($this->client())) {
            throw new RuntimeException(sprintf(
                'The `%s` client has not been initialized',
                $this->config('name')
            ));
        }

        if (!method_exists($this->client(), $method)) {
            throw new UnimplementedWebserviceMethodException([
                'name' => $this->config('name'),
                'method' => $method
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
            'client' => $this->client(),
            'logger' => $this->logger(),
            'webservices' => array_keys($this->_webservices)
        ];
    }

    /**
     * Creates a Webservice instance.
     *
     * It provides a fallback to PluginNameWebservice.
     *
     * @param string $className Class name of the webservice to initialize
     * @param array $options Set of options to pass to the constructor
     *
     * @return WebserviceInterface
     */
    protected function _createWebservice($className, array $options = [])
    {
        $namespaceParts = explode('\\', get_class($this));

        $pluginName = implode('/', array_reverse(array_slice(array_reverse($namespaceParts), -2)));

        $webservice = App::className($className, 'Webservice', 'Webservice');
        if ($webservice) {
            return new $webservice($options);
        }

        $fallbackWebserviceClass = $pluginName . '.' . end($namespaceParts);
        $fallbackWebservice = App::className($fallbackWebserviceClass, 'Webservice', 'Webservice');
        if ($fallbackWebservice) {
            return new $fallbackWebservice($options);
        }

        throw new MissingWebserviceClassException([
            'class' => $className,
            'fallbackClass' => $fallbackWebserviceClass
        ]);
    }
}
