<?php
/**
 * This file is part of the BEAR.QueryRepository package.
 *
 * @license http://opensource.org/licenses/MIT MIT
 */
namespace FakeVendor\HelloWorld\Resource\App;

use BEAR\RepositoryModule\Annotation\Cacheable;
use BEAR\RepositoryModule\Annotation\Purge;
use BEAR\RepositoryModule\Annotation\Refresh;
use BEAR\Resource\Code;
use BEAR\Resource\ResourceObject;
use Ray\Di\Di\Named;

/**
 * @Cacheable
 */
#[Cacheable]
class User extends ResourceObject
{
    protected $data = [];

    public function __construct()
    {
        $this->data[1]['name'] = 'bear';
        $this->data[1]['age'] = '3';
    }

    public function onGet($id)
    {
        $this['name'] = isset($this->data[$id]) ? $this->data[$id]['name'] : '';
        $this['time'] = \microtime(true);

        return $this;
    }

    public function onPatch($id, $name)
    {
        if (empty($name)) {
            $this->code = Code::BAD_REQUEST;

            return $this;
        }

        $this->data[$id]['name'] = $name;

        return $this;
    }

    /**
     * @Purge(uri="app://self/user/friend?user_id={id}")
     * @Refresh(uri="app://self/user/profile?user_id={id}")
     *
     * @ Named // to ignore
     *
     * @param mixed $id
     * @param mixed $name
     * @param mixed $age
     */
    #[Purge(uri: "app://self/user/friend?user_id={id}")]
    #[Refresh(uri: "app://self/user/profile?user_id={id}")]
    public function onPut($id, $name, $age)
    {
        $this->data[$id]['name'] = $name;
        $this->data[$id]['age'] = $age;
        $this['id'] = $id;

        return $this;
    }

    public function onDelete($id)
    {
        unset($this->data[$id]);

        return $this;
    }
}
