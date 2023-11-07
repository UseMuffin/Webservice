<?php
declare(strict_types=1);

namespace Muffin\Webservice\Webservice\Exception;

use Cake\Core\Exception\CakeException;

class UnexpectedDriverException extends CakeException
{
    /**
     * Template string that has attributes sprintf()'ed into it.
     *
     * @var string
     */
    protected string $_messageTemplate
        = 'Driver (`%s`) should extend `Muffin\Webservice\Webservice\Driver\AbstractDriver`';
}
