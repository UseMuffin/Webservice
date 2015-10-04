<?php

namespace Muffin\Webservice\Model;

interface ResourceBasedEntityInterface
{

    public function applyResource(Resource $resource);

    public static function createFromResource(Resource $resource, array $options = []);
}
