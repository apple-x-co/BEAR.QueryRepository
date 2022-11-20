<?php

declare(strict_types=1);

namespace BEAR\QueryRepository;

use BEAR\Resource\Code;
use BEAR\Resource\ResourceObject;
use Ray\Aop\MethodInterceptor;
use Ray\Aop\MethodInvocation;
use Throwable;

use function assert;
use function sprintf;
use function trigger_error;

use const E_USER_WARNING;

abstract class AbstractDonutCacheInterceptor implements MethodInterceptor
{
    protected const IS_ENTIRE_CONTENT_CACHEABLE = false;

    private DonutRepositoryInterface $donutRepository;

    public function __construct(DonutRepositoryInterface $donutRepository)
    {
        $this->donutRepository = $donutRepository;
    }

    /**
     * {@inheritdoc}
     */
    final public function invoke(MethodInvocation $invocation)
    {
        $ro = $invocation->getThis();
        assert($ro instanceof ResourceObject);
        try {
            $maybeRo = $this->donutRepository->get($ro);
            if ($maybeRo instanceof ResourceObject) {
                return $maybeRo;
            }
        } catch (Throwable $e) { // @codeCoverageIgnoreStart
            // when cache server is down
            $this->triggerWarning($e);

            return $invocation->proceed(); // @codeCoverageIgnoreStartEnd
        }

        /** @var ResourceObject $ro */
        $ro = $invocation->proceed();
        // donut created in ResourceObject
        if (isset($ro->headers[Header::ETAG]) || $ro->code >= Code::BAD_REQUEST) {
            return $ro;
        }

        return self::IS_ENTIRE_CONTENT_CACHEABLE ?
            $this->donutRepository->putStatic($ro, null, null) :
            $this->donutRepository->putDonut($ro, null);
    }

    /**
     * @codeCoverageIgnore
     */
    private function triggerWarning(Throwable $e): void
    {
        trigger_error(sprintf('%s: %s in %s:%s', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()), E_USER_WARNING);
    }
}
