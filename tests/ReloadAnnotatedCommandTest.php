<?php

declare(strict_types=1);

namespace BEAR\QueryRepository;

use BEAR\Resource\ResourceInterface;
use PHPUnit\Framework\TestCase;
use Ray\Di\Injector;

class ReloadAnnotatedCommandTest extends TestCase
{
    private ResourceInterface $resource;

    protected function setUp(): void
    {
        $this->resource = (new Injector(ModuleFactory::getInstance('FakeVendor\HelloWorld'), $_ENV['TMP_DIR']))->getInstance(ResourceInterface::class);

        parent::setUp();
    }

    public function testInvoke(): void
    {
        $user = $this->resource->patch('app://self/user', ['id' => 1, 'name' => 'koriym']);
        // put
        $expect = 'Last-Modified';
        $this->assertArrayHasKey($expect, $user->headers);
        $time = $user['time'];
        // get
        $user = $this->resource->get('app://self/user', ['id' => 1]);
        $this->assertArrayHasKey($expect, $user->headers);
        $expect = $time;
        $this->assertSame($expect, $user['time']);
    }
}
