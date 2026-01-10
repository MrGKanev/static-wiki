<?php

declare(strict_types=1);

namespace Wiki;

/**
 * File-based rate limiter for API protection
 *
 * Implements a sliding window rate limiting algorithm using file-based storage.
 * Each client identifier (typically IP address) has its request timestamps stored
 * in a JSON file. Requests outside the time window are automatically filtered out.
 *
 * @package Wiki
 * @author  Static Wiki Contributors
 * @license MIT
 *
 * @example Basic usage with search API:
 * ```php
 * $limiter = new RateLimiter();
 * $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
 *
 * if (!$limiter->checkLimit($clientIp)) {
 *     http_response_code(429);
 *     header('Retry-After: ' . $limiter->getResetTime($clientIp));
 *     die('Rate limit exceeded');
 * }
 *
 * // Add rate limit headers
 * header('X-RateLimit-Remaining: ' . $limiter->getRemainingRequests($clientIp));
 * ```
 */
class RateLimiter
{
    /** @var int Default maximum requests per time window */
    private const DEFAULT_MAX_REQUESTS = 30;

    /** @var int Default time window in seconds */
    private const DEFAULT_TIME_WINDOW = 60;

    /** @var string Directory for storing rate limit data */
    private readonly string $storageDir;

    /** @var int Maximum requests allowed per time window */
    private readonly int $maxRequests;

    /** @var int Time window in seconds */
    private readonly int $timeWindow;

    /**
     * Create a new RateLimiter instance
     *
     * @param string|null $storageDir  Directory for rate limit files (defaults to CACHE_DIR/ratelimit)
     * @param int         $maxRequests Maximum requests per time window (default: 30)
     * @param int         $timeWindow  Time window in seconds (default: 60)
     */
    public function __construct(
        ?string $storageDir = null,
        int $maxRequests = self::DEFAULT_MAX_REQUESTS,
        int $timeWindow = self::DEFAULT_TIME_WINDOW
    ) {
        $this->storageDir = $storageDir ?? (defined('CACHE_DIR') ? CACHE_DIR . '/ratelimit' : sys_get_temp_dir() . '/wiki_ratelimit');
        $this->maxRequests = $maxRequests;
        $this->timeWindow = $timeWindow;

        // Ensure storage directory exists
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
    }

    /**
     * Check if the identifier (usually IP address) is within rate limits
     *
     * @param string $identifier The unique identifier to rate limit (e.g., IP address)
     * @return bool True if within limits, false if rate limited
     */
    public function checkLimit(string $identifier): bool
    {
        $this->cleanup();

        $key = $this->sanitizeKey($identifier);
        $filePath = $this->getFilePath($key);
        $now = time();

        $requests = $this->getRequests($filePath, $now);

        // Check if over limit
        if (count($requests) >= $this->maxRequests) {
            return false;
        }

        // Add current request
        $requests[] = $now;
        $this->saveRequests($filePath, $requests);

        return true;
    }

    /**
     * Get remaining requests for an identifier within the current window
     *
     * @param string $identifier The client identifier (e.g., IP address)
     *
     * @return int Number of requests remaining (0 if rate limited)
     */
    public function getRemainingRequests(string $identifier): int
    {
        $key = $this->sanitizeKey($identifier);
        $filePath = $this->getFilePath($key);
        $now = time();

        $requests = $this->getRequests($filePath, $now);

        return max(0, $this->maxRequests - count($requests));
    }

    /**
     * Get seconds until the rate limit window resets
     *
     * Useful for setting the Retry-After HTTP header when rate limited.
     *
     * @param string $identifier The client identifier (e.g., IP address)
     *
     * @return int Seconds until reset (0 if no active requests)
     */
    public function getResetTime(string $identifier): int
    {
        $key = $this->sanitizeKey($identifier);
        $filePath = $this->getFilePath($key);
        $now = time();

        $requests = $this->getRequests($filePath, $now);

        if (empty($requests)) {
            return 0;
        }

        $oldestRequest = min($requests);
        $resetTime = $oldestRequest + $this->timeWindow - $now;

        return (int) max(0, $resetTime);
    }

    /**
     * Get request timestamps from file, filtering out expired ones
     *
     * @return array<int>
     */
    private function getRequests(string $filePath, int $now): array
    {
        if (!file_exists($filePath)) {
            return [];
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return [];
        }

        // Filter out requests outside the time window
        $cutoff = $now - $this->timeWindow;
        return array_values(array_filter($data, fn(int $timestamp): bool => $timestamp > $cutoff));
    }

    /**
     * Save request timestamps to file
     *
     * @param array<int> $requests
     */
    private function saveRequests(string $filePath, array $requests): void
    {
        $json = json_encode($requests);
        if ($json !== false) {
            file_put_contents($filePath, $json, LOCK_EX);
        }
    }

    /**
     * Get file path for identifier
     */
    private function getFilePath(string $key): string
    {
        return $this->storageDir . '/' . $key . '.json';
    }

    /**
     * Sanitize identifier to safe filename
     */
    private function sanitizeKey(string $identifier): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '_', $identifier) ?? 'unknown';
    }

    /**
     * Cleanup old rate limit files (runs occasionally)
     */
    private function cleanup(): void
    {
        // Only run cleanup 1% of the time
        if (rand(1, 100) > 1) {
            return;
        }

        $files = glob($this->storageDir . '/*.json');
        if ($files === false) {
            return;
        }

        $now = time();
        $maxAge = $this->timeWindow * 2; // Keep files for 2x the time window

        foreach ($files as $file) {
            $mtime = filemtime($file);
            if ($mtime !== false && ($now - $mtime) > $maxAge) {
                @unlink($file);
            }
        }
    }

    /**
     * Clear all rate limit data
     *
     * Removes all stored rate limit files. Useful for testing
     * or resetting all client limits.
     *
     * @return int Number of rate limit files deleted
     */
    public function clear(): int
    {
        $files = glob($this->storageDir . '/*.json');
        if ($files === false) {
            return 0;
        }

        $cleared = 0;
        foreach ($files as $file) {
            if (@unlink($file)) {
                $cleared++;
            }
        }

        return $cleared;
    }
}
