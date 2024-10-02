<?php

declare(strict_types=1);

namespace Ebrana\ElasticsearchExtension\Services;

use Nette\Bridges\Psr\PsrCacheAdapter;
use Psr\Cache\CacheItemInterface;

final class CacheItem implements CacheItemInterface
{
    private mixed $data;

    public function __construct(private readonly PsrCacheAdapter $adapter, private readonly string $key)
    {
        $this->data = $this->adapter->get($this->key);
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->data;
    }

    public function isHit(): bool
    {
        return $this->data !== null;
    }

    public function set(mixed $value): static
    {
        $this->data = $value;

        return $this;
    }

    public function expiresAt(?\DateTimeInterface $expiration): static
    {
        return $this;
    }

    public function expiresAfter(\DateInterval|int|null $time): static
    {
        return $this;
    }
}
