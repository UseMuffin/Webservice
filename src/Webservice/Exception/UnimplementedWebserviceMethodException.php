<?php
declare(strict_types=1);

namespace Muffin\Webservice\Webservice\Exception;

use Cake\Core\Exception\CakeException;

class UnimplementedWebserviceMethodException extends CakeException
{
    /**
     * Template string that has attributes sprintf()'ed into it.
     *
     * @var string
     */
    protected string $_messageTemplate = 'Webservice %s does not implement %s';
}
