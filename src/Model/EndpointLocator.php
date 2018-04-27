<?php

namespace Muffin\Webservice\Model;

use Cake\Core\App;
use Cake\Datasource\ConnectionManager;
use Cake\Utility\Inflector;
use RuntimeException;

/**
 * Class EndpointLocator
 *
 * Should implement the LocatorInterface
 * @see \Cake\ORM\Locator\LocatorInterface
 * @see https://github.com/cakephp/cakephp/issues/12014
 */
class EndpointLocator
{
    /**
     * Configuration for aliases.
     *
     * @var array
     */
    protected $_config = [];

    /**
     * Instances that belong to the locator.
     *
     * @var \Muffin\Webservice\Model\Endpoint[]
     */
    protected $_instances = [];

    /**
     * Contains a list of options that were passed to get() method.
     *
     * @var array
     */
    protected $_options = [];

    /**
     * Set an Endpoint instance to the locator.
     *
     * @param string $alias The alias to set.
     * @param \Muffin\Webservice\Model\Endpoint $object The table to set.
     * @return \Muffin\Webservice\Model\Endpoint
     */
    public function set($alias, Endpoint $object)
    {
        return $this->_instances[$alias] = $object;
    }

    /**
     * Get a endpoint instance from the locator.
     *
     * @param string $alias The alias name you want to get.
     * @param array $options The options you want to build the endpoint with.
     * @return \Muffin\Webservice\Model\Endpoint
     * @throws \RuntimeException If the registry alias is already in use.
     */
    public function get($alias, array $options = [])
    {
        if (isset($this->_instances[$alias])) {
            if (!empty($options) && $this->_options[$alias] !== $options) {
                throw new RuntimeException(sprintf(
                    'You cannot configure "%s", it already exists in the locator.',
                    $alias
                ));
            }

            return $this->_instances[$alias];
        }

        list(, $classAlias) = pluginSplit($alias);
        $options = ['alias' => $classAlias] + $options;

        if (empty($options['className'])) {
            $options['className'] = Inflector::camelize($alias);
        }
        $className = App::className($options['className'], 'Model/Endpoint', 'Endpoint');
        if ($className) {
            $options['className'] = $className;
        } else {
            if (!isset($options['endpoint']) && strpos($options['className'], '\\') === false) {
                list(, $endpoint) = pluginSplit($options['className']);
                $options['endpoint'] = Inflector::underscore($endpoint);
            }
            $options['className'] = 'Muffin\Webservice\Model\Endpoint';
        }

        if (empty($options['connection'])) {
            if ($options['className'] !== 'Muffin\Webservice\Model\Endpoint') {
                $connectionName = $options['className']::defaultConnectionName();
            } else {
                $pluginParts = explode('/', pluginSplit($alias)[0]);

                $connectionName = Inflector::underscore(end($pluginParts));
            }

            $options['connection'] = ConnectionManager::get($connectionName);
        }

        $options['registryAlias'] = $alias;
        $this->_instances[$alias] = $this->_create($options);
        $this->_options[$alias] = $options;

        return $this->_instances[$alias];
    }

    /**
     * Check to see if an instance exists in the locator.
     *
     * @param string $alias The alias to check for.
     * @return bool
     */
    public function exists($alias)
    {
        return isset($this->_instances[$alias]);
    }

    /**
     * Returns configuration for an alias or the full configuration array for all aliases.
     *
     * @param string|null $alias Endpoint alias
     * @return array
     */
    public function getConfig($alias = null)
    {
        if ($alias === null) {
            return $this->_config;
        }

        return isset($this->_config[$alias]) ? $this->_config[$alias] : [];
    }

    /**
     * Stores a list of options to be used when instantiating an object with a matching alias.
     *
     * If configuring many aliases, use an array keyed by the alias.
     *
     * ```
     * $locator->setConfig([
     *     'Example' => ['registryAlias' => 'example'],
     *     'Posts' => ['registryAlias' => 'posts'],
     * ]);
     * ```
     *
     * @param string|array $alias The alias to set configuration for, or an array of configuration keyed by alias
     * @param null|array $config Array of configuration options
     * @return $this
     * @throws \RuntimeException
     */
    public function setConfig($alias, $config = null)
    {
        if (!is_string($alias)) {
            $this->_config = $alias;

            return $this;
        }

        if (isset($this->_instances[$alias])) {
            throw new RuntimeException(sprintf(
                'You cannot configure "%s", it has already been constructed.',
                $alias
            ));
        }

        $this->_config[$alias] = $config;

        return $this;
    }

    /**
     * Clear the endpoint locator of all instances
     *
     * @return void
     */
    public function clear()
    {
        $this->_instances = [];
        $this->_options = [];
        $this->_config = [];
    }

    /**
     * Remove a specific endpoint instance from the locator by alias
     *
     * @param string $alias String alias of the endpoint
     * @return void
     */
    public function remove($alias)
    {
        unset(
            $this->_instances[$alias],
            $this->_options[$alias],
            $this->_config[$alias]
        );
    }

    /**
     * Wrapper for creating endpoint instances
     *
     * @param array $options The alias to check for.
     * @return \Muffin\Webservice\Model\Endpoint
     */
    protected function _create(array $options)
    {
        return new $options['className']($options);
    }
}
