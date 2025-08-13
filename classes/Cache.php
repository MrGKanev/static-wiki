<?php

/**
 * Simple file-based caching system
 * Improves performance by caching expensive operations
 */

class Cache
{
  private $cacheDir;
  private $defaultTtl;

  public function __construct($cacheDir = null, $defaultTtl = 3600)
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
  public function get($key)
  {
    $filePath = $this->getCacheFilePath($key);

    if (!file_exists($filePath)) {
      return null;
    }

    $data = file_get_contents($filePath);
    $cache = unserialize($data);

    if (!$cache || !isset($cache['expires']) || !isset($cache['data'])) {
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
  public function set($key, $data, $ttl = null)
  {
    $ttl = $ttl ?: $this->defaultTtl;
    $filePath = $this->getCacheFilePath($key);

    $cache = [
      'data' => $data,
      'expires' => time() + $ttl,
      'created' => time()
    ];

    $serialized = serialize($cache);

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
  public function delete($key)
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
  public function has($key)
  {
    return $this->get($key) !== null;
  }

  /**
   * Get cached data or execute callback and cache the result
   */
  public function remember($key, $callback, $ttl = null)
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
  public function rememberFile($key, $sourceFile, $callback, $ttl = null)
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
  public function rememberDirectory($key, $sourceDir, $callback, $ttl = null)
  {
    $dirMtime = $this->getDirectoryMtime($sourceDir);
    $cacheKey = $key . '_' . $dirMtime;

    return $this->remember($cacheKey, $callback, $ttl);
  }

  /**
   * Clear all cache entries
   */
  public function clear()
  {
    if (!is_dir($this->cacheDir)) {
      return true;
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
  public function cleanup()
  {
    if (!is_dir($this->cacheDir)) {
      return 0;
    }

    $files = glob($this->cacheDir . '/*.cache');
    $cleaned = 0;

    foreach ($files as $file) {
      $data = file_get_contents($file);
      $cache = unserialize($data);

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
  public function getStats()
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
      $cache = unserialize($data);

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
  private function getCacheFilePath($key)
  {
    $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
    return $this->cacheDir . '/' . $safeKey . '.cache';
  }

  /**
   * Get the latest modification time for a directory and its contents
   */
  private function getDirectoryMtime($dir)
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
  private function formatBytes($size)
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
  public static function generateKey(...$params)
  {
    return md5(serialize($params));
  }
}
