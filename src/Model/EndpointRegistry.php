<?php

namespace Muffin\Webservice\Model;

use Cake\Core\App;
use Cake\Datasource\ConnectionManager;
use Cake\Utility\Inflector;
use RuntimeException;

class EndpointRegistry
{

    /**
     * Instances that belong to the registry.
     *
     * @var array
     */
    protected static $_instances = [];

    /**
     * @var array
     */
    protected static $_options = [];

    /**
     * Clear the endpoint registry of all instances
     *
     * @return void
     */
    public static function clear()
    {
        self::$_instances = [];
        self::$_options = [];
    }

    /**
     * Remove a specific endpoint instance from the registry by alias
     *
     * @param string $alias String alias of the endpoint
     * @return void
     */
    public static function remove($alias)
    {
        unset(
            self::$_instances[$alias],
            self::$_options[$alias]
        );
    }

    /**
     * Get a endpoint instance from the registry.
     *
     * @param string $alias The alias name you want to get.
     * @param array $options The options you want to build the endpoint with.
     *
     * @return \Muffin\Webservice\Model\Endpoint
     */
    public static function get($alias, array $options = [])
    {
        if (isset(self::$_instances[$alias])) {
            if (!empty($options) && self::$_options[$alias] !== $options) {
                throw new RuntimeException(sprintf(
                    'You cannot configure "%s", it already exists in the registry.',
                    $alias
                ));
            }

            return self::$_instances[$alias];
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
        self::$_instances[$alias] = self::_create($options);

        return self::$_instances[$alias];
    }

    /**
     * Wrapper for creating endpoint instances
     *
     * @param array $options The alias to check for.
     *
     * @return \Muffin\Webservice\Model\Endpoint
     */
    protected static function _create(array $options)
    {
        return new $options['className']($options);
    }
}
