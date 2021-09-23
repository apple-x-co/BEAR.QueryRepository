<?php

declare(strict_types=1);

namespace BEAR\QueryRepository;

use BEAR\Resource\Module\ResourceModule;
use BEAR\Resource\ResourceInterface;
use BEAR\Resource\ResourceObject;
use Madapaja\TwigModule\TwigModule;
use PHPUnit\Framework\TestCase;
use Ray\Di\Injector;

use function dirname;

class ResourceStaticTest extends TestCase
{
    /** @var ResourceInterface */
    private $resource;

    protected function setUp(): void
    {
        $namespace = 'FakeVendor\HelloWorld';
        $module = new FakeEtagPoolModule(new QueryRepositoryModule(new ResourceModule($namespace)));
        $path = dirname(__DIR__) . '/tests/Fake/fake-app/var/templates';
        $module->override(new TwigModule([$path]));
        $injector = new Injector($module, $_ENV['TMP_DIR']);
        $this->resource = $injector->getInstance(ResourceInterface::class);
        parent::setUp();
    }

    public function testRefresh(): void
    {
        $static = new ResourceStatic('cmt=[le:page://self/html/comment]');
        $blog = $this->resource->get('page://self/html/blog-posting');
        $ro = $static->refresh($this->resource, $blog);
        $this->assertInstanceOf(ResourceObject::class, $ro);
        $this->assertSame('cmt=comment01', $ro->view);
    }
}
