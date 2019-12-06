<?php
declare(strict_types=1);

namespace Muffin\Webservice\Exception;

use Cake\Core\Exception\Exception;

class MissingWebserviceClassException extends Exception
{
    protected $_messageTemplate = 'Webservice class %s (and fallback %s) could not be found.';
}
