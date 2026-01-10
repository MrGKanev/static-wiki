<?php

declare(strict_types=1);

namespace Wiki;

use Wiki\Interfaces\CacheInterface;

/**
 * File-based caching system for the Static Wiki
 *
 * Provides a simple, file-based caching mechanism that stores serialized data
 * as JSON files. Supports TTL-based expiration, file/directory-based cache
 * invalidation, and atomic write operations for data integrity.
 *
 * @package Wiki
 * @author  Static Wiki Contributors
 * @license MIT
 *
 * @example Basic usage:
 * ```php
 * $cache = new Cache('/path/to/cache', 3600);
 *
 * // Store data
 * $cache->set('user_123', ['name' => 'John'], 1800);
 *
 * // Retrieve data
 * $data = $cache->get('user_123');
 *
 * // Use remember pattern
 * $result = $cache->remember('expensive_operation', fn() => computeData(), 3600);
 * ```
 */
class Cache implements CacheInterface
{
  /** @var string Directory where cache files are stored */
  private readonly string $cacheDir;

  /** @var int Default time-to-live in seconds for cache entries */
  private readonly int $defaultTtl;

  /**
   * Create a new Cache instance
   *
   * @param string|null $cacheDir   Directory for cache files (defaults to CACHE_DIR constant)
   * @param int         $defaultTtl Default TTL in seconds (default: 3600 = 1 hour)
   *
   * @throws \RuntimeException If cache directory cannot be created
   */
  public function __construct(?string $cacheDir = null, int $defaultTtl = 3600)
  {
    $this->cacheDir = $cacheDir ?? CACHE_DIR;
    $this->defaultTtl = $defaultTtl;

    // Ensure cache directory exists
    if (!is_dir($this->cacheDir)) {
      mkdir($this->cacheDir, 0755, true);
    }
  }

  /**
   * Retrieve cached data by key
   *
   * Returns the cached data if it exists and hasn't expired,
   * otherwise returns null.
   *
   * @param string $key The cache key to retrieve
   *
   * @return mixed The cached data, or null if not found/expired
   */
  public function get(string $key): mixed
  {
    $filePath = $this->getCacheFilePath($key);

    if (!file_exists($filePath)) {
      return null;
    }

    $data = file_get_contents($filePath);
    if ($data === false) {
      return null;
    }
    $cache = json_decode($data, true);

    if (json_last_error() !== JSON_ERROR_NONE || !$cache || !isset($cache['expires']) || !isset($cache['data'])) {
      return null;
    }

    // Check if cache has expired
    if (time() > $cache['expires']) {
      $this->delete($key);
      return null;
    }

    return $cache['data'];
  }

  /**
   * Store data in the cache
   *
   * Uses atomic write operations (write to temp file, then rename) to ensure
   * data integrity even under concurrent access.
   *
   * @param string   $key  The cache key
   * @param mixed    $data The data to cache (must be JSON-serializable)
   * @param int|null $ttl  Time-to-live in seconds (null = use default)
   *
   * @return bool True on success, false on failure
   */
  public function set(string $key, mixed $data, ?int $ttl = null): bool
  {
    $ttl = $ttl ?: $this->defaultTtl;
    $filePath = $this->getCacheFilePath($key);

    $cache = [
      'data' => $data,
      'expires' => time() + $ttl,
      'created' => time()
    ];

    try {
      $serialized = json_encode($cache, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    } catch (\JsonException $e) {
      error_log('Cache::set() - JSON encoding error: ' . $e->getMessage());
      return false;
    }

    // Atomic write using temporary file
    $tempFile = $filePath . '.tmp';
    if (file_put_contents($tempFile, $serialized, LOCK_EX) !== false) {
      return rename($tempFile, $filePath);
    }

    return false;
  }

  /**
   * Delete a cache entry
   *
   * @param string $key The cache key to delete
   *
   * @return bool True if deleted or didn't exist, false on error
   */
  public function delete(string $key): bool
  {
    $filePath = $this->getCacheFilePath($key);

    if (file_exists($filePath)) {
      return unlink($filePath);
    }

    return true;
  }

  /**
   * Check if a valid cache entry exists
   *
   * @param string $key The cache key to check
   *
   * @return bool True if cache entry exists and is valid
   */
  public function has(string $key): bool
  {
    return $this->get($key) !== null;
  }

  /**
   * Get cached data or execute callback and cache the result
   *
   * This is the recommended pattern for caching expensive operations.
   * If the cache exists, returns it. Otherwise, executes the callback,
   * caches the result, and returns it.
   *
   * @param string   $key      The cache key
   * @param callable $callback Function to execute if cache miss
   * @param int|null $ttl      Time-to-live in seconds (null = use default)
   *
   * @return mixed The cached or computed data
   *
   * @example
   * ```php
   * $users = $cache->remember('all_users', fn() => $db->fetchAllUsers(), 3600);
   * ```
   */
  public function remember(string $key, callable $callback, ?int $ttl = null): mixed
  {
    $data = $this->get($key);

    if ($data !== null) {
      return $data;
    }

    // Execute callback and cache the result
    $data = $callback();
    $this->set($key, $data, $ttl);

    return $data;
  }

  /**
   * Cache data with automatic invalidation when source file changes
   *
   * Creates a cache key that includes the file's modification time,
   * automatically invalidating the cache when the file is modified.
   *
   * @param string   $key        Base cache key
   * @param string   $sourceFile Path to the source file to monitor
   * @param callable $callback   Function to execute on cache miss
   * @param int|null $ttl        Time-to-live in seconds
   *
   * @return mixed The cached or computed data
   */
  public function rememberFile(string $key, string $sourceFile, callable $callback, ?int $ttl = null): mixed
  {
    if (!file_exists($sourceFile)) {
      return $callback();
    }

    $fileMtime = filemtime($sourceFile);
    $cacheKey = $key . '_' . $fileMtime;

    return $this->remember($cacheKey, $callback, $ttl);
  }

  /**
   * Cache data with automatic invalidation when any file in directory changes
   *
   * Monitors an entire directory tree for changes. Useful for caching
   * navigation structures or aggregated content from multiple files.
   *
   * @param string   $key       Base cache key
   * @param string   $sourceDir Path to the directory to monitor
   * @param callable $callback  Function to execute on cache miss
   * @param int|null $ttl       Time-to-live in seconds
   *
   * @return mixed The cached or computed data
   */
  public function rememberDirectory(string $key, string $sourceDir, callable $callback, ?int $ttl = null): mixed
  {
    $dirMtime = $this->getDirectoryMtime($sourceDir);
    $cacheKey = $key . '_' . $dirMtime;

    return $this->remember($cacheKey, $callback, $ttl);
  }

  /**
   * Delete all cache entries
   *
   * Removes all .cache files from the cache directory.
   *
   * @return int Number of cache entries deleted
   */
  public function clear(): int
  {
    if (!is_dir($this->cacheDir)) {
      return 0;
    }

    $files = glob($this->cacheDir . '/*.cache');
    if ($files === false) {
      return 0;
    }
    $cleared = 0;

    foreach ($files as $file) {
      if (unlink($file)) {
        $cleared++;
      }
    }

    return $cleared;
  }

  /**
   * Remove expired cache entries
   *
   * Scans all cache files and removes those that have expired.
   * This is typically called periodically or on a schedule.
   *
   * @return int Number of expired entries removed
   */
  public function cleanup(): int
  {
    if (!is_dir($this->cacheDir)) {
      return 0;
    }

    $files = glob($this->cacheDir . '/*.cache');
    if ($files === false) {
      return 0;
    }
    $cleaned = 0;

    foreach ($files as $file) {
      $data = file_get_contents($file);
      if ($data === false) {
        continue;
      }
      $cache = json_decode($data, true);

      if (!$cache || !isset($cache['expires']) || time() > $cache['expires']) {
        if (unlink($file)) {
          $cleaned++;
        }
      }
    }

    return $cleaned;
  }

  /**
   * Get cache statistics
   *
   * Returns information about the current state of the cache,
   * including total entries, size, and expired entries.
   *
   * @return array{total: int, size: int, size_human: string, expired: int, valid: int}
   */
  public function getStats(): array
  {
    if (!is_dir($this->cacheDir)) {
      return ['total' => 0, 'size' => 0, 'size_human' => '0 B', 'expired' => 0, 'valid' => 0];
    }

    $files = glob($this->cacheDir . '/*.cache');
    if ($files === false) {
      return ['total' => 0, 'size' => 0, 'size_human' => '0 B', 'expired' => 0, 'valid' => 0];
    }
    $total = count($files);
    $size = 0;
    $expired = 0;

    foreach ($files as $file) {
      $fileSize = filesize($file);
      if ($fileSize !== false) {
        $size += $fileSize;
      }

      $data = file_get_contents($file);
      if ($data === false) {
        continue;
      }
      $cache = json_decode($data, true);

      if (!$cache || !isset($cache['expires']) || time() > $cache['expires']) {
        $expired++;
      }
    }

    return [
      'total' => $total,
      'size' => $size,
      'size_human' => $this->formatBytes($size),
      'expired' => $expired,
      'valid' => $total - $expired
    ];
  }

  /**
   * Get cache file path for a given key
   */
  private function getCacheFilePath(string $key): string
  {
    $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
    return $this->cacheDir . '/' . $safeKey . '.cache';
  }

  /**
   * Get the latest modification time for a directory and its contents
   */
  private function getDirectoryMtime(string $dir): int
  {
    if (!is_dir($dir)) {
      return 0;
    }

    $mtime = filemtime($dir);
    if ($mtime === false) {
      return 0;
    }
    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
      $fileMtime = $file->getMTime();
      if ($fileMtime > $mtime) {
        $mtime = $fileMtime;
      }
    }

    return $mtime;
  }

  /**
   * Format bytes in human readable format
   */
  private function formatBytes(int|float $size): string
  {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;

    while ($size >= 1024 && $i < count($units) - 1) {
      $size /= 1024;
      $i++;
    }

    return round($size, 2) . ' ' . $units[$i];
  }

  /**
   * Generate a unique cache key from multiple parameters
   *
   * Creates an MD5 hash from the JSON representation of all parameters,
   * useful for creating cache keys from complex data structures.
   *
   * @param mixed ...$params Any number of parameters to include in the key
   *
   * @return string A 32-character hexadecimal hash
   *
   * @example
   * ```php
   * $key = Cache::generateKey('users', ['active' => true], 1);
   * // Returns something like: "a1b2c3d4e5f6..."
   * ```
   */
  public static function generateKey(mixed ...$params): string
  {
    $json = json_encode($params);
    return md5($json !== false ? $json : serialize($params));
  }
}
