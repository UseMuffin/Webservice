<?php

namespace Muffin\Webservice\Model\Schema;

use Cake\Datasource\ConnectionInterface;

/**
 * Represents a database schema collection
 *
 * Used to access information about the tables,
 * and other data in a database.
 */
class Collection
{

    /**
     * Connection object
     *
     * @var \Cake\Datasource\ConnectionInterface
     */
    protected $_connection;

    /**
     * Constructor.
     *
     * @param \Cake\Datasource\ConnectionInterface $connection The connection instance.
     */
    public function __construct(ConnectionInterface $connection)
    {
        $this->_connection = $connection;
    }

    /**
     * Get the column metadata for a table.
     *
     * Caching will be applied if `cacheMetadata` key is present in the Connection
     * configuration options. Defaults to _cake_model_ when true.
     *
     * @param string $name The name of the table to describe.
     * @param array $options The options to use, see above.
     * @return \Muffin\Webservice\Schema Object with column metadata.
     */
    public function describe($name, array $options = [])
    {
        $config = $this->_connection->config();
        if (strpos($name, '.')) {
            list($config['schema'], $name) = explode('.', $name);
        }

        return $this->_connection->webservice($name)->describe($name);
    }

    public function listTables()
    {
        return [];
    }
}
