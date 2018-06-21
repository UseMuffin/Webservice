<?php

namespace Muffin\Webservice;

use Cake\Core\BasePlugin;

class Plugin extends BasePlugin
{
    /**
     * Disable routes hook.
     *
     * @var bool
     */
    protected $routesEnabled = false;

    /**
     * Disable middleware hook.
     *
     * @var bool
     */
    protected $middlewareEnabled = false;

    /**
     * Disable console hook.
     *
     * @var bool
     */
    protected $consoleEnabled = false;
}
