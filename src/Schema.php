<?php

namespace Muffin\Webservice;

use Cake\Database\Schema\Table;

class Schema extends Table
{

    /**
     * Constructor.
     *
     * @param string $endpoint The endpoint name.
     * @param array $fields The list of fields for the schema.
     */
    public function __construct($endpoint, array $fields = [])
    {
        parent::__construct($endpoint, $fields);

        $this->initialize();
    }

    /**
     * Initialize a schema instance. Called after the constructor.
     *
     * You can use this method to define fields.
     *
     * ```
     *  public function initialize()
     *  {
     *      $this->addField('title', [
     *          'type' => 'string'
     *      ]);
     *  }
     * ```
     *
     * @return void
     */
    public function initialize()
    {
    }
}
