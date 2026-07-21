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
    // ============================================================
    // AVD-007 修复：统一缓存键命名规范
    // 格式：{prefix}{module}:{identifier}
    // ============================================================
    public function key(string $module, string $identifier): string
    {
        return $this->prefix . $module . ':' . $identifier;
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
    // AVD-007 修复：新增 getWithPrefix() 方法，用于直接使用完整键名（含前缀）
    public function getWithPrefix(string $key)
    {
        $data = $this->redis->get($key);
        if ($data === false) {
            return null;
        }
        return unserialize($data);
    }
    // AVD-007 修复：新增 setWithPrefix() 方法
    public function setWithPrefix(string $key, $value, ?int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $serialized = serialize($value);
        if ($ttl > 0) {
            return $this->redis->setex($key, $ttl, $serialized);
        }
        return $this->redis->set($key, $serialized);
    }
    // AVD-007 修复：新增 hasWithPrefix() 方法
    public function hasWithPrefix(string $key): bool
    {
        return (bool)$this->redis->exists($key);
    }
    // AVD-007 修复：新增 deleteWithPrefix() 方法
    public function deleteWithPrefix(string $key): bool
    {
        return (bool)$this->redis->del($key);
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
    // AVD-007 修复：新增 clearModule() 方法，删除指定模块的所有缓存
    public function clearModule(string $module): bool
    {
        $pattern = $this->prefix . $module . ':*';
        $keys = $this->redis->keys($pattern);
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