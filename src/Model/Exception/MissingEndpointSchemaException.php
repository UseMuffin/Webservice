<?php
declare(strict_types=1);

namespace Muffin\Webservice\Model\Exception;

use Cake\Core\Exception\Exception;

class MissingEndpointSchemaException extends Exception
{
    /**
     * Template string that has attributes sprintf()'ed into it.
     *
     * @var string
     */
    protected $_messageTemplate = 'Missing schema %s or webservice %s describe implementation';
}
