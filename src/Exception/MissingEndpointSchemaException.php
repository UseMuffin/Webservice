<?php

namespace Muffin\Webservice\Exception;

use Cake\Core\Exception\Exception;

class MissingEndpointSchemaException extends Exception
{

    /**
     * {@inheritDoc}
     */
    protected $_messageTemplate = 'Missing schema %s or webservice %s describe implementation';
}
