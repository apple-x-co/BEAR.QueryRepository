<?php

declare(strict_types=1);

namespace BEAR\QueryRepository;

use BEAR\QueryRepository\Cdn\AkamaiModule;
use BEAR\QueryRepository\Cdn\FastlyModule;
use BEAR\Resource\ResourceInterface;
use BEAR\Resource\ResourceObject;
use BEAR\Resource\Uri;
use Madapaja\TwigModule\TwigModule;
use PHPUnit\Framework\TestCase;
use Ray\Di\AbstractModule;
use Ray\Di\Injector;

use function assert;
use function dirname;

class CdnCacheControlHeaderTest extends TestCase
{
    public function testCdnCacheControl(): void
    {
        $module = $this->getModule();
        $injector =  new Injector($module, $_ENV['TMP_DIR']);
        /** @var ResourceInterface $resource */
        $resource = $injector->getInstance(ResourceInterface::class);
        $ro = $resource->get('page://self/html/blog-posting');
        assert($ro instanceof ResourceObject);
        $this->assertArrayHasKey(Header::CDN_CACHE_CONTROL, $ro->headers);
        $this->assertSame($ro->headers[Header::CDN_CACHE_CONTROL], 'max-age=10 stale-while-revalidate=10');
        $repository = $injector->getInstance(QueryRepositoryInterface::class);
        assert($repository->purge(new Uri('page://self/html/comment')));

        $donutRo = $resource->get('page://self/html/blog-posting');
        $this->assertSame('r', $donutRo->headers[Header::ETAG][-1]);
        $this->assertArrayHasKey(Header::CDN_CACHE_CONTROL, $donutRo->headers, 'Even if it is made from donut, it should have a CDN header.');
    }

    public function testFastlyModule(): void
    {
        $module = $this->getModule();
        $module->override(new FastlyModule('apiKey', 'serviceId'));
        $module->override(new FakeFastlyPurgeModule());
        $injector =  new Injector($module, $_ENV['TMP_DIR']);
        $resource = $injector->getInstance(ResourceInterface::class);
        $ro = $resource->get('page://self/html/blog-posting');
        assert($ro instanceof ResourceObject);
        $this->assertArrayHasKey('Surrogate-Control', $ro->headers);
        $this->assertSame($ro->headers['Surrogate-Control'], 'max-age=31536000');
        $this->assertArrayHasKey('Surrogate-Key', $ro->headers);
    }

    public function testAkamaiModule(): void
    {
        $module = $this->getModule();
        $module->override(new AkamaiModule());
        $injector =  new Injector($module, $_ENV['TMP_DIR']);
        $resource = $injector->getInstance(ResourceInterface::class);
        $ro = $resource->get('page://self/html/blog-posting');
        assert($ro instanceof ResourceObject);
        $this->assertArrayHasKey('Akamai-Cache-Control', $ro->headers);
        $this->assertArrayHasKey('Edge-Cache-Tag', $ro->headers);
        $this->assertSame($ro->headers['Akamai-Cache-Control'], 'max-age=31536000');
    }

    public function testNullCdnCacheControlModule(): void
    {
        $module = $this->getModule();
        $module->override(new NullCdnCacheControlModule());
        $injector =  new Injector($module, $_ENV['TMP_DIR']);
        $resource = $injector->getInstance(ResourceInterface::class);
        $ro = $resource->get('page://self/html/blog-posting');
        assert($ro instanceof ResourceObject);
        $this->assertArrayNotHasKey('Surrogate-Control', $ro->headers);
        $this->assertArrayNotHasKey('Header::CDN_CACHE_CONTROL_HEADER', $ro->headers);
    }

    private function getModule(): AbstractModule
    {
        $namespace = 'FakeVendor\HelloWorld';
        $module = new FakeEtagPoolModule(ModuleFactory::getInstance($namespace));
        $module->override(new TwigModule([dirname(__DIR__) . '/tests/Fake/fake-app/var/templates']));

        return $module;
    }
}
