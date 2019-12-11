<?php
declare(strict_types=1);

namespace Muffin\Webservice\Webservice\Exception;

use Cake\Core\Exception\Exception;

class MissingDriverException extends Exception
{
    /**
     * Template string that has attributes sprintf()'ed into it.
     *
     * @var string
     */
    protected $_messageTemplate = 'Webservice driver %s could not be found.';
}
