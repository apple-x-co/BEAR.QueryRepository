<?php

declare(strict_types=1);

namespace BEAR\QueryRepository;

use BEAR\RepositoryModule\Annotation\CacheableResponse;
use BEAR\RepositoryModule\Annotation\DonutCache;
use BEAR\RepositoryModule\Annotation\RefreshCache;
use Ray\Di\AbstractModule;
use Ray\Di\Scope;

final class DonutCacheModule extends AbstractModule
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->bind(HeaderSetter::class);
        $this->bind(CdnCacheControlHeaderSetterInterface::class)->to(CdnCacheControlHeaderSetter::class);
        $this->bind(DonutRepositoryInterface::class)->to(DonutRepository::class)->in(Scope::SINGLETON);
        $this->bind(RepositoryLoggerInterface::class)->to(RepositoryLogger::class)->in(Scope::SINGLETON);
        $this->bind(PurgerInterface::class)->to(NullPurger::class);
        $this->bind(UriTagInterface::class)->to(UriTag::class)->in(Scope::SINGLETON);
        $this->installAopClassModule();
        $this->installAopMethodModule();
    }

    private function installAopClassModule(): void
    {
        $this->bind(DonutRepository::class)->in(Scope::SINGLETON);
        $this->bindPriorityInterceptor(
            $this->matcher->annotatedWith(CacheableResponse::class),
            $this->matcher->startsWith('onGet'),
            [DonutCacheableResponseInterceptor::class]
        );
        $this->bindPriorityInterceptor(
            $this->matcher->annotatedWith(DonutCache::class),
            $this->matcher->startsWith('onGet'),
            [DonutCacheInterceptor::class]
        );

        $this->bindInterceptor(
            $this->matcher->annotatedWith(CacheableResponse::class),
            $this->matcher->logicalOr(
                $this->matcher->startsWith('onPut'),
                $this->matcher->logicalOr(
                    $this->matcher->startsWith('onPatch'),
                    $this->matcher->startsWith('onDelete')
                )
            ),
            [DonutCommandInterceptor::class]
        );
    }

    private function installAopMethodModule(): void
    {
        $this->bindInterceptor(
            $this->matcher->any(),
            $this->matcher->annotatedWith(CacheableResponse::class),
            [DonutCacheInterceptor::class]
        );
        $this->bindInterceptor(
            $this->matcher->any(),
            $this->matcher->annotatedWith(RefreshCache::class),
            [DonutCacheInterceptor::class]
        );
    }
}
