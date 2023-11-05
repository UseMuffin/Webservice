<?php
declare(strict_types=1);

namespace Muffin\Webservice\Model;

interface ResourceBasedEntityInterface
{
    /**
     * Apply the properties from a resource
     *
     * @param \Muffin\Webservice\Model\Resource $resource The resource to apply the properties from
     * @return void
     */
    public function applyResource(Resource $resource): void;

    /**
     * Creates a instance if the current entity with the values of a resouce
     *
     * @param \Muffin\Webservice\Model\Resource $resource The resource to apply the properties from
     * @param array $options The options to pass to the constructor
     * @return self
     */
    public static function createFromResource(Resource $resource, array $options = []): self;
}
