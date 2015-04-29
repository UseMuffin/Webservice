<?php
namespace Muffin\Webservice;

use Cake\Core\App;
use Muffin\Webservice\Exception\MissingConnectionException;
use Muffin\Webservice\Exception\MissingDriverException;
use Muffin\Webservice\Exception\UnexpectedDriverException;

class Connection
{
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

    public function __call($method, $args)
    {
        return call_user_func_array([$this->_driver, $method], $args);
    }
}
