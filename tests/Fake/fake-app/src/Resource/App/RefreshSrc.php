<?php
/**
 * This file is part of the BEAR.QueryRepository package.
 *
 * @license http://opensource.org/licenses/MIT MIT
 */
namespace FakeVendor\HelloWorld\Resource\App;

use BEAR\RepositoryModule\Annotation\Refresh;
use BEAR\Resource\ResourceObject;

class RefreshSrc extends ResourceObject
{
    #[Refresh(uri: "app://self/refresh-dest{?id}")]
    public function onPut(mixed $id)
    {
        unset($id);

        return $this;
    }
}
