<?php
declare(strict_types=1);

namespace Muffin\Webservice\Webservice\Exception;

use Cake\Core\Exception\CakeException;

class MissingDriverException extends CakeException
{
    /**
     * Template string that has attributes sprintf()'ed into it.
     *
     * @var string
     */
    protected string $_messageTemplate = 'Webservice driver %s could not be found.';
}
