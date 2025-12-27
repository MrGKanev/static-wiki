<?php

declare(strict_types=1);

namespace Wiki;

use Wiki\Interfaces\CacheInterface;

/**
 * Simple file-based caching system
 * Improves performance by caching expensive operations
 */
class Cache implements CacheInterface
{
  private string $cacheDir;
  private int $defaultTtl;

  public function __construct(?string $cacheDir = null, int $defaultTtl = 3600)
  {
    $this->cacheDir = $cacheDir ?: CACHE_DIR;
    $this->defaultTtl = $defaultTtl;

    // Ensure cache directory exists
    if (!is_dir($this->cacheDir)) {
      mkdir($this->cacheDir, 0755, true);
    }
  }

  /**
   * Get cached data if it exists and is still valid
   */
  public function get(string $key): mixed
  {
    $filePath = $this->getCacheFilePath($key);

    if (!file_exists($filePath)) {
      return null;
    }

    $data = file_get_contents($filePath);
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
   * Store data in cache
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
   * Check if cache entry exists and is valid
   */
  public function has(string $key): bool
  {
    return $this->get($key) !== null;
  }

  /**
   * Get cached data or execute callback and cache the result
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
   * Get cached data based on file modification time
   * Automatically invalidates if source file is newer
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
   * Get cached data based on directory modification time
   * Useful for caching navigation trees
   */
  public function rememberDirectory(string $key, string $sourceDir, callable $callback, ?int $ttl = null): mixed
  {
    $dirMtime = $this->getDirectoryMtime($sourceDir);
    $cacheKey = $key . '_' . $dirMtime;

    return $this->remember($cacheKey, $callback, $ttl);
  }

  /**
   * Clear all cache entries
   */
  public function clear(): int
  {
    if (!is_dir($this->cacheDir)) {
      return 0;
    }

    $files = glob($this->cacheDir . '/*.cache');
    $cleared = 0;

    foreach ($files as $file) {
      if (unlink($file)) {
        $cleared++;
      }
    }

    return $cleared;
  }

  /**
   * Clean expired cache entries
   */
  public function cleanup(): int
  {
    if (!is_dir($this->cacheDir)) {
      return 0;
    }

    $files = glob($this->cacheDir . '/*.cache');
    $cleaned = 0;

    foreach ($files as $file) {
      $data = file_get_contents($file);
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
   */
  public function getStats(): array
  {
    if (!is_dir($this->cacheDir)) {
      return ['total' => 0, 'size' => 0, 'expired' => 0];
    }

    $files = glob($this->cacheDir . '/*.cache');
    $total = count($files);
    $size = 0;
    $expired = 0;

    foreach ($files as $file) {
      $size += filesize($file);

      $data = file_get_contents($file);
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
    $iterator = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
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
   * Generate a cache key based on multiple parameters
   */
  public static function generateKey(mixed ...$params): string
  {
    return md5(json_encode($params));
  }
}
