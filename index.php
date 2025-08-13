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
require_once CLASSES_DIR . '/Wiki.php';

// Initialize the wiki
$wiki = new Wiki();

// Get current state
$currentPath = $wiki->getCurrentPath();
$isSearch = isset($_GET['search']);
$searchQuery = trim($_GET['q'] ?? '');

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
