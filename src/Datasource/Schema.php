<?php
declare(strict_types=1);

namespace Muffin\Webservice\Datasource;

use Cake\Database\TypeFactory;
use Cake\Datasource\SchemaInterface;

/**
 * Represents a single endpoint in a database schema.
 *
 * Can either be populated using the reflection API's
 * or by incrementally building an instance using
 * methods.
 */
class Schema implements SchemaInterface
{
    /**
     * The name of the endpoint
     *
     * @var string
     */
    protected string $_repository;

    /**
     * Columns in the endpoint.
     *
     * @var array
     */
    protected array $_columns = [];

    /**
     * A map with columns to types
     *
     * @var array
     */
    protected array $_typeMap = [];

    /**
     * Indexes in the endpoint.
     *
     * @var array
     */
    protected array $_indexes = [];

    /**
     * Constraints in the endpoint.
     *
     * @var array
     */
    protected array $_constraints = [];

    /**
     * Options for the endpoint.
     *
     * @var array
     */
    protected array $_options = [];

    /**
     * Whether or not the endpoint is temporary
     *
     * @var bool
     */
    protected bool $_temporary = false;

    /**
     * The valid keys that can be used in a column
     * definition.
     *
     * @var array
     */
    protected static array $_columnKeys = [
        'type' => null,
        'baseType' => null,
        'length' => null,
        'precision' => null,
        'null' => null,
        'default' => null,
        'comment' => null,
        'primaryKey' => null,
    ];

    /**
     * Additional type specific properties.
     *
     * @var array
     */
    protected static array $_columnExtras = [
        'string' => [
            'fixed' => null,
        ],
        'integer' => [
            'unsigned' => null,
            'autoIncrement' => null,
        ],
        'biginteger' => [
            'unsigned' => null,
            'autoIncrement' => null,
        ],
        'decimal' => [
            'unsigned' => null,
        ],
        'float' => [
            'unsigned' => null,
        ],
    ];

    /**
     * Constructor.
     *
     * @param string $endpoint The endpoint name.
     * @param array $columns The list of columns for the schema.
     */
    public function __construct(string $endpoint, array $columns = [])
    {
        $this->_repository = $endpoint;
        foreach ($columns as $field => $definition) {
            $this->addColumn($field, $definition);
        }
    }

    /**
     * Get the name of the endpoint.
     *
     * @return string
     */
    public function name(): string
    {
        return $this->_repository;
    }

    /**
     * Add a column to the endpoint.
     *
     * ### Attributes
     *
     * Columns can have several attributes:
     *
     * - `type` The type of the column. This should be
     *   one of CakePHP's abstract types.
     * - `length` The length of the column.
     * - `precision` The number of decimal places to store
     *   for float and decimal types.
     * - `default` The default value of the column.
     * - `null` Whether or not the column can hold nulls.
     * - `fixed` Whether or not the column is a fixed length column.
     *   This is only present/valid with string columns.
     * - `unsigned` Whether or not the column is an unsigned column.
     * - `primaryKey` Whether or not the column is a primary key.
     *   This is only present/valid for integer, decimal, float columns.
     *
     * In addition to the above keys, the following keys are
     * implemented in some database dialects, but not all:
     *
     * - `comment` The comment for the column.
     *
     * @param string $name The name of the column
     * @param array|string $attrs The attributes for the column.
     * @return $this
     */
    public function addColumn(string $name, array|string $attrs)
    {
        if (is_string($attrs)) {
            $attrs = ['type' => $attrs];
        }
        $valid = static::$_columnKeys;
        if (isset(static::$_columnExtras[$attrs['type']])) {
            $valid += static::$_columnExtras[$attrs['type']];
        }
        $attrs = array_intersect_key($attrs, $valid);
        $this->_columns[$name] = $attrs + $valid;
        $this->_typeMap[$name] = $this->_columns[$name]['type'];

        return $this;
    }

    /**
     * Get the column names in the endpoint.
     *
     * @return array<string>
     */
    public function columns(): array
    {
        return array_keys($this->_columns);
    }

    /**
     * Get column data in the endpoint.
     *
     * @param string $name The column name.
     * @return array<string, mixed>|null Column data or null.
     */
    public function getColumn(string $name): ?array
    {
        if (!isset($this->_columns[$name])) {
            return null;
        }
        $column = $this->_columns[$name];
        unset($column['baseType']);

        return $column;
    }

    /**
     * Check if schema has column
     *
     * @param string $name The column name.
     * @return bool
     */
    public function hasColumn(string $name): bool
    {
        return isset($this->_columns[$name]);
    }

    /**
     * Remove a column from the table schema.
     *
     * If the column is not defined in the table, no error will be raised.
     *
     * @param string $name The name of the column
     * @return $this
     */
    public function removeColumn(string $name)
    {
        unset($this->_columns[$name], $this->_typeMap[$name]);

        return $this;
    }

    /**
     * Set the type of a column
     *
     * @param string $name Column name
     * @param string $type Type to set for the column
     * @return $this
     */
    public function setColumnType(string $name, string $type)
    {
        $this->_columns[$name]['type'] = $type;
        $this->_typeMap[$name] = $type;

        return $this;
    }

    /**
     * Get the type of a column
     *
     * @param string $name Column name
     * @return string|null
     */
    public function getColumnType(string $name): ?string
    {
        if (!isset($this->_columns[$name])) {
            return null;
        }

        return $this->_columns[$name]['type'];
    }

    /**
     * Returns the base type name for the provided column.
     * This represent the schema type a more complex class is
     * based upon.
     *
     * @param string $column The column name to get the base type from
     * @return string|null The base type name
     */
    public function baseColumnType(string $column): ?string
    {
        if (isset($this->_columns[$column]['baseType'])) {
            return $this->_columns[$column]['baseType'];
        }

        $type = $this->getColumnType($column);

        if ($type === null) {
            return null;
        }

        if (TypeFactory::getMap($type)) {
            $type = TypeFactory::build($type)->getBaseType();
        }

        return $this->_columns[$column]['baseType'] = $type;
    }

    /**
     * Returns an array where the keys are the column names in the schema
     * and the values the schema type they have.
     *
     * @return array<string, string>
     */
    public function typeMap(): array
    {
        return $this->_typeMap;
    }

    /**
     * Check whether or not a field is nullable
     *
     * Missing columns are nullable.
     *
     * @param string $name The column to get the type of.
     * @return bool Whether or not the field is nullable.
     */
    public function isNullable(string $name): bool
    {
        if (!isset($this->_columns[$name])) {
            return true;
        }

        return $this->_columns[$name]['null'] === true;
    }

    /**
     * Get a hash of columns and their default values.
     *
     * @return array<string, mixed>
     */
    public function defaultValues(): array
    {
        $defaults = [];
        foreach ($this->_columns as $name => $data) {
            if (!array_key_exists('default', $data)) {
                continue;
            }
            if ($data['default'] === null && $data['null'] !== true) {
                continue;
            }
            $defaults[$name] = $data['default'];
        }

        return $defaults;
    }

    /**
     * Get the column(s) used for the primary key.
     *
     * @return array Column name(s) for the primary key. An
     *   empty list will be returned when the endpoint has no primary key.
     */
    public function getPrimaryKey(): array
    {
        $primaryKeys = [];
        foreach ($this->_columns as $name => $data) {
            if ((!array_key_exists('primaryKey', $data)) || ($data['primaryKey'] !== true)) {
                continue;
            }

            $primaryKeys[] = $name;
        }

        return $primaryKeys;
    }

    /**
     * Set the schema options for an endpoint
     *
     * @param array<string, mixed> $options Array of options to set
     * @return $this
     */
    public function setOptions(array $options)
    {
        $this->_options = array_merge($this->_options, $options);

        return $this;
    }

    /**
     * Get the schema options
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->_options;
    }
}
