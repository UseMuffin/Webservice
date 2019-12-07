<?php
declare(strict_types=1);

namespace Muffin\Webservice\Test\test_app\Webservice;

use Muffin\Webservice\Model\Resource;
use Muffin\Webservice\Query;
use Muffin\Webservice\ResultSet;
use Muffin\Webservice\Webservice\Webservice;

class EndpointTestWebservice extends Webservice
{
    protected $resources;

    public function initialize(): void
    {
        parent::initialize();

        $this->resources = [
            new Resource([
                'id' => 1,
                'title' => 'Hello World',
                'body' => 'Some text',
            ], [
                'markNew' => false,
                'markClean' => true,
            ]),
            new Resource([
                'id' => 2,
                'title' => 'New ORM',
                'body' => 'Some more text',
            ], [
                'markNew' => false,
                'markClean' => true,
            ]),
            new Resource([
                'id' => 3,
                'title' => 'Webservices',
                'body' => 'Even more text',
            ], [
                'markNew' => false,
                'markClean' => true,
            ]),
        ];
    }

    protected function _executeCreateQuery(Query $query, array $options = [])
    {
        $fields = $query->set();

        if (!is_numeric($fields['id'])) {
            return false;
        }

        $this->resources[] = new Resource($fields, [
            'markNew' => false,
            'markClean' => true,
        ]);

        return true;
    }

    protected function _executeReadQuery(Query $query, array $options = [])
    {
        if (!empty($query->where()['id'])) {
            $index = $this->conditionsToIndex($query->where());

            if (!isset($this->resources[$index])) {
                return new ResultSet([], 0);
            }

            return new ResultSet([
                $this->resources[$index],
            ], 1);
        }
        if (isset($query->where()[$query->getEndpoint()->aliasField('title')])) {
            $resources = [];

            foreach ($this->resources as $resource) {
                if ($resource->title !== $query->where()[$query->getEndpoint()->aliasField('title')]) {
                    continue;
                }

                $resources[] = $resource;
            }

            return new ResultSet($resources, count($resources));
        }

        return new ResultSet($this->resources, count($this->resources));
    }

    protected function _executeUpdateQuery(Query $query, array $options = [])
    {
        $this->resources[$this->conditionsToIndex($query->where())]->set($query->set());

        $this->resources[$this->conditionsToIndex($query->where())]->clean();

        return 1;
    }

    protected function _executeDeleteQuery(Query $query, array $options = [])
    {
        $conditions = $query->where();

        if (is_int($conditions['id'])) {
            $exists = isset($this->resources[$this->conditionsToIndex($conditions)]);

            unset($this->resources[$this->conditionsToIndex($conditions)]);

            return $exists ? 1 : 0;
        } elseif (is_array($conditions['id'])) {
            $deleted = 0;

            foreach ($conditions['id'] as $id) {
                if (!isset($this->resources[$id - 1])) {
                    continue;
                }

                $deleted++;
                unset($this->resources[$id - 1]);
            }

            return $deleted;
        }

        return 0;
    }

    public function conditionsToIndex(array $conditions)
    {
        return $conditions['id'] - 1;
    }
}
