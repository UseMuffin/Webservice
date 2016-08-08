<?php

namespace Muffin\Webservice\Panel;

use DebugKit\DebugPanel;
use Muffin\Webservice\QueryLog;

class WebserviceQueriesPanel extends DebugPanel
{
    public $plugin = 'Muffin/Webservice';

    /**
     * {@inheritDoc}
     */
    public function data()
    {
        return [
            'queries' => QueryLog::queries()
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function summary()
    {
        $took = round(collection(QueryLog::queries())->sumOf('took'), 0);

        return __d('muffin/webservice', '{0} / {1} ms', count(QueryLog::queries()), $took);
    }
}
