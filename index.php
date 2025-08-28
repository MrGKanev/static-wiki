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

// Handle PDF export requests
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
  // Check if TCPDF is available
  if (!file_exists(__DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php')) {
    die('PDF export requires TCPDF library. Install via: composer require tecnickcom/tcpdf');
  }

  require_once CLASSES_DIR . '/PDFExporter.php';

  try {
    $exporter = new PDFExporter($wiki, $cache);
    $exportType = $_GET['type'] ?? 'page';
    $path = $wiki->getCurrentPath();

    if ($exportType === 'section') {
      $result = $exporter->exportSection($path);
    } else {
      $result = $exporter->exportPage($path);
    }

    // Set headers for PDF download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
    header('Content-Length: ' . strlen($result['content']));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    echo $result['content'];
    exit;
  } catch (Exception $e) {
    // Log error and show user-friendly message
    error_log('PDF Export Error: ' . $e->getMessage());

    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: text/html');

    echo '<!DOCTYPE html><html><head><title>Export Error</title></head><body>';
    echo '<h1>PDF Export Error</h1>';
    echo '<p>Unable to generate PDF export. Please try again later.</p>';
    echo '<p><a href="javascript:history.back()">Go Back</a></p>';
    echo '</body></html>';
    exit;
  }
}

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
$pageHeadings = [];
$pageModified = null;

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

  // Extract headings for table of contents and get modification time
  if ($pageContent !== null) {
    $pageHeadings = $wiki->getPageHeadings($currentPath);
    $pageModified = $wiki->getPageModified($currentPath);
  }

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