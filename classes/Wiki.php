<?php

declare(strict_types=1);

namespace Wiki;

use Wiki\Interfaces\CacheInterface;

/**
 * Main Wiki class - core content management for Static Wiki
 *
 * Handles all content operations including page retrieval, navigation building,
 * search functionality, and caching. Supports nested directory structures for
 * organizing content hierarchically.
 *
 * @package Wiki
 * @author  Static Wiki Contributors
 * @license MIT
 *
 * @example Basic usage:
 * ```php
 * $cache = new Cache();
 * $wiki = new Wiki(null, $cache);
 *
 * // Get current page
 * $path = $wiki->getCurrentPath();
 * $content = $wiki->getPageContent($path);
 * $title = $wiki->getPageTitle($path);
 *
 * // Build navigation
 * $nav = $wiki->getNavigation();
 *
 * // Search content
 * $results = $wiki->search('api documentation');
 * ```
 */
class Wiki
{
  /** @var string Root directory for content files */
  private readonly string $contentDir;

  /** @var array<mixed>|null Cached navigation tree */
  private ?array $navigation = null;

  /** @var CacheInterface|null Cache instance for performance */
  private ?CacheInterface $cache;

  /** @var SearchIndex|null Search index for fast lookups */
  private ?SearchIndex $searchIndex = null;

  /** @var array<string, bool> In-memory cache for file existence checks */
  private array $fileExistsCache = [];

  /**
   * Create a new Wiki instance
   *
   * @param string|null         $contentDir Directory containing Markdown files (defaults to CONTENT_DIR)
   * @param CacheInterface|null $cache      Cache instance for improved performance
   */
  public function __construct(?string $contentDir = null, ?CacheInterface $cache = null)
  {
    $this->contentDir = $contentDir ?? CONTENT_DIR;
    $this->cache = $cache;

    // Initialize cache if enabled and not provided
    if (ENABLE_CACHE && $this->cache === null) {
      $this->cache = new Cache();
    }
  }

  /**
   * Get the current page path from URL parameters
   *
   * Extracts and sanitizes the 'page' parameter from the URL query string.
   * Handles nested paths and prevents directory traversal attacks.
   *
   * @return string Sanitized page path (empty string for home page)
   */
  public function getCurrentPath(): string
  {
    $path = $_GET['page'] ?? '';
    $path = trim($path, '/');

    // Enhanced security: prevent directory traversal while preserving valid nested paths
    $path = $this->sanitizePathEnhanced($path);

    if (DEBUG_MODE) {
      error_log("getCurrentPath: Raw path from URL: " . ($_GET['page'] ?? 'empty'));
      error_log("getCurrentPath: Sanitized path: $path");
    }

    return $path;
  }

  /**
   * Enhanced path sanitization that preserves valid nested structures
   */
  private function sanitizePathEnhanced(string $path): string
  {
    // Remove any directory traversal attempts
    $path = str_replace(['../', '..\\', './'], '', $path);

    // Remove any null bytes
    $path = str_replace("\0", '', $path);

    // Allow alphanumeric, hyphens, underscores, forward slashes, and dots
    $path = preg_replace('/[^a-zA-Z0-9\-_\/\.]/', '', $path);

    // Remove multiple consecutive slashes
    $path = preg_replace('/\/+/', '/', $path);

    // Remove leading/trailing slashes
    $path = trim($path, '/');

    return $path;
  }

  /**
   * Legacy sanitizePath method for backward compatibility
   */
  private function sanitizePath(string $path): string
  {
    return $this->sanitizePathEnhanced($path);
  }

  /**
   * Get rendered HTML content for a page
   *
   * Resolves the path to a Markdown file, parses it, and returns
   * the rendered HTML. Handles multiple path formats (with/without extension,
   * index files, etc.) and uses caching for performance.
   *
   * @param string $path Page path (e.g., "docs/api" or "getting-started")
   *
   * @return string|null Rendered HTML content, or null if page not found
   */
  public function getPageContent(string $path): ?string
  {
    // Try multiple path variations to handle different URL formats
    $possiblePaths = $this->generatePossiblePaths($path);

    if (DEBUG_MODE) {
      error_log("getPageContent: Trying paths for '$path': " . implode(', ', $possiblePaths));
    }

    foreach ($possiblePaths as $tryPath) {
      $filePath = $this->getFilePath($tryPath);

      if (DEBUG_MODE) {
        error_log("getPageContent: Checking file: $filePath");
      }

      if ($this->isValidFile($filePath)) {
        if (DEBUG_MODE) {
          error_log("getPageContent: Found valid file: $filePath");
        }

        // Use cache if enabled
        if ($this->cache && ENABLE_CACHE) {
          $cacheKey = 'content_' . md5($tryPath);
          return $this->cache->rememberFile(
            $cacheKey,
            $filePath,
            function () use ($filePath) {
              return MarkdownParser::parse(file_get_contents($filePath));
            },
            CONTENT_CACHE_TTL
          );
        }

        return MarkdownParser::parse(file_get_contents($filePath));
      }
    }

    if (DEBUG_MODE) {
      error_log("getPageContent: No valid file found for path: $path");
    }

    return null;
  }

  /**
   * Generate possible file paths for a given URL path
   * This helps handle various URL formats and nested structures
   */
  private function generatePossiblePaths(string $path): array
  {
    $paths = [];

    // Original path as-is
    $paths[] = $path;

    // Handle cases where the path might need the filename repeated
    // For example: implementation/Z1/accessories -> implementation/Z1/accessories/accessories
    $pathParts = explode('/', $path);
    if (count($pathParts) >= 2) {
      $lastPart = end($pathParts);
      if (!empty($lastPart)) {
        $paths[] = $path . '/' . $lastPart;
      }
    }

    // Handle index files
    $paths[] = $path . '/index';

    return array_unique($paths);
  }

  /**
   * Get raw page content without markdown parsing
   * Enhanced with multiple path resolution
   */
  public function getRawPageContent(string $path): ?string
  {
    $possiblePaths = $this->generatePossiblePaths($path);

    foreach ($possiblePaths as $tryPath) {
      $filePath = $this->getFilePath($tryPath);

      if ($this->isValidFile($filePath)) {
        return file_get_contents($filePath);
      }
    }

    return null;
  }

  /**
   * Get file path from page path
   */
  private function getFilePath(string $path): string
  {
    if (empty($path)) {
      return $this->contentDir . '/index.md';
    }

    return $this->contentDir . '/' . $path . '.md';
  }

  /**
   * Check if file exists and is valid (with in-memory caching)
   */
  private function isValidFile(string $filePath): bool
  {
    // Check in-memory cache first for performance
    $cacheKey = md5($filePath);
    if (isset($this->fileExistsCache[$cacheKey])) {
      return $this->fileExistsCache[$cacheKey];
    }

    // Perform actual validation
    $result = $this->validateFile($filePath);

    // Cache the result for this request
    $this->fileExistsCache[$cacheKey] = $result;

    return $result;
  }

  /**
   * Perform actual file validation
   */
  private function validateFile(string $filePath): bool
  {
    if (!file_exists($filePath)) {
      return false;
    }

    // Security check: ensure file is within content directory
    $realContentDir = realpath($this->contentDir);
    $realFilePath = realpath($filePath);

    if (!$realFilePath || !$realContentDir || strpos($realFilePath, $realContentDir) !== 0) {
      return false;
    }

    // Check file extension
    $extension = pathinfo($filePath, PATHINFO_EXTENSION);
    return in_array($extension, ALLOWED_FILE_EXTENSIONS);
  }

  /**
   * Get the navigation tree
   *
   * Builds a hierarchical navigation structure from the content directory.
   * Categories (folders) contain pages (Markdown files). The tree is
   * cached for performance and invalidated when content changes.
   *
   * @return array<array{type: string, name: string, path?: string, items?: array}>
   */
  public function getNavigation(): array
  {
    if ($this->navigation !== null) {
      return $this->navigation;
    }

    // Use cache if enabled
    if ($this->cache && ENABLE_CACHE) {
      $cacheKey = 'navigation';

      $this->navigation = $this->cache->rememberDirectory(
        $cacheKey,
        $this->contentDir,
        function () {
          return $this->buildNavTree($this->contentDir);
        },
        NAVIGATION_CACHE_TTL
      );
    } else {
      // Fallback without cache
      $this->navigation = $this->buildNavTree($this->contentDir);
    }

    return $this->navigation;
  }

  /**
   * Enhanced buildNavTree with better debugging and path handling
   */
  private function buildNavTree(string $dir, string $relativePath = ''): array
  {
    $items = [];

    if (!is_dir($dir)) {
      if (DEBUG_MODE) {
        error_log("buildNavTree: Directory does not exist: $dir");
      }
      return $items;
    }

    $files = $this->getSortedDirectoryContents($dir);

    if (DEBUG_MODE) {
      error_log("buildNavTree: Processing directory: $dir (relativePath: $relativePath)");
      error_log("buildNavTree: Found files: " . implode(', ', $files));
    }

    foreach ($files as $file) {
      if ($this->shouldSkipFile($file)) {
        continue;
      }

      $fullPath = $dir . '/' . $file;
      $relativeFilePath = $relativePath ? $relativePath . '/' . $file : $file;

      if (is_dir($fullPath)) {
        $categoryItem = $this->createCategoryItem($file, $relativeFilePath, $fullPath);
        if ($categoryItem) {
          $items[] = $categoryItem;

          if (DEBUG_MODE) {
            error_log("buildNavTree: Created category: {$file} with " . count($categoryItem['children']) . " children");
          }
        }
      } elseif ($this->isMarkdownFile($file)) {
        $pageItem = $this->createPageItem($file, $relativeFilePath);
        if ($pageItem) {
          $items[] = $pageItem;

          if (DEBUG_MODE) {
            error_log("buildNavTree: Created page: {$file} -> path: {$pageItem['path']}");
          }
        }
      }
    }

    return $items;
  }

  /**
   * Get sorted directory contents
   */
  private function getSortedDirectoryContents(string $dir): array
  {
    $files = scandir($dir);

    // Remove . and ..
    $files = array_filter($files, function ($file) {
      return !in_array($file, ['.', '..']);
    });

    // Sort: directories first, then files
    usort($files, function ($a, $b) use ($dir) {
      $aIsDir = is_dir($dir . '/' . $a);
      $bIsDir = is_dir($dir . '/' . $b);

      if ($aIsDir && !$bIsDir) return -1;
      if (!$aIsDir && $bIsDir) return 1;

      return strcasecmp($a, $b);
    });

    return $files;
  }

  /**
   * Check if file should be skipped
   */
  private function shouldSkipFile(string $file): bool
  {
    return $file[0] === '.' || $file === 'README.md';
  }

  /**
   * Check if file is markdown
   */
  private function isMarkdownFile(string $file): bool
  {
    return pathinfo($file, PATHINFO_EXTENSION) === 'md';
  }

  /**
   * Create category navigation item
   */
  private function createCategoryItem(string $file, string $relativeFilePath, string $fullPath): array
  {
    return [
      'type' => 'category',
      'name' => $this->generateTitleFromPath($file),
      'path' => $relativeFilePath,
      'children' => $this->buildNavTree($fullPath, $relativeFilePath)
    ];
  }

  /**
   * Enhanced createPageItem method with better path handling for nested files
   */
  private function createPageItem(string $file, string $relativeFilePath): ?array
  {
    $name = pathinfo($file, PATHINFO_FILENAME);

    // Skip index files in navigation (they're handled by categories)
    if ($name === 'index') {
      return null;
    }

    // Improved path construction for nested files
    $pagePath = $this->constructPagePath($relativeFilePath, $name);

    // Add debug logging if enabled
    if (DEBUG_MODE) {
      error_log("Creating page item: file=$file, relativeFilePath=$relativeFilePath, pagePath=$pagePath");
    }

    return [
      'type' => 'page',
      'name' => $this->generateTitleFromPath($name),
      'path' => $pagePath,
      'fullRelativePath' => $relativeFilePath // Add for debugging
    ];
  }

  /**
   * Improved path construction that handles nested directories properly
   */
  private function constructPagePath(string $relativeFilePath, string $name): string
  {
    $directory = dirname($relativeFilePath);

    // Handle root level files
    if ($directory === '.' || empty($directory)) {
      return $name;
    }

    // Remove the .md extension from the relative file path to get the page path
    $pathWithoutExtension = preg_replace('/\.md$/', '', $relativeFilePath);

    return $pathWithoutExtension;
  }

  /**
   * Search for content across all markdown files
   * Uses inverted index for fast lookups when cache is enabled
   */
  public function search(string $query): array
  {
    if (empty($query) || strlen($query) < 2) {
      return [];
    }

    // Use SearchIndex for faster lookups when cache is enabled
    if (ENABLE_CACHE) {
      return $this->searchWithIndex($query);
    }

    // Fallback to directory scan without cache
    $results = [];
    $this->searchInDirectory($this->contentDir, $query, $results);

    // Limit results for performance
    return array_slice($results, 0, MAX_SEARCH_RESULTS);
  }

  /**
   * Search using the inverted index (much faster for large wikis)
   *
   * @return array<array{title: string, path: string, snippet: string}>
   */
  private function searchWithIndex(string $query): array
  {
    // Lazy initialize search index
    if ($this->searchIndex === null) {
      $this->searchIndex = new SearchIndex($this->contentDir, $this->cache);
    }

    $indexResults = $this->searchIndex->search($query, MAX_SEARCH_RESULTS);

    // Convert to standard result format (remove score)
    $results = [];
    foreach ($indexResults as $result) {
      $results[] = [
        'title' => $result['title'],
        'path' => $result['path'],
        'snippet' => $result['snippet']
      ];
    }

    return $results;
  }

  /**
   * Recursively search in directory
   */
  private function searchInDirectory(string $dir, string $query, array &$results, string $relativePath = ''): void
  {
    if (!is_dir($dir)) {
      return;
    }

    $files = scandir($dir);

    foreach ($files as $file) {
      if ($this->shouldSkipFile($file)) {
        continue;
      }

      $fullPath = $dir . '/' . $file;
      $relativeFilePath = $relativePath ? $relativePath . '/' . $file : $file;

      if (is_dir($fullPath)) {
        $this->searchInDirectory($fullPath, $query, $results, $relativeFilePath);
      } elseif ($this->isMarkdownFile($file)) {
        $this->searchInFile($fullPath, $relativeFilePath, $query, $results);
      }
    }
  }

  /**
   * Search within a specific file
   */
  private function searchInFile(string $filePath, string $relativeFilePath, string $query, array &$results): void
  {
    $content = file_get_contents($filePath);

    if (stripos($content, $query) === false) {
      return;
    }

    $name = pathinfo($relativeFilePath, PATHINFO_FILENAME);

    // Use the enhanced path construction
    $pagePath = $this->constructPagePath($relativeFilePath, $name);

    // Get title and snippet
    $title = MarkdownParser::extractTitle($content) ?: $this->generateTitleFromPath($name);
    $snippet = MarkdownParser::createSearchSnippet($content, $query);

    $results[] = [
      'title' => $title,
      'path' => $pagePath,
      'snippet' => $snippet
    ];
  }

  /**
   * Get the title for a page
   *
   * Extracts the title from the page's first H1 heading, or generates
   * a human-readable title from the file path if no heading is found.
   *
   * @param string $path Page path
   *
   * @return string Page title (defaults to "Home" for empty path)
   */
  public function getPageTitle(string $path): string
  {
    if (empty($path)) {
      return 'Home';
    }

    $rawContent = $this->getRawPageContent($path);

    if ($rawContent === null) {
      return '404 - Page Not Found';
    }

    // Try to extract title from content
    $title = MarkdownParser::extractTitle($rawContent);
    if ($title) {
      return $title;
    }

    // Generate title from path
    return $this->generateTitleFromPath($path);
  }

  /**
   * Generate a readable title from a file/directory path
   */
  private function generateTitleFromPath(string $path): string
  {
    // For path-based title generation, use the last part of the path
    $basename = basename($path);

    // Remove file extension
    $basename = preg_replace('/\.[^.]+$/', '', $basename);

    // Replace dashes and underscores with spaces
    $basename = str_replace(['-', '_'], ' ', $basename);

    // Capitalize words
    return ucwords($basename);
  }

  /**
   * Get breadcrumb navigation for a path
   *
   * Builds an array of breadcrumb items from root to the current page,
   * useful for displaying navigation context.
   *
   * @param string $currentPath Current page path
   *
   * @return array<array{name: string, path: string}> Breadcrumb items
   */
  public function getBreadcrumbs(string $currentPath): array
  {
    if (empty($currentPath)) {
      return [];
    }

    $breadcrumbs = [['name' => 'Home', 'path' => '']];
    $pathParts = explode('/', $currentPath);
    $currentPathBuild = '';

    foreach ($pathParts as $part) {
      $currentPathBuild .= ($currentPathBuild ? '/' : '') . $part;
      $breadcrumbs[] = [
        'name' => $this->generateTitleFromPath($part),
        'path' => $currentPathBuild
      ];
    }

    return $breadcrumbs;
  }

  /**
   * Get page headings for table of contents
   */
  public function getPageHeadings(string $path): array
  {
    $rawContent = $this->getRawPageContent($path);

    if ($rawContent === null) {
      return [];
    }

    return MarkdownParser::extractHeaders($rawContent);
  }

  /**
   * Check if wiki has content
   */
  public function hasContent(): bool
  {
    return is_dir($this->contentDir) && count(scandir($this->contentDir)) > 2;
  }

  /**
   * Clear all cache entries
   */
  public function clearCache(): int
  {
    if ($this->cache && ENABLE_CACHE) {
      return $this->cache->clear();
    }
    return 0;
  }

  /**
   * Clean expired cache entries
   */
  public function cleanupCache(): int
  {
    if ($this->cache && ENABLE_CACHE) {
      return $this->cache->cleanup();
    }
    return 0;
  }

  /**
   * Get cache statistics
   */
  public function getCacheStats(): array
  {
    if ($this->cache && ENABLE_CACHE) {
      return $this->cache->getStats();
    }
    return ['total' => 0, 'size' => 0, 'expired' => 0, 'enabled' => false];
  }

  /**
   * Check if caching is enabled and working
   */
  public function isCacheEnabled(): bool
  {
    return ENABLE_CACHE && $this->cache !== null;
  }

  /**
   * Get the last modified time of a page
   */
  public function getPageModified(string $path): ?int
  {
    $possiblePaths = $this->generatePossiblePaths($path);

    foreach ($possiblePaths as $tryPath) {
      $filePath = $this->getFilePath($tryPath);

      if ($this->isValidFile($filePath)) {
        return filemtime($filePath);
      }
    }

    return null;
  }
}
