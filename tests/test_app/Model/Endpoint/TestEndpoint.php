<?php

namespace Muffin\Webservice\Test\test_app\Model\Endpoint;

use Cake\Validation\Validator;
use Muffin\Webservice\Model\Endpoint;

class TestEndpoint extends Endpoint
{

    /**
     * Returns the default validator object. Subclasses can override this function
     * to add a default validation set to the validator object.
     *
     * @param \Cake\Validation\Validator $validator The validator that can be modified to
     * add some rules to it.
     *
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator->requirePresence('title')
            ->notEmptyString('title')
            ->requirePresence('body')
            ->notEmptyString('body')
            ->minLength('body', 5, 'Must be 5 characters or longer');

        return $validator;
    }

    /**
     * Example finder
     *
     * @return true
     */
    public function findExamples()
    {
        return true;
    }
}
