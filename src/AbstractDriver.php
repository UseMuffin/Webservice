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
     * @param object $client
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

    public function webservice($name, WebserviceInterface $webservice = null)
    {
        if ($webservice === null) {
            if (!isset($this->_webservices[$name])) {
                $namespaceParts = explode('\\', get_class($this));

                $pluginName = implode('/', array_reverse(array_slice(array_reverse($namespaceParts), -2)));

                $webserviceClass = $pluginName . '.' . Inflector::camelize($name);
                $webservice = App::className($webserviceClass, 'Webservice', 'Webservice');
                if (!$webservice) {
                    $fallbackWebserviceClass = $pluginName . '.' . end($namespaceParts);
                    $webservice = App::className($fallbackWebserviceClass, 'Webservice', 'Webservice');

                    if (!$webservice) {
                        throw new MissingWebserviceClassException([
                            'class' => $webserviceClass,
                            'fallbackClass' => $fallbackWebserviceClass
                        ]);
                    }
                }

                $webservice = new $webservice([
                    'endpoint' => $name,
                    'driver' => $this,
                ]);

                $this->_webservices[$name] = $webservice;
            }

            return $this->_webservices[$name];
        }

        $this->_webservices[$name] = $webservice;

        return $this;
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
        if (empty($this->_config['name'])) {
            return '';
        }
        return $this->_config['name'];
    }

    /**
     * Enables or disables query logging for this driver
     *
     * @param boolean|null $enable whether to turn logging on or disable it.
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
     * @return mixed
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

    public function __debugInfo()
    {
        return [
            'client' => $this->client(),
            'logger' => $this->logger(),
            'webservices' => array_keys($this->_webservices)
        ];
    }
}
