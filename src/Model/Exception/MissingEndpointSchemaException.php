<?php
declare(strict_types=1);

namespace Muffin\Webservice\Model\Exception;

use Cake\Core\Exception\CakeException;

class MissingEndpointSchemaException extends CakeException
{
    /**
     * Template string that has attributes sprintf()'ed into it.
     *
     * @var string
     */
    protected string $_messageTemplate = 'Missing schema %s or webservice %s describe implementation';
}
