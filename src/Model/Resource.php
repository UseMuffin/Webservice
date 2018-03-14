<?php

namespace Muffin\Webservice\Model;

use Cake\Datasource\EntityInterface;
use Cake\Datasource\EntityTrait;
use Cake\Datasource\InvalidPropertyInterface;

class Resource implements EntityInterface, InvalidPropertyInterface
{

    use EntityTrait;

    /**
     * Initializes the internal properties of this resource out of the
     * keys in an array. The following list of options can be used:
     *
     * - useSetters: whether use internal setters for properties or not
     * - markClean: whether to mark all properties as clean after setting them
     * - markNew: whether this instance has not yet been persisted
     * - guard: whether to prevent inaccessible properties from being set (default: false)
     * - source: A string representing the alias of the repository this resource came from
     *
     * ### Example:
     *
     * ```
     *  $resource = new Resource(['id' => 1, 'name' => 'Andrew'])
     * ```
     *
     * @param array $properties hash of properties to set in this resource
     * @param array $options list of options to use when creating this resource
     */
    public function __construct(array $properties = [], array $options = [])
    {
        $options += [
            'useSetters' => true,
            'markClean' => false,
            'markNew' => null,
            'guard' => false,
            'source' => null
        ];

        if (!empty($options['source'])) {
            $this->setSource($options['source']);
        }

        if ($options['markNew'] !== null) {
            $this->isNew($options['markNew']);
        }

        if (!empty($properties) && $options['markClean'] && !$options['useSetters']) {
            $this->_properties = $properties;

            return;
        }

        if (!empty($properties)) {
            $this->set($properties, [
                'setter' => $options['useSetters'],
                'guard' => $options['guard']
            ]);
        }

        if ($options['markClean']) {
            $this->clean();
        }
    }
}
