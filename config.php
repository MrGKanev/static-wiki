<?php

/**
 * Configuration file for Company Wiki
 */

// Basic configuration
define('WIKI_TITLE', 'Company Wiki');
define('CONTENT_DIR', __DIR__ . '/content');
define('CACHE_DIR', __DIR__ . '/cache');

// Set LOGO_IMAGE to a path relative to the assets folder, or leave empty for text logo
define('LOGO_IMAGE', ''); // Example: 'images/logo.png' or 'images/logo.svg'
define('LOGO_ALT', 'Company Logo'); // Alt text for the logo image
define('LOGO_HEIGHT', '32'); // Height in pixels for the logo image

// Application settings
define('ITEMS_PER_PAGE', 10);
define('SEARCH_SNIPPET_LENGTH', 200);
define('DEBUG_MODE', true);

// Cache settings
define('ENABLE_CACHE', false);
define('CACHE_TTL', 3600);           // 1 hour default TTL
define('NAVIGATION_CACHE_TTL', 7200); // 2 hours for navigation
define('CONTENT_CACHE_TTL', 1800);    // 30 minutes for content
define('SEARCH_CACHE_TTL', 600);      // 10 minutes for search results

// Security settings
define('ALLOWED_FILE_EXTENSIONS', ['md']);
define('MAX_SEARCH_RESULTS', 50);

// GitHub Integration Settings
define('GITHUB_REPO_URL', 'https://github.com/MrGKanev/static-wiki');
define('GITHUB_BRANCH', 'main'); // or 'master' depending on your default branch
define('GITHUB_EDIT_ENABLED', true);

// Feedback Settings
define('FEEDBACK_ENABLED', true);
define('FEEDBACK_TYPE', 'email'); // Options: 'email', 'url', 'github_issues'
define('FEEDBACK_EMAIL', 'feedback@yourcompany.com');
define('FEEDBACK_URL', ''); // Custom feedback URL if using 'url' type
define('FEEDBACK_GITHUB_ISSUES_URL', ''); // GitHub issues URL if using 'github_issues' type

// Paths
define('CLASSES_DIR', __DIR__ . '/classes');
define('TEMPLATES_DIR', __DIR__ . '/templates');
define('ASSETS_DIR', __DIR__ . '/assets');
define('INCLUDES_DIR', __DIR__ . '/includes');

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
