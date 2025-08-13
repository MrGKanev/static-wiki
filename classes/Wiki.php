<?php

/**
 * Main Wiki class
 * Handles content retrieval, navigation, and search functionality
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
   */
  public function getCurrentPath()
  {
    $path = $_GET['page'] ?? '';
    $path = trim($path, '/');

    // Security: prevent directory traversal
    $path = $this->sanitizePath($path);

    return $path;
  }

  /**
   * Sanitize file path to prevent security issues
   */
  private function sanitizePath($path)
  {
    // Remove any directory traversal attempts
    $path = str_replace(['../', '..\\', './'], '', $path);

    // Remove any null bytes
    $path = str_replace("\0", '', $path);

    // Only allow alphanumeric, hyphens, underscores, and forward slashes
    $path = preg_replace('/[^a-zA-Z0-9\-_\/]/', '', $path);

    return $path;
  }

  /**
   * Get content of a specific page
   */
  public function getPageContent($path)
  {
    $filePath = $this->getFilePath($path);

    if (!$this->isValidFile($filePath)) {
      return null;
    }

    // Use cache if enabled
    if ($this->cache && ENABLE_CACHE) {
      $cacheKey = 'content_' . md5($path);

      return $this->cache->rememberFile($cacheKey, $filePath, function () use ($filePath) {
        $content = file_get_contents($filePath);
        return MarkdownParser::parse($content);
      }, CONTENT_CACHE_TTL);
    }

    // Fallback without cache
    $content = file_get_contents($filePath);
    return MarkdownParser::parse($content);
  }

  /**
   * Get raw content of a page (for search)
   */
  public function getRawPageContent($path)
  {
    $filePath = $this->getFilePath($path);

    if (!$this->isValidFile($filePath)) {
      return null;
    }

    return file_get_contents($filePath);
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
   * Generate a readable title from file path
   */
  private function generateTitleFromPath($path)
  {
    $basename = basename($path);
    return ucwords(str_replace(['-', '_'], ' ', $basename));
  }

  /**
   * Get file path for a given page path
   */
  private function getFilePath($path)
  {
    if (empty($path)) {
      $path = 'index';
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
   * Recursively build navigation tree from directory structure
   */
  private function buildNavTree($dir, $relativePath = '')
  {
    $items = [];

    if (!is_dir($dir)) {
      return $items;
    }

    $files = $this->getSortedDirectoryContents($dir);

    foreach ($files as $file) {
      if ($this->shouldSkipFile($file)) {
        continue;
      }

      $fullPath = $dir . '/' . $file;
      $relativeFilePath = $relativePath ? $relativePath . '/' . $file : $file;

      if (is_dir($fullPath)) {
        $items[] = $this->createCategoryItem($file, $relativeFilePath, $fullPath);
      } elseif ($this->isMarkdownFile($file)) {
        $pageItem = $this->createPageItem($file, $relativeFilePath);
        if ($pageItem) {
          $items[] = $pageItem;
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
   * Create page navigation item
   */
  private function createPageItem($file, $relativeFilePath)
  {
    $name = pathinfo($file, PATHINFO_FILENAME);

    // Skip index files in navigation (they're handled by categories)
    if ($name === 'index') {
      return null;
    }

    $pagePath = dirname($relativeFilePath);
    $pagePath = ($pagePath === '.') ? $name : $pagePath . '/' . $name;

    return [
      'type' => 'page',
      'name' => $this->generateTitleFromPath($name),
      'path' => $pagePath
    ];
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
    $pagePath = dirname($relativeFilePath);
    $pagePath = ($pagePath === '.') ? $name : $pagePath . '/' . $name;

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
}
