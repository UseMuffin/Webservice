<?php

namespace Muffin\Webservice\View\Form;

use Cake\Network\Request;
use Cake\View\Form\ContextInterface;
use Muffin\Webservice\Model\EndpointRegistry;

class ResourceContext implements ContextInterface
{

    /**
     * @var \Muffin\Webservice\Model\Endpoint
     */
    protected $_endpoint;

    /**
     * @var \Muffin\Webservice\Model\Resource
     */
    protected $_resource;

    public function __construct(Request $request, array $data)
    {
        /** @var \Muffin\Webservice\Model\Resource $resource */
        $this->_resource = $data['entity'];

        $this->_endpoint = EndpointRegistry::get($this->_resource->source());
    }


    /**
     * Get the fields used in the context as a primary key.
     *
     * @return array
     */
    public function primaryKey()
    {
        // TODO: Implement primaryKey() method.
    }

    /**
     * Returns true if the passed field name is part of the primary key for this context
     *
     * @param string $field A dot separated path to the field a value
     *   is needed for.
     * @return bool
     */
    public function isPrimaryKey($field)
    {
        // TODO: Implement isPrimaryKey() method.
    }

    /**
     * Returns whether or not this form is for a create operation.
     *
     * @return bool
     */
    public function isCreate()
    {
        return $this->_resource->isNew();
    }

    /**
     * Get the current value for a given field.
     *
     * @param string $field A dot separated path to the field a value
     *   is needed for.
     * @return mixed
     */
    public function val($field)
    {
        return $this->_resource->get($field);
    }

    /**
     * Check if a given field is 'required'.
     *
     * In this context class, this is simply defined by the 'required' array.
     *
     * @param string $field A dot separated path to check required-ness for.
     * @return bool
     */
    public function isRequired($field)
    {
        return true;
    }

    /**
     * Get the fieldnames of the top level object in this context.
     *
     * @return array A list of the field names in the context.
     */
    public function fieldNames()
    {
        return $this->_endpoint->schema()->columns();
    }

    /**
     * Get the field type for a given field name.
     *
     * @param string $field A dot separated path to get a schema type for.
     * @return null|string An data type or null.
     * @see \Cake\Database\Type
     */
    public function type($field)
    {
        // TODO: Implement type() method.
    }

    /**
     * Get an associative array of other attributes for a field name.
     *
     * @param string $field A dot separated path to get additional data on.
     * @return array An array of data describing the additional attributes on a field.
     */
    public function attributes($field)
    {
        // TODO: Implement attributes() method.
    }

    /**
     * Check whether or not a field has an error attached to it
     *
     * @param string $field A dot separated path to check errors on.
     * @return bool Returns true if the errors for the field are not empty.
     */
    public function hasError($field)
    {
        // TODO: Implement hasError() method.
    }

    /**
     * Get the errors for a given field
     *
     * @param string $field A dot separated path to check errors on.
     * @return array An array of errors, an empty array will be returned when the
     *    context has no errors.
     */
    public function error($field)
    {
        // TODO: Implement error() method.
    }
}
