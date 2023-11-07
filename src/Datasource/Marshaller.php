<?php
declare(strict_types=1);

namespace Muffin\Webservice\Datasource;

use ArrayObject;
use Cake\Collection\Collection;
use Cake\Datasource\EntityInterface;
use Cake\Datasource\InvalidPropertyInterface;
use Muffin\Webservice\Model\Endpoint;
use RuntimeException;
use Traversable;

/**
 * Contains logic to convert array data into resources.
 *
 * Useful when converting request data into resources.
 */
class Marshaller
{
    /**
     * The endpoint instance this marshaller is for.
     *
     * @var \Muffin\Webservice\Model\Endpoint
     */
    protected Endpoint $_endpoint;

    /**
     * Constructor.
     *
     * @param \Muffin\Webservice\Model\Endpoint $endpoint The endpoint this marshaller is for.
     */
    public function __construct(Endpoint $endpoint)
    {
        $this->_endpoint = $endpoint;
    }

    /**
     * Hydrate one entity.
     *
     * ### Options:
     *
     * * fieldList: A whitelist of fields to be assigned to the entity. If not present,
     *   the accessible fields list in the entity will be used.
     * * accessibleFields: A list of fields to allow or deny in entity accessible fields.
     *
     * @param array $data The data to hydrate.
     * @param array $options List of options
     * @return \Cake\Datasource\EntityInterface
     * @see \Muffin\Webservice\Model\Endpoint::newEntity()
     */
    public function one(array $data, array $options = []): EntityInterface
    {
        [$data, $options] = $this->_prepareDataAndOptions($data, $options);

        $primaryKey = (array)$this->_endpoint->getPrimaryKey();
        /** @psalm-var class-string<\Muffin\Webservice\Model\Resource> */
        $resourceClass = $this->_endpoint->getResourceClass();
        $entity = new $resourceClass();
        $entity->setSource($this->_endpoint->getRegistryAlias());

        if (isset($options['accessibleFields'])) {
            foreach ((array)$options['accessibleFields'] as $key => $value) {
                $entity->setAccess($key, $value);
            }
        }

        $errors = $this->_validate($data, $options, true);
        $properties = [];
        foreach ($data as $key => $value) {
            if (!empty($errors[$key])) {
                $entity->setInvalidField($key, $value);
                continue;
            }
            if ($value === '' && in_array($key, $primaryKey, true)) {
                // Skip marshalling '' for pk fields.
                continue;
            }
            $properties[$key] = $value;
        }

        if (!isset($options['fieldList'])) {
            $entity->set($properties);
            $entity->setErrors($errors);

            return $entity;
        }

        foreach ((array)$options['fieldList'] as $field) {
            if (array_key_exists($field, $properties)) {
                $entity->set($field, $properties[$field]);
            }
        }

        $entity->setErrors($errors);

        return $entity;
    }

    /**
     * Returns the validation errors for a data set based on the passed options
     *
     * @param array $data The data to validate.
     * @param array $options The options passed to this marshaller.
     * @param bool $isNew Whether it is a new entity or one to be updated.
     * @return array The list of validation errors.
     * @throws \RuntimeException If no validator can be created.
     */
    protected function _validate(array $data, array $options, bool $isNew): array
    {
        if (!$options['validate']) {
            return [];
        }
        if ($options['validate'] === true) {
            $options['validate'] = $this->_endpoint->getValidator('default');
        }

        if (is_string($options['validate'])) {
            $options['validate'] = $this->_endpoint->getValidator($options['validate']);
        }
        if (!is_object($options['validate'])) {
            throw new RuntimeException(
                sprintf('validate must be a boolean, a string or an object. Got %s.', gettype($options['validate']))
            );
        }

        return $options['validate']->validate($data, $isNew);
    }

    /**
     * Returns data and options prepared to validate and marshall.
     *
     * @param array $data The data to prepare.
     * @param array $options The options passed to this marshaller.
     * @return array An array containing prepared data and options.
     */
    protected function _prepareDataAndOptions(array $data, array $options): array
    {
        $options += ['validate' => true];

        $endpointName = $this->_endpoint->getAlias();
        if (isset($data[$endpointName])) {
            $data = $data[$endpointName];
        }

        $data = new ArrayObject($data);
        $options = new ArrayObject($options);
        $this->_endpoint->dispatchEvent('Model.beforeMarshal', compact('data', 'options'));

        return [(array)$data, (array)$options];
    }

    /**
     * Hydrate many entities.
     *
     * ### Options:
     *
     * * fieldList: A whitelist of fields to be assigned to the entity. If not present,
     *   the accessible fields list in the entity will be used.
     * * accessibleFields: A list of fields to allow or deny in entity accessible fields.
     *
     * @param array $data The data to hydrate.
     * @param array $options List of options
     * @return array<\Cake\Datasource\EntityInterface> An array of hydrated records.
     * @see \Muffin\Webservice\Model\Endpoint::newEntities()
     */
    public function many(array $data, array $options = []): array
    {
        $output = [];
        foreach ($data as $record) {
            if (!is_array($record)) {
                continue;
            }
            $output[] = $this->one($record, $options);
        }

        return $output;
    }

    /**
     * Merges `$data` into `$entity`.
     *
     * ### Options:
     *
     * * validate: Whether or not to validate data before hydrating the entities. Can
     *   also be set to a string to use a specific validator. Defaults to true/default.
     * * fieldList: A whitelist of fields to be assigned to the entity. If not present
     *   the accessible fields list in the entity will be used.
     * * accessibleFields: A list of fields to allow or deny in entity accessible fields.
     *
     * @param \Cake\Datasource\EntityInterface $entity the entity that will get the
     * data merged in
     * @param array $data key value list of fields to be merged into the entity
     * @param array $options List of options.
     * @return \Cake\Datasource\EntityInterface
     */
    public function merge(EntityInterface $entity, array $data, array $options = []): EntityInterface
    {
        [$data, $options] = $this->_prepareDataAndOptions($data, $options);

        $isNew = $entity->isNew();
        $keys = [];

        if (!$isNew) {
            $keys = $entity->extract((array)$this->_endpoint->getPrimaryKey());
        }

        if (isset($options['accessibleFields'])) {
            foreach ((array)$options['accessibleFields'] as $key => $value) {
                $entity->setAccess($key, $value);
            }
        }

        $errors = $this->_validate($data + $keys, $options, $isNew);
        $properties = [];
        foreach ($data as $key => $value) {
            if (!empty($errors[$key])) {
                if ($entity instanceof InvalidPropertyInterface) {
                    $entity->setInvalidField($key, $value);
                }
                continue;
            }

            $properties[$key] = $value;
        }

        if (!isset($options['fieldList'])) {
            $entity->set($properties);
            $entity->setErrors($errors);

            return $entity;
        }

        foreach ((array)$options['fieldList'] as $field) {
            if (array_key_exists($field, $properties)) {
                $entity->set($field, $properties[$field]);
            }
        }

        $entity->setErrors($errors);

        return $entity;
    }

    /**
     * Merges each of the elements from `$data` into each of the entities in `$entities`.
     *
     * Records in `$data` are matched against the entities using the primary key
     * column. Entries in `$entities` that cannot be matched to any record in
     * `$data` will be discarded. Records in `$data` that could not be matched will
     * be marshalled as a new entity.
     *
     * ### Options:
     *
     * - fieldList: A whitelist of fields to be assigned to the entity. If not present,
     *   the accessible fields list in the entity will be used.
     * - accessibleFields: A list of fields to allow or deny in entity accessible fields.
     *
     * @param \Traversable|array $entities the entities that will get the
     *   data merged in
     * @param array $data list of arrays to be merged into the entities
     * @param array $options List of options.
     * @return array<\Cake\Datasource\EntityInterface>
     */
    public function mergeMany(array|Traversable $entities, array $data, array $options = []): array
    {
        $primary = (array)$this->_endpoint->getPrimaryKey();

        $indexed = (new Collection($data))
            ->groupBy(function ($el) use ($primary) {
                $keys = [];
                foreach ($primary as $key) {
                    $keys[] = $el[$key] ?? '';
                }

                return implode(';', $keys);
            })
            ->map(function ($element, $key) {
                return $key === '' ? $element : $element[0];
            })
            ->toArray();

        /** @psalm-suppress NullArrayOffset, InvalidArrayOffset */
        $new = $indexed[null] ?? [];
        /** @psalm-suppress PossiblyNullArrayOffset, InvalidArrayOffset */
        unset($indexed[null]);
        $output = [];

        foreach ($entities as $entity) {
            if (!($entity instanceof EntityInterface)) {
                continue;
            }

            $key = implode(';', $entity->extract($primary));
            if ($key === '' || !isset($indexed[$key])) {
                continue;
            }

            $output[] = $this->merge($entity, $indexed[$key], $options);
            unset($indexed[$key]);
        }

        foreach ((new Collection($indexed))->append($new) as $value) {
            if (!is_array($value)) {
                continue;
            }
            $output[] = $this->one($value, $options);
        }

        return $output;
    }
}
