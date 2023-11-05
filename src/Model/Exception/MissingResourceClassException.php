<?php
declare(strict_types=1);

namespace Muffin\Webservice\Model\Exception;

use Cake\Core\Exception\CakeException;

class MissingResourceClassException extends CakeException
{
    protected string $_messageTemplate = 'Resource class %s could not be found.';
}
