<?php

namespace Muffin\Webservice\Test\test_app\Model\Endpoint;

use Cake\Datasource\EntityInterface;
use Cake\Event\Event;
use Muffin\Webservice\Model\Endpoint;

class CallbackEndpoint extends Endpoint
{

    /**
     * Test afterSave Callback
     * @param Event $event
     * @param EntityInterface $entity
     * @param \ArrayObject $options
     * @return bool|EntityInterface
     */
    public function afterSave(Event $event, EntityInterface $entity, \ArrayObject $options)
    {
        $event->stopPropagation();
        if ($entity->get('title')) {
            $entity->set('title', 'Loads of sun');
        }

        return $entity;
    }
}
