<?php
declare(strict_types=1);

namespace Muffin\Webservice\Webservice\Driver;

use Cake\Core\App;
use Cake\Core\InstanceConfigTrait;
use Cake\Utility\Inflector;
use Muffin\Webservice\Webservice\Exception\MissingWebserviceClassException;
use Muffin\Webservice\Webservice\Exception\UnimplementedWebserviceMethodException;
use Muffin\Webservice\Webservice\WebserviceInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use function Cake\Core\pluginSplit;

abstract class AbstractDriver implements LoggerAwareInterface
{
    use InstanceConfigTrait;
    use LoggerAwareTrait;

    /**
     * Client
     *
     * @var object
     */
    protected object $_client;

    /**
     * Default config
     *
     * @var array
     */
    protected array $_defaultConfig = [];

    /**
     * Whatever queries should be logged
     *
     * @var bool
     */
    protected bool $_logQueries = false;

    /**
     * The list of webservices to be used
     *
     * @var array
     */
    protected array $_webservices = [];

    /**
     * Constructor.
     *
     * @param array $config Custom configuration.
     */
    public function __construct(array $config = [])
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
    abstract public function initialize(): void;

    /**
     * Set the client instance this driver will use to make requests
     *
     * @param object $client Client instance
     * @return $this
     */
    public function setClient(object $client): AbstractDriver
    {
        $this->_client = $client;

        return $this;
    }

    /**
     * Get the client instance configured for this driver
     *
     * @return object|null
     */
    public function getClient(): ?object
    {
        return $this->_client;
    }

    /**
     * Set the webservice instance used by the driver
     *
     * @param string $name The registry alias for the webservice instance
     * @param \Muffin\Webservice\Webservice\WebserviceInterface $webservice Instance of the webservice
     * @return $this
     */
    public function setWebservice(string $name, WebserviceInterface $webservice): AbstractDriver
    {
        $this->_webservices[$name] = $webservice;

        return $this;
    }

    /**
     * Fetch a webservice instance from the driver registry
     *
     * @param string $name Registry alias to fetch
     * @return \Muffin\Webservice\Webservice\WebserviceInterface
     */
    public function getWebservice(string $name): WebserviceInterface
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
     * Sets a logger
     *
     * @param \Psr\Log\LoggerInterface $logger Logger object
     * @return void
     * @psalm-suppress ImplementedReturnTypeMismatch
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Returns a logger instance
     *
     * @return \Psr\Log\LoggerInterface|null
     */
    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Returns the connection name used in the configuration
     *
     * @return string
     */
    public function configName(): string
    {
        return (string)$this->_config['name'];
    }

    /**
     * Enable query logging for the driver
     *
     * @return $this
     */
    public function enableQueryLogging(): AbstractDriver
    {
        $this->_logQueries = true;

        return $this;
    }

    /**
     * Disable query logging for the driver
     *
     * @return $this
     */
    public function disableQueryLogging(): AbstractDriver
    {
        $this->_logQueries = false;

        return $this;
    }

    /**
     * Check if query logging is enabled.
     *
     * @return bool
     */
    public function isQueryLoggingEnabled(): bool
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
     * @throws \Muffin\Webservice\Webservice\Exception\UnimplementedWebserviceMethodException If the method does not exist in the client.
     */
    public function __call(string $method, array $args): mixed
    {
        /** @psalm-suppress PossiblyNullArgument Only the left expression is executed if the getClient returns null **/
        if ($this->getClient() === null || !method_exists($this->getClient(), $method)) {
            throw new UnimplementedWebserviceMethodException([
                'name' => $this->getConfig('name'),
                'method' => $method,
            ]);
        }

        /* @phpstan-ignore-next-line This is supported behavior for now: https://www.php.net/manual/en/function.call-user-func-array.php (example 1) */
        return call_user_func_array([$this->_client, $method], $args);
    }

    /**
     * Returns a handy representation of this driver
     *
     * @return array
     */
    public function __debugInfo(): array
    {
        return [
            'client' => $this->getClient(),
            'logger' => $this->getLogger(),
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
     * @throws \Muffin\Webservice\Webservice\Exception\MissingWebserviceClassException If no webservice class can be found
     */
    protected function _createWebservice(string $className, array $options = []): WebserviceInterface
    {
        $webservice = App::className($className, 'Webservice', 'Webservice');
        if ($webservice) {
            /** @psalm-var \Muffin\Webservice\Webservice\WebserviceInterface */
            return new $webservice($options);
        }

        $namespaceParts = explode('\\', static::class);
        $fallbackWebserviceClass = end($namespaceParts);

        [$pluginName] = pluginSplit($className);
        if ($pluginName !== null) {
            $fallbackWebserviceClass = $pluginName . '.' . $fallbackWebserviceClass;
        }

        $fallbackWebservice = App::className($fallbackWebserviceClass, 'Webservice', 'Webservice');
        if ($fallbackWebservice) {
            /** @psalm-var \Muffin\Webservice\Webservice\WebserviceInterface */
            return new $fallbackWebservice($options);
        }

        throw new MissingWebserviceClassException([
            'class' => $className,
            'fallbackClass' => $fallbackWebserviceClass,
        ]);
    }
}
