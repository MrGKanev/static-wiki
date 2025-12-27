<?php

declare(strict_types=1);

/**
 * Live Search API Endpoint
 * Handles AJAX requests for real-time search functionality
 */

// Load configuration and classes
require_once __DIR__ . '/config.php';

// Load classes (check for autoloader first)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
  require_once __DIR__ . '/vendor/autoload.php';
} else {
  // Fallback to manual loading
  require_once CLASSES_DIR . '/Interfaces/CacheInterface.php';
  require_once CLASSES_DIR . '/Interfaces/MarkdownParserInterface.php';
  require_once CLASSES_DIR . '/MarkdownParser.php';
  require_once CLASSES_DIR . '/Cache.php';
  require_once CLASSES_DIR . '/Wiki.php';
}

use Wiki\Cache;
use Wiki\Wiki;

// Set JSON response headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// CORS headers if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

try {
  // Get search query
  $query = trim($_GET['q'] ?? '');

  if (empty($query) || strlen($query) < 2) {
    echo json_encode([
      'success' => true,
      'results' => [],
      'query' => $query,
      'total' => 0,
      'message' => 'Query too short'
    ]);
    exit;
  }

  // Initialize cache if enabled
  $cache = null;
  if (ENABLE_CACHE) {
    $cache = new Cache();
  }

  // Initialize wiki and perform search using Wiki::search()
  $wiki = new Wiki(null, $cache);
  $results = $wiki->search($query);

  // Add URL to each result for consistency with old API
  foreach ($results as &$result) {
    $result['url'] = '?page=' . urlencode($result['path']);
  }

  // Response
  $response = [
    'success' => true,
    'results' => $results,
    'query' => $query,
    'total' => count($results),
    'cached' => ENABLE_CACHE
  ];

  echo json_encode($response);
} catch (Exception $e) {
  error_log('Search API Error: ' . $e->getMessage());

  http_response_code(500);
  echo json_encode([
    'success' => false,
    'error' => DEBUG_MODE ? $e->getMessage() : 'Search temporarily unavailable',
    'query' => $query ?? '',
    'results' => []
  ]);
}
