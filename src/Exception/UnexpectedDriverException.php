<?php
declare(strict_types=1);

namespace Muffin\Webservice\Exception;

use Cake\Core\Exception\Exception;

class UnexpectedDriverException extends Exception
{
    /**
     * Template string that has attributes sprintf()'ed into it.
     *
     * @var string
     */
    protected $_messageTemplate = 'Driver (`%s`) should extend `Muffin\Webservice\Webservice\Driver\AbstractDriver`';
}
