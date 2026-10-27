<?php

declare(strict_types=1);

namespace Contenir\SiteToken\Tests\TestAsset;

use Override;
use Psr\SimpleCache\CacheInterface;

use function array_key_exists;
use function is_iterable;
use function is_string;

/**
 * A PSR-16 cache in an array, which records the lifetime each key was set with and can be told to
 * refuse a key as the standard's InvalidArgumentException.
 *
 * Its parameters are untyped so that it implements every version of the interface the package
 * supports: 1.x declares no types, 2.x adds parameter types and 3.x adds return types, and an
 * implementation may widen a parameter but never narrow it.
 */
final class ArrayCache implements CacheInterface
{
    /** @var array<string, mixed> */
    public array $values = [];

    /** @var array<string, mixed> */
    public array $ttls = [];

    public function __construct(
        private readonly ?string $refuse = null,
    ) {}

    #[Override]
    public function clear(): bool
    {
        $this->values = [];

        return true;
    }

    #[Override]
    public function delete(mixed $key): bool
    {
        unset($this->values[$this->check($key)]);

        return true;
    }

    #[Override]
    public function deleteMultiple(mixed $keys): bool
    {
        /** @var mixed $key */
        foreach ($this->each($keys) as $key) {
            $this->delete($key);
        }

        return true;
    }

    #[Override]
    public function get(mixed $key, mixed $default = null): mixed
    {
        return $this->values[$this->check($key)] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function getMultiple(mixed $keys, mixed $default = null): iterable
    {
        $found = [];
        /** @var mixed $key */
        foreach ($this->each($keys) as $key) {
            $found[$this->check($key)] = $this->get($key, $default);
        }

        return $found;
    }

    #[Override]
    public function has(mixed $key): bool
    {
        return array_key_exists($this->check($key), $this->values);
    }

    #[Override]
    public function set(mixed $key, mixed $value, mixed $ttl = null): bool
    {
        $key                = $this->check($key);
        $this->values[$key] = $value;
        $this->ttls[$key]   = $ttl;

        return true;
    }

    #[Override]
    public function setMultiple(mixed $values, mixed $ttl = null): bool
    {
        /** @var mixed $value */
        foreach ($this->each($values) as $key => $value) {
            $this->set((string) $key, $value, $ttl);
        }

        return true;
    }

    /**
     * @throws RefusedKey
     */
    private function check(mixed $key): string
    {
        if (! is_string($key) || $key === $this->refuse) {
            throw new RefusedKey('The cache refuses this key.');
        }

        return $key;
    }

    /**
     * @return iterable<mixed>
     * @throws RefusedKey
     */
    private function each(mixed $items): iterable
    {
        return is_iterable($items) ? $items : throw new RefusedKey('The cache takes keys as a list.');
    }
}
