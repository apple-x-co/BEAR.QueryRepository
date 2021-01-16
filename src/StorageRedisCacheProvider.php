<?php

declare(strict_types=1);

namespace BEAR\QueryRepository;

use BEAR\QueryRepository\Exception\RedisConnectionException;
use BEAR\RepositoryModule\Annotation\Redis;
use Doctrine\Common\Cache\RedisCache;
use Ray\Di\ProviderInterface;
use RedisException;

class StorageRedisCacheProvider implements ProviderInterface
{
    /** @var string */
    private $host;

    /** @var int */
    private $port;

    /**
     * @param array{0: string, 1: string} $server
     *
     * @Redis("server")
     */
    public function __construct(array $server)
    {
        $this->host = $server[0];
        $this->port = (int) $server[1];
    }

    /**
     * {@inheritdoc}
     */
    public function get()
    {
        $redis = new \Redis();
        try {
            $redis->connect($this->host, $this->port);
        } catch (RedisException $e) {
            throw new RedisConnectionException(sprintf('%s/%s', $this->host, $this->port), 0, $e);
        }
        $redisCache = new RedisCache();
        $redisCache->setRedis($redis);

        return $redisCache;
    }
}
