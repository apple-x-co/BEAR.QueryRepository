<?php

declare(strict_types=1);

namespace BEAR\QueryRepository;

use BEAR\QueryRepository\QueryRepository as Repository;
use BEAR\RepositoryModule\Annotation\EtagPool;
use BEAR\Resource\Module\ResourceModule;
use BEAR\Resource\ResourceInterface;
use BEAR\Resource\ResourceObject;
use BEAR\Resource\Uri;
use BEAR\Sunday\Extension\Transfer\HttpCacheInterface;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\Reader;
use FakeVendor\HelloWorld\Resource\App\NullView;
use FakeVendor\HelloWorld\Resource\App\User\Profile;
use FakeVendor\HelloWorld\Resource\Page\None;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Ray\Di\AbstractModule;
use Ray\Di\Injector;
use Ray\PsrCacheModule\Annotation\Shared;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

use function assert;
use function is_array;
use function serialize;
use function unserialize;

class QueryRepositoryTest extends TestCase
{
    private ResourceInterface $resource;
    private QueryRepositoryInterface $repository;
    private HttpCacheInterface $httpCache;
    private RepositoryLoggerInterface $logger;

    protected function setUp(): void
    {
        $namespace = 'FakeVendor\HelloWorld';
        $injector = new Injector(new FakeEtagPoolModule(ModuleFactory::getInstance($namespace)), $_ENV['TMP_DIR']);
        $this->repository = $injector->getInstance(QueryRepositoryInterface::class);
        $this->resource = $injector->getInstance(ResourceInterface::class);
        $this->httpCache = $injector->getInstance(HttpCacheInterface::class);
        $this->logger = $injector->getInstance(RepositoryLoggerInterface::class);
        parent::setUp();
    }

    protected function tearDown(): void
    {
        $log = ((string) $this->logger);
        // error_log((string) $log);  // uncomment to see the debug log
        unset($log);
    }

    public function testPurgeSameResourceObjectByPatch(): void
    {
        $user = $this->resource->get('app://self/user', ['id' => 1]);
        assert($user instanceof ResourceObject);
        $etag = $user->headers[Header::ETAG];
        // reload (purge repository entry and re-generate by onGet)
        $this->resource->patch('app://self/user', ['id' => 1, 'name' => 'kuma']);
        // load from repository, not invoke onGet method
        $user = $this->resource->get('app://self/user', ['id' => 1]);
        $newEtag = $user->headers[Header::ETAG];
        $this->assertFalse($etag === $newEtag);
    }

    public function testPurgeSameResourceObjectByDelete(): void
    {
        $user = $this->resource->get('app://self/user', ['id' => 1]);
        assert($user instanceof ResourceObject);
        $etag = $user->headers[Header::ETAG];
        $server = [
            'REQUEST_METHOD' => 'GET',
            'HTTP_IF_NONE_MATCH' => $etag,
        ];
        $isNotModified = $this->httpCache->isNotModified($server);
        $this->assertTrue($isNotModified);
        $this->resource->delete('app://self/user', ['id' => 1]);
        $user = $this->resource->get('app://self/user', ['id' => 1]);
        $newEtag = $user->headers[Header::ETAG];
        $this->assertFalse($etag === $newEtag);
        $isNotModified = $this->httpCache->isNotModified($server);
        $this->assertFalse($isNotModified);
    }

    public function testPurgeByAnnotation(): void
    {
        $this->resource->put('app://self/user', ['id' => 1, 'age' => 10, 'name' => 'Sunday']);
        $this->assertTrue(Profile::$requested);
    }

    /** @covers \BEAR\QueryRepository\QueryRepository::getExpiryTime() */
    public function testNoAnnotationLifeTime(): void
    {
        $ro = new None(); // no annotation
        $ro->uri = new Uri('page://self/none');
        $result = $this->repository->put($ro);
        $this->assertTrue($result);
    }

    public function testPutResquestEmbeddedResoureView(): void
    {
        $uri = 'page://self/emb-view';
        $ro = $this->resource->get($uri);
        $this->repository->put($ro);
        $state = $this->repository->get(new Uri($uri));
        assert($state instanceof ResourceState);
        assert(is_array($state->body));
        $this->assertSame(1, $state->body['num']);
        $this->assertSame('{
    "time": {
        "none": "none"
    },
    "num": 1
}
', $state->view);
    }

    public function testPutResquestEmbeddedResoureValue(): void
    {
        $uri = 'page://self/emb-val';
        $ro = $this->resource->get($uri);
        $this->repository->put($ro);
        $state = $this->repository->get(new Uri($uri));
        assert($state instanceof ResourceState);
        assert(is_array($state->body));
        $this->assertSame(1, $state->body['num']);
        $this->assertNull($state->view);
    }

    /**
     * If the cache component causes an error (such as insufficient disk space), it will read the original data to avoid the error.
     * In that case, a warning will be output to syslog.
     *
     * キャッシュコンポーネントが(ディスクの容量不足など）エラーを発生させた場合に、オリジナルのデータを読んでエラーを回避します。
     * その際にはsyslogにWarningを出力します。
     */
    public function testErrorInCacheRead(): void
    {
        $namespace = 'FakeVendor\HelloWorld';
        $module = ModuleFactory::getInstance($namespace);

        $module->override(new class extends AbstractModule {
            protected function configure(): void
            {
                $this->bind(CacheItemPoolInterface::class)->annotatedWith(Shared::class)->to(FakeErrorCache::class);
            }
        });
        $resource = (new Injector($module, $_ENV['TMP_DIR']))->getInstance(ResourceInterface::class);
        assert($resource instanceof ResourceInterface);
        $this->expectWarning();
        $resource->get('app://self/user', ['id' => 1]);
        $this->assertSame(2, $GLOBALS['BEAR\QueryRepository\syslog'][0]);
        $this->assertContains('Exception: DoctrineNamespaceCacheKey[]', $GLOBALS['BEAR\QueryRepository\syslog'][1]);
    }

    public function testSameResponseButDifferentParameter(): void
    {
        $ro1 = $this->resource->get('app://self/sometimes-same-response', ['id' => 1]);
        $server1 = [
            'REQUEST_METHOD' => 'GET',
            'HTTP_IF_NONE_MATCH' => $ro1->headers[Header::ETAG],
        ];
        $this->assertTrue($this->httpCache->isNotModified($server1), 'id:1 is not modified');

        $ro2 = $this->resource->get('app://self/sometimes-same-response', ['id' => 2]);
        $server2 = [
            'REQUEST_METHOD' => 'GET',
            'HTTP_IF_NONE_MATCH' => $ro2->headers[Header::ETAG],
        ];
        $this->assertTrue($this->httpCache->isNotModified($server2), 'id:2 is not modified');
        $this->resource->delete('app://self/sometimes-same-response', ['id' => 1]);
        $this->assertFalse($this->httpCache->isNotModified($server1), 'id:1 is modified');
        $this->assertTrue($this->httpCache->isNotModified($server2), 'id:2 is not modified');
    }

    public function testRenderView(): void
    {
        $ro = new NullView();
        $ro->uri = new Uri('app://self/null-view');
        $ro->body = ['time' => '0'];
        $this->repository->put($ro);
        $this->assertIsString($ro->view);
    }

    public function testSerializable(): void
    {
        $namespace = 'FakeVendor\HelloWorld';
        $module = new QueryRepositoryModule(new ResourceModule($namespace));
        $module->override(new class extends AbstractModule{
            protected function configure(): void
            {
                $this->bind(CacheItemPoolInterface::class)->annotatedWith(Shared::class)->to(FilesystemAdapter::class);
                $this->bind(CacheItemPoolInterface::class)->annotatedWith(EtagPool::class)->to(FilesystemAdapter::class);
                $this->bind(Reader::class)->to(AnnotationReader::class);
            }
        });
        $repository = (new Injector($module))->getInstance(QueryRepositoryInterface::class);
        $unserilizedRepository = unserialize(serialize(unserialize(serialize($repository))));
        $this->assertInstanceOf(Repository::class, $unserilizedRepository);
    }
}
