<?php
namespace Muffin\Webservice\Exception;

use Cake\Core\Exception\Exception;

class UnimplementedWebserviceMethodException extends Exception
{

    /**
     * {@inheritDoc}
     */
    protected $_messageTemplate = 'Driver (`%s`) does not implement `%s`';
}
