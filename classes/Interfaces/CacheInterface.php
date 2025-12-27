<?php

declare(strict_types=1);

namespace Wiki\Interfaces;

/**
 * Cache Interface
 * Defines the contract for caching implementations
 */
interface CacheInterface
{
    /**
     * Get cached data if it exists and is still valid
     *
     * @param string $key Cache key
     * @return mixed|null The cached data or null if not found/expired
     */
    public function get(string $key): mixed;

    /**
     * Store data in cache
     *
     * @param string $key Cache key
     * @param mixed $data Data to cache
     * @param int|null $ttl Time to live in seconds (null uses default)
     * @return bool True if successful, false otherwise
     */
    public function set(string $key, mixed $data, ?int $ttl = null): bool;

    /**
     * Check if cache entry exists and is valid
     *
     * @param string $key Cache key
     * @return bool True if exists and valid
     */
    public function has(string $key): bool;

    /**
     * Delete a cache entry
     *
     * @param string $key Cache key
     * @return bool True if deleted or didn't exist
     */
    public function delete(string $key): bool;

    /**
     * Get cached data or execute callback and cache the result
     *
     * @param string $key Cache key
     * @param callable $callback Function to execute if cache miss
     * @param int|null $ttl Time to live in seconds
     * @return mixed The cached or computed data
     */
    public function remember(string $key, callable $callback, ?int $ttl = null): mixed;

    /**
     * Clear all cache entries
     *
     * @return int Number of entries cleared
     */
    public function clear(): int;

    /**
     * Clean expired cache entries
     *
     * @return int Number of entries cleaned
     */
    public function cleanup(): int;

    /**
     * Get cache statistics
     *
     * @return array{total: int, size: int, size_human: string, expired: int, valid: int} Statistics array
     */
    public function getStats(): array;
}
