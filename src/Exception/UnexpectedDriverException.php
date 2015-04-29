<?php
namespace Muffin\Webservice\Exception;

use Cake\Core\Exception\Exception;

class UnexpectedDriverException extends Exception
{

    /**
     * {@inheritDoc}
     */
    protected $_messageTemplate = 'Driver (`%s`) should extend `Muffin\Webservice\AbstractDriver`';
}
