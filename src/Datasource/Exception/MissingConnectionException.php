<?php
declare(strict_types=1);

namespace Muffin\Webservice\Datasource\Exception;

use Cake\Core\Exception\CakeException;

class MissingConnectionException extends CakeException
{
    /**
     * Template string that has attributes sprintf()'ed into it.
     *
     * @var string
     */
    protected string $_messageTemplate = 'No `%s` connection configured.';
}
