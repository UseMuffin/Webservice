<?php
declare(strict_types=1);

namespace Muffin\Webservice\Exception;

use Cake\Core\Exception\Exception;

class MissingResourceClassException extends Exception
{
    protected $_messageTemplate = 'Resource class %s could not be found.';
}
