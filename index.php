<?php

/**
 * Company Wiki - Main Entry Point
 * 
 * A simple, file-based wiki system using markdown files
 * organized in a folder structure for navigation.
 */

// Load configuration
require_once __DIR__ . '/config.php';

// Load classes
require_once CLASSES_DIR . '/MarkdownParser.php';
require_once CLASSES_DIR . '/Cache.php';
require_once CLASSES_DIR . '/Wiki.php';

// Initialize cache if enabled
$cache = null;
if (ENABLE_CACHE) {
  $cache = new Cache();

  // Cleanup expired cache entries occasionally (5% chance)
  if (rand(1, 100) <= 5) {
    $cache->cleanup();
  }
}

// Initialize the wiki
$wiki = new Wiki(null, $cache);

// Get current state
$currentPath = $wiki->getCurrentPath();
$isSearch = isset($_GET['search']);
$searchQuery = trim($_GET['q'] ?? '');

// Handle cache management actions (debug mode only)
if (DEBUG_MODE && isset($_GET['action'])) {
  switch ($_GET['action']) {
    case 'clear_cache':
      if ($wiki->isCacheEnabled()) {
        $cleared = $wiki->clearCache();
        // Simple feedback via URL parameter
        header('Location: ?' . http_build_query(array_filter([
          'page' => $currentPath ?: null,
          'cache_cleared' => $cleared
        ])));
        exit;
      }
      break;

    case 'cleanup_cache':
      if ($wiki->isCacheEnabled()) {
        $cleaned = $wiki->cleanupCache();
        header('Location: ?' . http_build_query(array_filter([
          'page' => $currentPath ?: null,
          'cache_cleaned' => $cleaned
        ])));
        exit;
      }
      break;
  }
}

// Initialize variables for templates
$pageContent = null;
$pageTitle = WIKI_TITLE;
$searchResults = [];
$navigation = $wiki->getNavigation();

// Handle search or page display
if ($isSearch) {
  // Search mode
  $pageTitle = 'Search Results - ' . WIKI_TITLE;

  if (!empty($searchQuery)) {
    $searchResults = $wiki->search($searchQuery);
  }

  // Render search template
  ob_start();
  include TEMPLATES_DIR . '/search.php';
  $content = ob_get_clean();
} else {
  // Page display mode
  $pageContent = $wiki->getPageContent($currentPath);
  $pageTitle = $wiki->getPageTitle($currentPath);

  // Add site title unless it's the home page
  if (!empty($currentPath)) {
    $pageTitle .= ' - ' . WIKI_TITLE;
  }

  // Render page template
  ob_start();
  include TEMPLATES_DIR . '/page.php';
  $content = ob_get_clean();
}

// Render the complete page with layout
include TEMPLATES_DIR . '/layout.php';
