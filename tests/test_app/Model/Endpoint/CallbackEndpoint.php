<?php

namespace Muffin\Webservice\Test\test_app\Model\Endpoint;

use Cake\Datasource\EntityInterface;
use Cake\Event\Event;
use Muffin\Webservice\Connection;
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
        if ($entity->get('title')) {
            $entity->set('title', 'Loads of sun');
        }

        return $entity;
    }

    /**
     * Test beforeDelete Callback
     * @param Event $event
     * @param EntityInterface $entity
     * @return EntityInterface
     */
    public function beforeDelete(Event $event, EntityInterface $entity)
    {
        $entity = $this->get(5);
        if ($entity->get('title')) {
            $entity->set('title', 'I love fun');
        }

        return $entity;
    }

    /**
     * Test afterDelete Callback
     * @param Event $event
     * @param EntityInterface $entity
     * @return EntityInterface
     */
    public function afterDelete(Event $event, EntityInterface $entity)
    {
        $entity = $this->get(6);
        if ($entity->get('title')) {
            $entity->set('title', 'I need fun');
        }

        return $entity;
    }
}
