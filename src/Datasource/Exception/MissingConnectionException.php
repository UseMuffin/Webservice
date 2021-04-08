<?php
declare(strict_types=1);

namespace Muffin\Webservice\Datasource\Exception;

use Cake\Core\Exception\Exception;

class MissingConnectionException extends Exception
{
    /**
     * Template string that has attributes sprintf()'ed into it.
     *
     * @var string
     */
    protected $_messageTemplate = 'No `%s` connection configured.';
}
