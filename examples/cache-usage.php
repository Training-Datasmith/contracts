<?php

declare(strict_types=1);

/**
 * Example: Symfony Contracts — CacheInterface with stampede prevention.
 *
 * Demonstrates implementing a simple in-memory cache that satisfies
 * CacheInterface and using the callback-based get() pattern.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

// --- Minimal in-memory CacheInterface implementation ---

final class Simple_Memory_Cache implements CacheInterface
{
    /** @var array<string, mixed> */
    private array $store = [];

    public function get(string $key, callable $callback, float $beta = null, array &$metadata = null): mixed
    {
        if (!array_key_exists($key, $this->store)) {
            // Cache miss: compute the value via the callback.
            $item = new Simple_Cache_Item($key);
            $this->store[$key] = $callback($item, $beta);
        }

        return $this->store[$key];
    }

    public function delete(string $key): bool
    {
        unset($this->store[$key]);
        return true;
    }
}

final class Simple_Cache_Item implements ItemInterface
{
    private bool $hit;

    public function __construct(private readonly string $key, bool $hit = false)
    {
        $this->hit = $hit;
    }

    public function getKey(): string { return $this->key; }
    public function isHit(): bool { return $this->hit; }

    // For this minimal example, expiry and tag methods are no-ops.
    public function expiresAt(?\DateTimeInterface $expiration): static { return $this; }
    public function expiresAfter(int|\DateInterval|null $time): static { return $this; }
    public function tag(mixed $tags): static { return $this; }
    public function getMetadata(): array { return []; }
}

// --- Usage ---

$cache = new Simple_Memory_Cache();

// First call: cache miss — callback is invoked.
$result = $cache->get('weather.paris', static function (ItemInterface $item): string {
    echo "Computing expensive value...\n";
    $item->expiresAfter(3600); // cache for 1 hour
    return 'Sunny, 22°C';
});

echo "Weather: {$result}\n"; // Computing expensive value... / Weather: Sunny, 22°C

// Second call: cache hit — callback is NOT invoked.
$result = $cache->get('weather.paris', static function (ItemInterface $item): string {
    echo "This should not print.\n";
    return 'different';
});

echo "Weather (cached): {$result}\n"; // Weather (cached): Sunny, 22°C

// Delete and re-fetch.
$cache->delete('weather.paris');

$result = $cache->get('weather.paris', static function (ItemInterface $item): string {
    echo "Recomputing after delete...\n";
    return 'Cloudy, 15°C';
});

echo "Weather (refetched): {$result}\n";
