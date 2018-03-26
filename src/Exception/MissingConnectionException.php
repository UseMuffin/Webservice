<?php
namespace Muffin\Webservice\Exception;

use Cake\Core\Exception\Exception;

class MissingConnectionException extends Exception
{

    /**
     * Template string that has attributes sprintf()'ed into it.
     *
     * @var string
     */
    protected $_messageTemplate = 'No `%` connection configured.';
}
