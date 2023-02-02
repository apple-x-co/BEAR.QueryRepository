<?php

declare(strict_types=1);

namespace BEAR\QueryRepository;

use BEAR\Resource\ResourceInterface;
use BEAR\Resource\ResourceObject;
use BEAR\Resource\Uri;
use Madapaja\TwigModule\TwigModule;
use PHPUnit\Framework\TestCase;
use Ray\Di\Injector;

use function assert;
use function dirname;

class DonutQueryInterceptorPurgeTest extends TestCase
{
    private ResourceInterface $resource;
    private QueryRepository $repository;
    private RepositoryLoggerInterface $logger;

    protected function setUp(): void
    {
        static $injector;

        $namespace = 'FakeVendor\HelloWorld';
        $module = new FakeEtagPoolModule(ModuleFactory::getInstance($namespace));
        $module->override(new TwigModule([dirname(__DIR__) . '/tests/Fake/fake-app/var/templates']));
        if (! $injector) {
            $injector = new Injector($module, $_ENV['TMP_DIR']);
        }

        $this->resource = $injector->getInstance(ResourceInterface::class);
        $this->repository = $injector->getInstance(QueryRepository::class);
        $this->logger = $injector->getInstance(RepositoryLoggerInterface::class);

        parent::setUp();
    }

    protected function tearDown(): void
    {
        $log = ((string) $this->logger);
        // error_log((string) $log);  // uncomment to see the debug log
        unset($log);
    }

    public function testStatePurge(): void
    {
        $ro1 = $this->resource->get('page://self/html/blog-posting');
        $this->assertFalse($this->isCreatedByState($ro1));
        $this->assertTrue($this->isStateCached());

        assert($this->repository->purge(new Uri('page://self/html/comment')));
        $this->assertFalse($this->isStateCached());

        $ro2 = $this->resource->get('page://self/html/blog-posting');
        $this->assertTrue($this->isCreatedByState($ro2));
        $this->assertTrue($this->isStateCached(), 'Resource state should be cached');
    }

    private function isStateCached(): bool
    {
        return $this->repository->get(new Uri('page://self/html/blog-posting')) instanceof ResourceState;
    }

    private function isCreatedByState(ResourceObject $ro): bool
    {
        return $ro->headers[Header::ETAG][-1] === 'r';
    }
}
