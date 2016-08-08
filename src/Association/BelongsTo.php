<?php

namespace Muffin\Webservice\Association;

use Cake\Datasource\EntityInterface;
use Cake\Datasource\RepositoryInterface;
use Cake\Utility\Inflector;
use Muffin\Webservice\Association;

/**
 * Represents an 1 - N relationship where the source side of the relation is
 * related to only one record in the target endpoint.
 *
 * An example of a BelongsTo association would be Article belongs to Author.
 */
class BelongsTo extends Association
{
    use QueryableAssociationTrait;

    /**
     * The strategy name to be used to fetch associated records.
     *
     * @var string
     */
    protected $_strategy = self::STRATEGY_QUERY;

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
                $this->_foreignKey = $this->_modelKey($this->target()->alias());
            }
            return $this->_foreignKey;
        }
        return parent::foreignKey($key);
    }

    /**
     * Handle cascading deletes.
     *
     * BelongsTo associations are never cleared in a cascading delete scenario.
     *
     * @param \Cake\Datasource\EntityInterface $entity The entity that started the cascaded delete.
     * @param array $options The options for the original delete.
     * @return bool Success.
     */
    public function cascadeDelete(EntityInterface $entity, array $options = [])
    {
        return true;
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
     * Returns whether or not the passed repository is the owning side for this
     * association. This means that rows in the 'target' endpoint would miss important
     * or required information if the row in 'source' did not exist.
     *
     * @param \Cake\Datasource\RepositoryInterface $side The potential repository with ownership
     * @return bool
     */
    public function isOwningSide(RepositoryInterface $side)
    {
        return $side === $this->target();
    }

    /**
     * Get the relationship type.
     *
     * @return string Constant of either ONE_TO_ONE, MANY_TO_ONE, ONE_TO_MANY or MANY_TO_MANY.
     */
    public function type()
    {
        return self::MANY_TO_ONE;
    }

    /**
     * Takes an entity from the source table and looks if there is a field
     * matching the property name for this association. The found entity will be
     * saved on the target table for this association by passing supplied
     * `$options`
     *
     * @param \Cake\Datasource\EntityInterface $entity an entity from the source table
     * @param array|\ArrayObject $options options to be passed to the save method in
     * the target table
     * @return bool|\Cake\Datasource\EntityInterface false if $entity could not be saved, otherwise it returns
     * the saved entity
     * @see \Cake\ORM\Table::save()
     */
    public function saveAssociated(EntityInterface $entity, array $options = [])
    {
        $targetEntity = $entity->get($this->property());
        if (empty($targetEntity) || !($targetEntity instanceof EntityInterface)) {
            return $entity;
        }

        $table = $this->target();
        $targetEntity = $table->save($targetEntity, $options);
        if (!$targetEntity) {
            return false;
        }

        $properties = array_combine(
            (array)$this->foreignKey(),
            $targetEntity->extract((array)$this->bindingKey())
        );
        $entity->set($properties, ['guard' => false]);
        debug($entity);
        return $entity;
    }

    /**
     * {@inheritDoc}
     */
    protected function _linkField($options)
    {
        $links = [];
        $name = $this->alias();

        foreach ((array)$this->bindingKey() as $key) {
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
        $key = (array)$this->bindingKey();

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
