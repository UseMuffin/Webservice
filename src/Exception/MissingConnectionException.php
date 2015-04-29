<?php
namespace Muffin\Webservice\Exception;

use Cake\Core\Exception\Exception;

class MissingConnectionException extends Exception
{

    /**
     * {@inheritDoc}
     */
    protected $_messageTemplate = 'No `%` connection configured.';
}
