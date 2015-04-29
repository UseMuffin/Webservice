<?php
namespace Muffin\Webservice\Exception;

use Cake\Core\Exception\Exception;

class MissingDriverException extends Exception
{

    /**
     * {@inheritDoc}
     */
    protected $_messageTemplate = 'Webservice driver %s could not be found.';
}
