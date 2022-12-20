<?php

declare(strict_types=1);

namespace BEAR\QueryRepository;

use BEAR\Resource\ResourceInterface;
use BEAR\Sunday\Extension\Transfer\HttpCacheInterface as HttpCacheInterfaceAlias;
use Madapaja\TwigModule\TwigModule;
use Ray\Di\Injector;

use function assert;
use function dirname;
use function serialize;
use function unserialize;

class DonutCommandRedisCacheTest extends DonutCommandInterceptorTest
{
    protected function setUp(): void
    {
        $namespace = 'FakeVendor\HelloWorld';
        $module = new FakeEtagPoolModule(ModuleFactory::getInstance($namespace));
        $module->override(new TwigModule([dirname(__DIR__) . '/tests/Fake/fake-app/var/templates']));
        $module->override(new StorageRedisModule('127.0.0.1:6379'));
        $injector = new Injector($module, $_ENV['TMP_DIR']);
        $this->resource = $injector->getInstance(ResourceInterface::class);
        $this->logger = $injector->getInstance(RepositoryLoggerInterface::class);
        $httpCache = $injector->getInstance(HttpCacheInterfaceAlias::class);
        $unserializedHttpCache = unserialize(serialize($httpCache));
        assert($unserializedHttpCache instanceof HttpCacheInterfaceAlias);
        $this->httpCache = $unserializedHttpCache;

        parent::setUp();
    }
}
