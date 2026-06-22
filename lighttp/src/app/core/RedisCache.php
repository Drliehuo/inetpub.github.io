<?php
declare(strict_types=1);

namespace App\core;

class RedisCache
{
    private \Redis $redis;
    private string $prefix;
    private int $defaultTtl;

    public function __construct(\Redis $redis, string $prefix = 'cms:', int $defaultTtl = 3600)
    {
        $this->redis = $redis;
        $this->prefix = $prefix;
        $this->defaultTtl = $defaultTtl;
    }

    public function get(string $key)
    {
        $data = $this->redis->get($this->prefix . $key);
        if ($data === false) {
            return null;
        }
        return unserialize($data);
    }

    public function set(string $key, $value, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $serialized = serialize($value);
        if ($ttl > 0) {
            return $this->redis->setex($this->prefix . $key, $ttl, $serialized);
        }
        return $this->redis->set($this->prefix . $key, $serialized);
    }

    public function has(string $key): bool
    {
        return (bool)$this->redis->exists($this->prefix . $key);
    }

    public function delete(string $key): bool
    {
        return (bool)$this->redis->del($this->prefix . $key);
    }

    public function clear(): bool
    {
        $keys = $this->redis->keys($this->prefix . '*');
        if (empty($keys)) {
            return true;
        }
        return (bool)$this->redis->del($keys);
    }

    public function increment(string $key, int $step = 1): int
    {
        return $this->redis->incrBy($this->prefix . $key, $step);
    }

    public function decrement(string $key, int $step = 1): int
    {
        return $this->redis->decrBy($this->prefix . $key, $step);
    }
}
