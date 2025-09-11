<?php

/**
 * Live Search API Endpoint
 * Handles AJAX requests for real-time search functionality
 */

// Load configuration and classes
require_once __DIR__ . '/config.php';
require_once CLASSES_DIR . '/MarkdownParser.php';

// Set JSON response headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// CORS headers if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Debug information collector
$debugInfo = [];

try {
  // Get search query
  $query = trim($_GET['q'] ?? '');
  $debugInfo[] = "Search query: '$query'";

  if (empty($query) || strlen($query) < 2) {
    echo json_encode([
      'success' => true,
      'results' => [],
      'query' => $query,
      'total' => 0,
      'message' => 'Query too short',
      'debug' => DEBUG_MODE ? $debugInfo : null
    ]);
    exit;
  }

  // Perform simple content search
  $results = searchAllFiles($query, $debugInfo);

  $debugInfo[] = "Final results count: " . count($results);

  // Response
  $response = [
    'success' => true,
    'results' => $results,
    'query' => $query,
    'total' => count($results),
    'cached' => false
  ];

  if (DEBUG_MODE) {
    $response['debug'] = $debugInfo;
  }

  echo json_encode($response);
} catch (Exception $e) {
  $debugInfo[] = 'ERROR: ' . $e->getMessage();

  error_log('Simple Search API Error: ' . $e->getMessage());

  http_response_code(500);
  echo json_encode([
    'success' => false,
    'error' => DEBUG_MODE ? $e->getMessage() : 'Search temporarily unavailable',
    'query' => $query ?? '',
    'results' => [],
    'debug' => DEBUG_MODE ? $debugInfo : null
  ]);
}

/**
 * Simple function to search all markdown files for content
 */
function searchAllFiles($query, &$debugInfo)
{
  $results = [];
  $contentDir = CONTENT_DIR;

  $debugInfo[] = "Starting search in: $contentDir";

  // Search recursively
  searchInDir($contentDir, '', $query, $results, $debugInfo);

  // Limit results
  $results = array_slice($results, 0, 20);

  return $results;
}

/**
 * Recursive function to search in directory
 */
function searchInDir($dir, $relativePath, $query, &$results, &$debugInfo)
{
  $debugInfo[] = "Searching directory: $dir";

  if (!is_dir($dir)) {
    $debugInfo[] = "Not a directory: $dir";
    return;
  }

  $files = scandir($dir);
  $mdFiles = [];
  $subDirs = [];

  foreach ($files as $file) {
    if ($file === '.' || $file === '..' || $file[0] === '.') {
      continue;
    }

    $fullPath = $dir . '/' . $file;

    if (is_dir($fullPath)) {
      $subDirs[] = $file;
    } elseif (pathinfo($file, PATHINFO_EXTENSION) === 'md') {
      $mdFiles[] = $file;
    }
  }

  $debugInfo[] = "Found " . count($mdFiles) . " markdown files: " . implode(', ', $mdFiles);
  $debugInfo[] = "Found " . count($subDirs) . " subdirectories: " . implode(', ', $subDirs);

  // Search markdown files
  foreach ($mdFiles as $file) {
    $fullPath = $dir . '/' . $file;
    $fileRelativePath = $relativePath ? $relativePath . '/' . $file : $file;

    searchInFile($fullPath, $fileRelativePath, $query, $results, $debugInfo);
  }

  // Search subdirectories
  foreach ($subDirs as $subDir) {
    $fullPath = $dir . '/' . $subDir;
    $subRelativePath = $relativePath ? $relativePath . '/' . $subDir : $subDir;

    searchInDir($fullPath, $subRelativePath, $query, $results, $debugInfo);
  }
}

/**
 * Search within a specific file content
 */
function searchInFile($filePath, $relativePath, $query, &$results, &$debugInfo)
{
  $debugInfo[] = "Checking file: $filePath";

  if (!file_exists($filePath) || !is_readable($filePath)) {
    $debugInfo[] = "File not readable: $filePath";
    return;
  }

  $content = file_get_contents($filePath);
  $contentLength = strlen($content);

  $debugInfo[] = "File size: $contentLength bytes";

  if ($contentLength === 0) {
    $debugInfo[] = "File is empty: $filePath";
    return;
  }

  // Search for query in content (case-insensitive)
  if (stripos($content, $query) === false) {
    $debugInfo[] = "No match found in: $filePath";
    return;
  }

  $debugInfo[] = "MATCH FOUND in: $filePath";

  // Extract file info
  $fileName = pathinfo($relativePath, PATHINFO_FILENAME);

  // Create page path for URL
  $pagePath = createPagePath($relativePath);

  // Extract title from content
  $title = extractTitle($content, $fileName);

  // Create snippet around the match
  $snippet = createSnippet($content, $query);

  $result = [
    'title' => $title,
    'path' => $pagePath,
    'snippet' => $snippet,
    'url' => '?page=' . urlencode($pagePath),
    'file' => $relativePath
  ];

  $results[] = $result;
  $debugInfo[] = "Added result: " . json_encode($result);
}

/**
 * Create page path from file path
 */
function createPagePath($relativePath)
{
  // Remove .md extension
  $path = preg_replace('/\.md$/', '', $relativePath);

  // Handle index files
  if (basename($path) === 'index') {
    $path = dirname($path);
    if ($path === '.') {
      $path = '';
    }
  }

  return $path;
}

/**
 * Extract title from markdown content
 */
function extractTitle($content, $fallback)
{
  // Look for first H1 heading
  if (preg_match('/^# (.+)$/m', $content, $matches)) {
    return trim($matches[1]);
  }

  // Look for any heading
  if (preg_match('/^#{1,6} (.+)$/m', $content, $matches)) {
    return trim($matches[1]);
  }

  // Fallback to filename
  return ucwords(str_replace(['-', '_'], ' ', $fallback));
}

/**
 * Create snippet around search match
 */
function createSnippet($content, $query)
{
  $pos = stripos($content, $query);
  if ($pos === false) {
    return substr($content, 0, 150) . '...';
  }

  // Get context around the match
  $start = max(0, $pos - 75);
  $length = 150;
  $snippet = substr($content, $start, $length);

  // Clean up markdown formatting
  $snippet = preg_replace('/[#*`\[\]()]/', '', $snippet);
  $snippet = preg_replace('/\s+/', ' ', $snippet);
  $snippet = trim($snippet);

  // Add ellipsis if needed
  if ($start > 0) {
    $snippet = '...' . $snippet;
  }
  if (strlen($content) > $start + $length) {
    $snippet = $snippet . '...';
  }

  // Highlight the search term (basic highlighting)
  $snippet = str_ireplace($query, '<mark>' . htmlspecialchars($query) . '</mark>', htmlspecialchars($snippet));

  return $snippet;
}
