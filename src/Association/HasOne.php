<?php

namespace Muffin\Webservice\Association;

use Cake\Datasource\EntityInterface;
use Cake\ORM\Association\DependentDeleteTrait;
use Cake\Utility\Inflector;
use Muffin\Webservice\Association;
use Muffin\Webservice\Model\Endpoint;

/**
 * Represents an 1 - 1 relationship where the source side of the relation is
 * related to only one record in the target endpoint and vice versa.
 *
 * An example of a HasOne association would be User has one Profile.
 */
class HasOne extends Association
{
    use DependentDeleteTrait;
    use QueryableAssociationTrait;

    /**
     * Sets the name of the field representing the foreign key to the target endpoint.
     * If no parameters are passed current field is returned
     *
     * @param string|null $key the key to be used to link both endpoints together
     * @return string
     */
    public function foreignKey($key = null)
    {
        if ($key === null) {
            if ($this->_foreignKey === null) {
                $this->_foreignKey = $this->_modelKey($this->source()->alias());
            }
            return $this->_foreignKey;
        }
        return parent::foreignKey($key);
    }

    /**
     * Returns default property name based on association name.
     *
     * @return string
     */
    protected function _propertyName()
    {
        list(, $name) = pluginSplit($this->_name);
        return Inflector::underscore(Inflector::singularize($name));
    }

    /**
     * Returns whether or not the passed endpoint is the owning side for this
     * association. This means that rows in the 'target' endpoint would miss important
     * or required information if the row in 'source' did not exist.
     *
     * @param \Muffin\Webservice\Model\Endpoint $side The potential Endpoint with ownership
     * @return bool
     */
    public function isOwningSide(Endpoint $side)
    {
        return $side === $this->source();
    }

    /**
     * Get the relationship type.
     *
     * @return string
     */
    public function type()
    {
        return self::ONE_TO_ONE;
    }

    /**
     * Takes an entity from the source endpoint and looks if there is a field
     * matching the property name for this association. The found entity will be
     * saved on the target endpoint for this association by passing supplied
     * `$options`
     *
     * @param \Cake\Datasource\EntityInterface $entity an entity from the source endpoint
     * @param array|\ArrayObject $options options to be passed to the save method in
     * the target endpoint
     * @return bool|\Cake\Datasource\EntityInterface false if $entity could not be saved, otherwise it returns
     * the saved entity
     * @see \Muffin\Webservice\Model\Endpoint::save()
     */
    public function saveAssociated(EntityInterface $entity, array $options = [])
    {
        $targetEntity = $entity->get($this->property());
        if (empty($targetEntity) || !($targetEntity instanceof EntityInterface)) {
            return $entity;
        }

        $properties = array_combine(
            (array)$this->foreignKey(),
            $entity->extract((array)$this->bindingKey())
        );
        $targetEntity->set($properties, ['guard' => false]);

        if (!$this->target()->save($targetEntity, $options)) {
            $targetEntity->unsetProperty(array_keys($properties));
            return false;
        }

        return $entity;
    }

    /**
     * {@inheritDoc}
     */
    protected function _linkField($options)
    {
        $links = [];
        $name = $this->alias();

        foreach ((array)$options['foreignKey'] as $key) {
            $links[] = sprintf('%s.%s', $name, $key);
        }

        if (count($links) === 1) {
            return $links[0];
        }

        return $links;
    }

    /**
     * {@inheritDoc}
     */
    protected function _buildResultMap($fetchQuery, $options)
    {
        $resultMap = [];
        $key = (array)$options['foreignKey'];

        foreach ($fetchQuery->all() as $result) {
            $values = [];
            foreach ($key as $k) {
                $values[] = $result[$k];
            }
            $resultMap[implode(';', $values)] = $result;
        }
        return $resultMap;
    }
}
