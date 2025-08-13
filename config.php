<?php

/**
 * Configuration file for Company Wiki
 */

// Basic configuration
define('WIKI_TITLE', 'Company Wiki');
define('CONTENT_DIR', __DIR__ . '/content');
define('CACHE_DIR', __DIR__ . '/cache');

// Application settings
define('ITEMS_PER_PAGE', 10);
define('SEARCH_SNIPPET_LENGTH', 200);
define('DEBUG_MODE', false);

// Cache settings
define('ENABLE_CACHE', true);
define('CACHE_TTL', 3600);           // 1 hour default TTL
define('NAVIGATION_CACHE_TTL', 7200); // 2 hours for navigation
define('CONTENT_CACHE_TTL', 1800);    // 30 minutes for content
define('SEARCH_CACHE_TTL', 600);      // 10 minutes for search results

// Security settings
define('ALLOWED_FILE_EXTENSIONS', ['md']);
define('MAX_SEARCH_RESULTS', 50);

// Paths
define('CLASSES_DIR', __DIR__ . '/classes');
define('TEMPLATES_DIR', __DIR__ . '/templates');
define('ASSETS_DIR', __DIR__ . '/assets');

// Auto-create directories if they don't exist
$requiredDirs = [CONTENT_DIR, CACHE_DIR];
foreach ($requiredDirs as $dir) {
  if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
  }
}

// Error reporting based on debug mode
if (DEBUG_MODE) {
  error_reporting(E_ALL);
  ini_set('display_errors', 1);
} else {
  error_reporting(0);
  ini_set('display_errors', 0);
}
