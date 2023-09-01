<?php

declare(strict_types=1);

namespace BEAR\QueryRepository;

use BEAR\Resource\ResourceInterface;
use BEAR\Resource\ResourceObject;
use PHPUnit\Framework\TestCase;
use Ray\Di\Injector;

class CacheTypeTest extends TestCase
{
    private ResourceInterface $resource;

    protected function setUp(): void
    {
        $this->resource = (new Injector(ModuleFactory::getInstance('FakeVendor\HelloWorld'), $_ENV['TMP_DIR']))->getInstance(ResourceInterface::class);

        parent::setUp();
    }

    public function requestDobule(string $uri): ResourceObject
    {
        $ro = $this->resource->get($uri);
        // put
        $expect = 'Last-Modified';
        $this->assertArrayHasKey($expect, $ro->headers);
        $time = $ro['time'];
        // get
        $ro = $this->resource->get($uri);
        $this->assertArrayHasKey($expect, $ro->headers);
        $expect = $time;
        $this->assertSame($expect, $ro['time']);

        return $ro;
    }

    public function testValue(): void
    {
        $uri = 'app://self/value';
        // put
        $ro = $this->resource->get($uri);
        (string) $ro; /* @phpstan-ignore-line */
        $time = $ro['time'];
        $this->assertSame('1' . $time, $ro->view);
        $ro = $this->resource->get($uri);
        (string) $ro; /* @phpstan-ignore-line */
        $this->assertSame('2' . $time, $ro->view);
    }

    public function testView(): void
    {
        $uri = 'app://self/view';
        // put
        $ro = $this->resource->get($uri);
        $time = $ro['time'];
        $this->assertSame('1' . $time, $ro->view);
        $ro = $this->resource->get($uri);
        $this->assertTrue((bool) $ro->view);
        $this->assertSame('1' . $time, $ro->view);
    }
}
