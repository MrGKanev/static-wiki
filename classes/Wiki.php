<?php

/**
 * Main Wiki class
 * Handles content retrieval, navigation, and search functionality
 * Enhanced version with improved nested directory support
 */

class Wiki
{
  private $contentDir;
  private $navigation;
  private $cache;

  public function __construct($contentDir = null, $cache = null)
  {
    $this->contentDir = $contentDir ?: CONTENT_DIR;
    $this->navigation = null;
    $this->cache = $cache;

    // Initialize cache if enabled and not provided
    if (ENABLE_CACHE && $this->cache === null) {
      $this->cache = new Cache();
    }
  }

  /**
   * Get the current page path from URL parameters
   * Enhanced with better nested path handling
   */
  public function getCurrentPath()
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
  private function sanitizePathEnhanced($path)
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
  private function sanitizePath($path)
  {
    return $this->sanitizePathEnhanced($path);
  }

  /**
   * Enhanced getPageContent with multiple path resolution
   */
  public function getPageContent($path)
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
  private function generatePossiblePaths($path)
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
  public function getRawPageContent($path)
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
  private function getFilePath($path)
  {
    if (empty($path)) {
      return $this->contentDir . '/index.md';
    }

    return $this->contentDir . '/' . $path . '.md';
  }

  /**
   * Check if file exists and is valid
   */
  private function isValidFile($filePath)
  {
    if (!file_exists($filePath)) {
      return false;
    }

    // Security check: ensure file is within content directory
    $realContentDir = realpath($this->contentDir);
    $realFilePath = realpath($filePath);

    if (!$realFilePath || strpos($realFilePath, $realContentDir) !== 0) {
      return false;
    }

    // Check file extension
    $extension = pathinfo($filePath, PATHINFO_EXTENSION);
    return in_array($extension, ALLOWED_FILE_EXTENSIONS);
  }

  /**
   * Build and cache navigation tree
   */
  public function getNavigation()
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
  private function buildNavTree($dir, $relativePath = '')
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
  private function getSortedDirectoryContents($dir)
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
  private function shouldSkipFile($file)
  {
    return $file[0] === '.' || $file === 'README.md';
  }

  /**
   * Check if file is markdown
   */
  private function isMarkdownFile($file)
  {
    return pathinfo($file, PATHINFO_EXTENSION) === 'md';
  }

  /**
   * Create category navigation item
   */
  private function createCategoryItem($file, $relativeFilePath, $fullPath)
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
  private function createPageItem($file, $relativeFilePath)
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
  private function constructPagePath($relativeFilePath, $name)
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
   */
  public function search($query)
  {
    if (empty($query) || strlen($query) < 2) {
      return [];
    }

    // Use cache if enabled
    if ($this->cache && ENABLE_CACHE) {
      $cacheKey = 'search_' . md5($query);

      return $this->cache->rememberDirectory(
        $cacheKey,
        $this->contentDir,
        function () use ($query) {
          $results = [];
          $this->searchInDirectory($this->contentDir, $query, $results);
          return array_slice($results, 0, MAX_SEARCH_RESULTS);
        },
        SEARCH_CACHE_TTL
      );
    }

    // Fallback without cache
    $results = [];
    $this->searchInDirectory($this->contentDir, $query, $results);

    // Limit results for performance
    return array_slice($results, 0, MAX_SEARCH_RESULTS);
  }

  /**
   * Recursively search in directory
   */
  private function searchInDirectory($dir, $query, &$results, $relativePath = '')
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
  private function searchInFile($filePath, $relativeFilePath, $query, &$results)
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
   * Get page title from content or generate from filename
   */
  public function getPageTitle($path)
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
  private function generateTitleFromPath($path)
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
   * Get breadcrumb navigation for current path
   */
  public function getBreadcrumbs($currentPath)
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
  public function getPageHeadings($path)
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
  public function hasContent()
  {
    return is_dir($this->contentDir) && count(scandir($this->contentDir)) > 2;
  }

  /**
   * Clear all cache entries
   */
  public function clearCache()
  {
    if ($this->cache && ENABLE_CACHE) {
      return $this->cache->clear();
    }
    return 0;
  }

  /**
   * Clean expired cache entries
   */
  public function cleanupCache()
  {
    if ($this->cache && ENABLE_CACHE) {
      return $this->cache->cleanup();
    }
    return 0;
  }

  /**
   * Get cache statistics
   */
  public function getCacheStats()
  {
    if ($this->cache && ENABLE_CACHE) {
      return $this->cache->getStats();
    }
    return ['total' => 0, 'size' => 0, 'expired' => 0, 'enabled' => false];
  }

  /**
   * Check if caching is enabled and working
   */
  public function isCacheEnabled()
  {
    return ENABLE_CACHE && $this->cache !== null;
  }

  /**
   * Get the last modified time of a page
   */
  public function getPageModified($path)
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
