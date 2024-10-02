<?php

declare(strict_types=1);

namespace Ebrana\ElasticsearchExtension\Services;

use Nette\Bridges\Psr\PsrCacheAdapter;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

final readonly class PsrCacheDecorator implements CacheItemPoolInterface
{
    public function __construct(private PsrCacheAdapter $adapter)
    {
    }

    public function getItem(string $key): CacheItemInterface
    {
        return new CacheItem($this->adapter, $key);
    }

    public function getItems(array $keys = []): iterable
    {
        $items = [];

        foreach ($keys as $key) {
            $items[] = $this->getItem($key);
        }

        return $items;
    }

    public function hasItem(string $key): bool
    {
        return $this->adapter->has($key);
    }

    public function clear(): bool
    {
        $this->adapter->clear();

        return true;
    }

    public function deleteItem(string $key): bool
    {
        $this->adapter->delete($key);

        return true;
    }

    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->adapter->delete($key);
        }

        return true;
    }

    public function save(CacheItemInterface $item): bool
    {
        $this->adapter->set($item->getKey(), $item->get());

        return true;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return true;
    }

    public function commit(): bool
    {
        return true;
    }
}
