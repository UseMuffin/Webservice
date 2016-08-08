<?php

namespace Muffin\Webservice\TestSuite;

use Cake\Core\Exception\Exception as CakeException;
use Cake\Datasource\ConnectionInterface;
use Cake\Datasource\ConnectionManager;
use Cake\Datasource\FixtureInterface;
use Cake\Log\Log;
use Cake\Utility\Inflector;
use Exception;
use Muffin\Webservice\Model\Endpoint;
use Muffin\Webservice\Model\EndpointRegistry;
use Muffin\Webservice\Schema;

/**
 * Cake TestFixture is responsible for building and destroying tables to be used
 * during testing.
 */
class TestFixture implements FixtureInterface
{

    /**
     * Fixture Datasource
     *
     * @var string
     */
    public $connection = 'test';

    /**
     * Full Endpoint name
     *
     * @var string
     */
    public $table = null;

    /**
     * Fields / Schema for the fixture.
     *
     * This array should be compatible with \Muffin\Webservice\Schema.
     * The `_constraints`, `_options` and `_indexes` keys are reserved for defining
     * constraints, options and indexes respectively.
     *
     * @var array
     */
    public $fields = [];

    /**
     * Configuration for importing fixture schema
     *
     * Accepts a `connection` and `model` or `table` key, to define
     * which table and which connection contain the schema to be
     * imported.
     *
     * @var array|null
     */
    public $import = null;

    /**
     * Fixture records to be inserted.
     *
     * @var array
     */
    public $records = [];

    /**
     * The \Muffin\Webservice\Schemae for this fixture.
     *
     * @var \Muffin\Webservice\Schema
     */
    protected $_schema;

    /**
     * Fixture constraints to be created.
     *
     * @var array
     */
    protected $_constraints = [];

    /**
     * Instantiate the fixture.
     *
     * @throws \Cake\Core\Exception\Exception on invalid datasource usage.
     */
    public function __construct()
    {
        if (!empty($this->connection)) {
            $connection = $this->connection;
            if (strpos($connection, 'test') !== 0) {
                $message = sprintf(
                    'Invalid datasource name "%s" for "%s" fixture. Fixture datasource names must begin with "test".',
                    $connection,
                    $this->table
                );
                throw new CakeException($message);
            }
        }
        $this->init();
    }

    /**
     * {@inheritDoc}
     */
    public function connection()
    {
        return $this->connection;
    }

    /**
     * {@inheritDoc}
     */
    public function sourceName()
    {
        return $this->table;
    }

    /**
     * Initialize the fixture.
     *
     * @return void
     * @throws \Cake\ORM\Exception\MissingTableClassException When importing from a table that does not exist.
     */
    public function init()
    {
        if ($this->table === null) {
            $this->table = $this->_endpointFromClass();
        }

        if (empty($this->import) && !empty($this->fields)) {
            $this->_schemaFromFields();
        }

        if (!empty($this->import)) {
            $this->_schemaFromImport();
        }

        if (empty($this->import) && empty($this->fields)) {
            $this->_schemaFromReflection();
        }
    }

    /**
     * Returns the endpoint name using the fixture class
     *
     * @return string
     */
    protected function _endpointFromClass()
    {
        list(, $class) = namespaceSplit(get_class($this));
        preg_match('/^(.*)Fixture$/', $class, $matches);
        $endpoint = $class;

        if (isset($matches[1])) {
            $endpoint = $matches[1];
        }

        return Inflector::tableize($endpoint);
    }

    /**
     * Build the fixtures table schema from the fields property.
     *
     * @return void
     */
    protected function _schemaFromFields()
    {
        $this->_schema = new Schema($this->table);
        foreach ($this->fields as $field => $data) {
            if ($field === '_options') {
                continue;
            }
            $this->_schema->addColumn($field, $data);
        }
        if (!empty($this->fields['_options'])) {
            $this->_schema->options($this->fields['_options']);
        }
    }

    /**
     * Build fixture schema from a table in another datasource.
     *
     * @return void
     * @throws \Cake\Core\Exception\Exception when trying to import from an empty table.
     */
    protected function _schemaFromImport()
    {
        if (!is_array($this->import)) {
            return;
        }
        $import = $this->import + ['connection' => 'default', 'endpoint' => null, 'model' => null];

        if (!empty($import['model'])) {
            if (!empty($import['endpoint'])) {
                throw new CakeException('You cannot define both table and model.');
            }
            $import['endpoint'] = EndpointRegistry::get($import['model'])->endpoint();
        }

        if (empty($import['endpoint'])) {
            throw new CakeException('Cannot import from undefined endpoint.');
        }

        $this->table = $import['endpoint'];

        $connection = ConnectionManager::get($import['connection'], false);
        $schema = $connection->webservice($import['endpoint'])->describe($import['endpoint']);
        $this->_schema = $schema;
    }

    /**
     * Build fixture schema directly from the datasource
     *
     * @return void
     * @throws \Cake\Core\Exception\Exception when trying to reflect a table that does not exist
     */
    protected function _schemaFromReflection()
    {
        $connection = ConnectionManager::get($this->connection());

        $this->_schema = $connection->webservice($this->table)->describe($this->table);
    }

    /**
     * Get/Set the \Muffin\Webservice\Schema instance used by this fixture.
     *
     * @param \Muffin\Webservice\Schema|null $schema The schema to set.
     * @return \Muffin\Webservice\Schema|null
     */
    public function schema(Schema $schema = null)
    {
        if ($schema) {
            $this->_schema = $schema;
            return null;
        }
        return $this->_schema;
    }

    /**
     * {@inheritDoc}
     */
    public function create(ConnectionInterface $db)
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function drop(ConnectionInterface $db)
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function insert(ConnectionInterface $db)
    {
        if (isset($this->records) && !empty($this->records)) {
            $values = $this->_getRecords();

            $endpoint = new Endpoint([
                'connection' => $db,
                'alias' => Inflector::camelize($this->table)
            ]);

            foreach ($values as $record) {
                $resource = $endpoint->newEntity($record);

                if (!$endpoint->save($resource)) {
                    debug($resource);
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function createConstraints(ConnectionInterface $db)
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function dropConstraints(ConnectionInterface $db)
    {
        return true;
    }

    /**
     * Converts the internal records into data used to generate a query.
     *
     * @return array
     */
    protected function _getRecords()
    {
        $fields = $values = $types = [];
        $columns = $this->_schema->columns();
        foreach ($this->records as $record) {
            $fields = array_merge($fields, array_intersect(array_keys($record), $columns));
        }
        $fields = array_values(array_unique($fields));
        foreach ($fields as $field) {
            $types[$field] = $this->_schema->column($field)['type'];
        }
        $default = array_fill_keys($fields, null);
        foreach ($this->records as $record) {
            $values[] = array_merge($default, $record);
        }
        return $values;
    }

    /**
     * {@inheritDoc}
     */
    public function truncate(ConnectionInterface $db)
    {
        $endpoint = new Endpoint([
            'connection' => $db,
            'alias' => Inflector::camelize($this->table)
        ]);

        $endpoint->deleteAll([]);

        return true;
    }
}
