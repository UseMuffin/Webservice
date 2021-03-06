<?php
namespace Muffin\Webservice\Exception;

use Cake\Core\Exception\Exception;

class UnimplementedDriverMethodException extends Exception
{

    /**
     * Template string that has attributes sprintf()'ed into it.
     *
     * @var string
     */
    protected $_messageTemplate = 'Driver (`%s`) does not implement `%s`';
}
