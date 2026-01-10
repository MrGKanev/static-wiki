<?php

declare(strict_types=1);

namespace Wiki\Enums;

/**
 * Cache type enumeration for different caching contexts
 * Each type has its own TTL based on how frequently data changes
 */
enum CacheType: string
{
    case CONTENT = 'content';
    case NAVIGATION = 'navigation';
    case SEARCH = 'search';
    case PAGE_METADATA = 'page_metadata';

    /**
     * Get the TTL (time-to-live) for this cache type
     */
    public function getTtl(): int
    {
        return match ($this) {
            self::CONTENT => defined('CONTENT_CACHE_TTL') ? CONTENT_CACHE_TTL : 1800,
            self::NAVIGATION => defined('NAVIGATION_CACHE_TTL') ? NAVIGATION_CACHE_TTL : 7200,
            self::SEARCH => defined('SEARCH_CACHE_TTL') ? SEARCH_CACHE_TTL : 600,
            self::PAGE_METADATA => defined('CONTENT_CACHE_TTL') ? CONTENT_CACHE_TTL : 1800,
        };
    }

    /**
     * Get the cache key prefix for this type
     */
    public function getPrefix(): string
    {
        return match ($this) {
            self::CONTENT => 'content_',
            self::NAVIGATION => 'nav_',
            self::SEARCH => 'search_',
            self::PAGE_METADATA => 'meta_',
        };
    }

    /**
     * Get human-readable description
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::CONTENT => 'Page content cache',
            self::NAVIGATION => 'Navigation tree cache',
            self::SEARCH => 'Search results cache',
            self::PAGE_METADATA => 'Page metadata cache (titles, headings)',
        };
    }
}
