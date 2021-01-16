<?php

declare(strict_types=1);

namespace BEAR\QueryRepository;

use BEAR\Resource\AbstractUri;
use BEAR\Resource\ResourceObject;

interface QueryRepositoryInterface
{
    /**
     * @return bool Is successfully stored
     */
    public function put(ResourceObject $ro);

    /**
     * @return array<int, array<string>, mixed, string>|false
     */
    public function get(AbstractUri $uri);

    /**
     * @return bool Is successfully deleted
     */
    public function purge(AbstractUri $uri);
}
