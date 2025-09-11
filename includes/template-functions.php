<?php

/**
 * Template Helper Functions
 * Contains reusable functions for rendering navigation and content
 * CLEAN VERSION - No debug paths, supports 4+ levels, all features restored
 */

/**
 * Render navigation tree recursively with collapsible categories
 * SUPPORTS UNLIMITED NESTING LEVELS
 *
 * @param array $items Navigation items array
 * @param int $level Current nesting level
 * @param string $currentPath Current page path for highlighting
 * @return string HTML output
 */
function renderNavigation($items, $level = 0, $currentPath = '')
{
  if (empty($items)) {
    return '';
  }

  $output = '<ul class="nav-list' . ($level === 0 ? ' nav-root' : '') . '">';

  foreach ($items as $item) {
    $isCurrentPage = false;
    $hasCurrentPageInChildren = false;

    // Check if this is the current page or contains the current page
    if ($item['type'] === 'page') {
      $isCurrentPage = ($item['path'] === $currentPath);
    } elseif ($item['type'] === 'category') {
      $hasCurrentPageInChildren = checkIfContainsCurrentPage($item, $currentPath);
    }

    $output .= '<li class="nav-item' . ($isCurrentPage ? ' current-page' : '') . '">';

    if ($item['type'] === 'category') {
      // Enhanced expansion logic - always expand important categories AND first 2 levels
      $shouldExpand = $hasCurrentPageInChildren ||
        $level <= 1 || // Always expand first 2 levels
        in_array(strtolower($item['name']), ['implementation', 'z1', 'z2']);

      $collapseClass = $shouldExpand ? '' : ' collapsed';

      $output .= '<div class="nav-category' . ($hasCurrentPageInChildren ? ' active' : '') . $collapseClass . '" ';
      $output .= 'data-category="' . htmlspecialchars($item['name']) . '" ';
      $output .= 'onclick="toggleCategory(this)" ';
      $output .= 'tabindex="0" ';
      $output .= 'role="button" ';
      $output .= 'aria-expanded="' . ($shouldExpand ? 'true' : 'false') . '">';
      $output .= htmlspecialchars($item['name']);
      $output .= '</div>';

      if (!empty($item['children'])) {
        $childrenClass = $shouldExpand ? '' : ' collapsed';
        // RECURSIVE CALL - SUPPORTS UNLIMITED LEVELS
        $childrenOutput = renderNavigation($item['children'], $level + 1, $currentPath);
        $output .= '<div class="nav-children' . $childrenClass . '">' . $childrenOutput . '</div>';
      }
    } else {
      // CLEAN page link generation - NO DEBUG INFO
      $isExternal = strpos($item['path'], 'http') === 0 || strpos($item['path'], '//') === 0;
      $externalClass = $isExternal ? ' external' : '';
      $activeClass = $isCurrentPage ? ' active' : '';

      $target = $isExternal ? ' target="_blank" rel="noopener"' : '';
      $href = $isExternal ? $item['path'] : '?page=' . urlencode($item['path']);

      $output .= '<a href="' . htmlspecialchars($href) . '"' . $target . ' class="nav-link' . $activeClass . $externalClass . '">';
      $output .= htmlspecialchars($item['name']);
      $output .= '</a>';
    }

    $output .= '</li>';
  }

  $output .= '</ul>';
  return $output;
}

/**
 * Check if a category contains the current page (recursively)
 * SUPPORTS UNLIMITED NESTING LEVELS
 *
 * @param array $category Category item to check
 * @param string $currentPath Current page path
 * @return bool Whether category contains current page
 */
function checkIfContainsCurrentPage($category, $currentPath)
{
  if (empty($category['children']) || empty($currentPath)) {
    return false;
  }

  foreach ($category['children'] as $child) {
    if ($child['type'] === 'page' && $child['path'] === $currentPath) {
      return true;
    } elseif ($child['type'] === 'category') {
      // RECURSIVE CHECK - SUPPORTS UNLIMITED LEVELS
      if (checkIfContainsCurrentPage($child, $currentPath)) {
        return true;
      }
    }
  }

  return false;
}

/**
 * Render table of contents from headings
 *
 * @param array $headings Array of heading data with level, text, and id
 * @return string HTML output
 */
function renderTableOfContents($headings)
{
  if (empty($headings)) {
    return '';
  }

  $output = '<ul class="toc-list">';

  foreach ($headings as $heading) {
    $level = $heading['level'];
    $id = $heading['id'];
    $text = $heading['text'];

    $output .= '<li class="toc-item toc-level-' . $level . '">';
    $output .= '<a href="#' . htmlspecialchars($id) . '" class="toc-link">';
    $output .= htmlspecialchars($text);
    $output .= '</a>';
    $output .= '</li>';
  }

  $output .= '</ul>';
  return $output;
}

/**
 * Render breadcrumb navigation
 *
 * @param array $breadcrumbs Array of breadcrumb items
 * @param string $currentPath Current page path
 * @return string HTML output
 */
function renderBreadcrumbs($breadcrumbs, $currentPath = '')
{
  if (empty($breadcrumbs)) {
    return '';
  }

  $output = '<nav class="breadcrumb" aria-label="Breadcrumb navigation">';

  foreach ($breadcrumbs as $index => $crumb) {
    if ($index > 0) {
      $output .= '<span class="separator" aria-hidden="true">&gt;</span>';
    }

    if ($index === count($breadcrumbs) - 1) {
      // Current page - no link
      $output .= '<span aria-current="page">' . htmlspecialchars($crumb['name']) . '</span>';
    } else {
      // Linked breadcrumb
      $href = empty($crumb['path']) ? '?' : '?page=' . urlencode($crumb['path']);
      $output .= '<a href="' . $href . '">' . htmlspecialchars($crumb['name']) . '</a>';
    }
  }

  $output .= '</nav>';
  return $output;
}

/**
 * Render cache feedback messages for debug mode
 *
 * @return string HTML output
 */
function renderCacheFeedback()
{
  $output = '';

  // Show cache action feedback if in debug mode
  if (defined('DEBUG_MODE') && DEBUG_MODE) {
    if (isset($_GET['cache_cleared'])) {
      $output .= '<div class="cache-feedback">
                    Cache cleared successfully! Removed ' . (int)$_GET['cache_cleared'] . ' entries.
                  </div>';
    }
    if (isset($_GET['cache_cleaned'])) {
      $output .= '<div class="cache-feedback warning">
                    Cache cleanup completed! Removed ' . (int)$_GET['cache_cleaned'] . ' expired entries.
                  </div>';
    }
  }

  return $output;
}

/**
 * Render debug information panel (ONLY shows when DEBUG_MODE is true)
 *
 * @param Wiki $wiki Wiki instance
 * @param string $currentPath Current page path
 * @param string $pageTitle Page title
 * @return string HTML output
 */
function renderDebugInfo($wiki, $currentPath, $pageTitle)
{
  if (!defined('DEBUG_MODE') || !DEBUG_MODE) {
    return '';
  }

  $output = '<div class="debug-info">';
  $output .= '<strong>Debug Info:</strong><br>';
  $output .= 'Current Path: ' . htmlspecialchars($currentPath ?: 'index') . '<br>';
  $output .= 'Page Title: ' . htmlspecialchars($pageTitle) . '<br>';

  if ($wiki->isCacheEnabled()) {
    $cacheStats = $wiki->getCacheStats();
    $output .= '<strong>Cache Stats:</strong><br>';
    $output .= 'Enabled: Yes | ';
    $output .= 'Total Entries: ' . $cacheStats['total'] . ' | ';
    $output .= 'Valid: ' . ($cacheStats['valid'] ?? 'N/A') . ' | ';
    $output .= 'Expired: ' . $cacheStats['expired'] . ' | ';
    $output .= 'Size: ' . ($cacheStats['size_human'] ?? 'N/A') . '<br>';
    $output .= '<a href="?action=clear_cache" style="color: #e74c3c; text-decoration: none; font-size: 11px;">[Clear Cache]</a>';
    $output .= '<a href="?action=cleanup_cache" style="color: #f39c12; text-decoration: none; font-size: 11px; margin-left: 10px;">[Cleanup Expired]</a>';
  } else {
    $output .= '<strong>Cache:</strong> Disabled';
  }

  $output .= '</div>';
  return $output;
}

/**
 * Generate GitHub edit URL for current page
 *
 * @param string $currentPath Current page path
 * @return string GitHub edit URL
 */
function getGitHubEditUrl($currentPath)
{
  if (!defined('GITHUB_REPO_URL') || !defined('GITHUB_BRANCH') || !GITHUB_EDIT_ENABLED) {
    return '';
  }

  // Convert page path to file path
  $filePath = 'content/';
  if (empty($currentPath)) {
    $filePath .= 'index.md';
  } else {
    $filePath .= $currentPath . '.md';
  }

  return GITHUB_REPO_URL . '/edit/' . GITHUB_BRANCH . '/' . $filePath;
}

/**
 * Render page action buttons (Contents, Feedback, GitHub Edit)
 * Export functionality removed - handled by dedicated Export Options section
 *
 * @param string $currentPath Current page path
 * @param array $pageHeadings Page headings for table of contents
 * @return string HTML output
 */
function renderPageActions($currentPath, $pageHeadings = [])
{
  $output = '<div class="page-actions-container">';
  $output .= '<div class="page-actions">';

  // Table of contents toggle
  if (!empty($pageHeadings)) {
    $output .= '<button class="page-action-button toc-toggle" onclick="toggleTableOfContents()" title="Toggle table of contents">';
    $output .= '<svg width="14" height="14" viewBox="0 0 24 24" fill="none">';
    $output .= '<line x1="8" y1="6" x2="21" y2="6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
    $output .= '<line x1="8" y1="12" x2="21" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
    $output .= '<line x1="8" y1="18" x2="21" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
    $output .= '<line x1="3" y1="6" x2="3.01" y2="6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
    $output .= '<line x1="3" y1="12" x2="3.01" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
    $output .= '<line x1="3" y1="18" x2="3.01" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
    $output .= '</svg>';
    $output .= 'Contents';
    $output .= '</button>';
  }

  // Feedback and GitHub links
  $feedbackUrl = getFeedbackUrl();
  $gitHubUrl = getGitHubEditUrl($currentPath);

  if ($feedbackUrl) {
    $output .= '<a href="' . htmlspecialchars($feedbackUrl) . '" class="page-action-button feedback-button" target="_blank" rel="noopener">';
    $output .= '<svg width="14" height="14" viewBox="0 0 24 24" fill="none">';
    $output .= '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
    $output .= '</svg>';
    $output .= 'Give us feedback';
    $output .= '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" style="margin-left: 4px;">';
    $output .= '<path d="M7 17l9.2-9.2M17 17V7H7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
    $output .= '</svg>';
    $output .= '</a>';
  }

  if ($gitHubUrl) {
    $output .= '<a href="' . htmlspecialchars($gitHubUrl) . '" class="page-action-button github-button" target="_blank" rel="noopener">';
    $output .= '<svg width="14" height="14" viewBox="0 0 24 24" fill="none">';
    $output .= '<path d="M12 2C6.477 2 2 6.477 2 12c0 4.418 2.865 8.166 6.839 9.489.5.092.682-.217.682-.482 0-.237-.008-.866-.013-1.7-2.782.604-3.369-1.34-3.369-1.34-.454-1.156-1.11-1.464-1.11-1.464-.908-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.087 2.91.832.092-.647.35-1.088.636-1.338-2.22-.252-4.555-1.11-4.555-4.943 0-1.091.39-1.984 1.029-2.683-.103-.253-.446-1.27.098-2.647 0 0 .84-.269 2.75 1.025A9.564 9.564 0 0 1 12 6.844c.85.004 1.705.115 2.504.337 1.909-1.294 2.747-1.025 2.747-1.025.546 1.377.203 2.394.1 2.647.64.699 1.028 1.592 1.028 2.683 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.852 0 1.336-.012 2.415-.012 2.743 0 .267.18.579.688.481C19.138 20.161 22 16.416 22 12c0-5.523-4.477-10-10-10z" fill="currentColor"/>';
    $output .= '</svg>';
    $output .= 'Edit this page on GitHub';
    $output .= '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" style="margin-left: 4px;">';
    $output .= '<path d="M7 17l9.2-9.2M17 17V7H7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
    $output .= '</svg>';
    $output .= '</a>';
  }

  $output .= '</div>';
  $output .= '</div>';

  return $output;
}

/**
 * Generate feedback URL based on configuration
 *
 * @return string Feedback URL
 */
function getFeedbackUrl()
{
  // Get current page info for feedback context
  $currentPath = $_GET['page'] ?? '';
  $pageTitle = ''; // Will be populated by calling context
  $currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

  if (!defined('FEEDBACK_TYPE') || empty(FEEDBACK_TYPE)) {
    return '';
  }

  switch (FEEDBACK_TYPE) {
    case 'email':
      if (defined('FEEDBACK_EMAIL') && !empty(FEEDBACK_EMAIL)) {
        $subject = urlencode('Wiki Feedback: ' . ($pageTitle ?: 'Home'));
        $body = urlencode("Page: " . ($pageTitle ?: 'Home') . "\nURL: " . $currentUrl . "\n\nFeedback:\n\n");
        return 'mailto:' . FEEDBACK_EMAIL . '?subject=' . $subject . '&body=' . $body;
      }
      break;
    case 'form':
      if (defined('FEEDBACK_FORM_URL') && !empty(FEEDBACK_FORM_URL)) {
        return FEEDBACK_FORM_URL . '?' . http_build_query([
          'page' => $currentPath,
          'title' => $pageTitle,
          'url' => $currentUrl
        ]);
      }
      break;
    case 'github_issues':
      if (defined('FEEDBACK_GITHUB_ISSUES_URL') && !empty(FEEDBACK_GITHUB_ISSUES_URL)) {
        $title = urlencode('Feedback: ' . ($pageTitle ?: 'Home'));
        $body = urlencode("**Page:** " . ($pageTitle ?: 'Home') . "\n**URL:** " . $currentUrl . "\n\n**Feedback:**\n\n");
        return FEEDBACK_GITHUB_ISSUES_URL . '/new?title=' . $title . '&body=' . $body;
      }
      break;
  }

  return '';
}

/**
 * Render theme toggle component for dark mode
 *
 * @return string HTML output
 */
function renderThemeToggle()
{
  $output = '<div class="theme-toggle">';
  $output .= '<button class="theme-toggle-button" onclick="toggleTheme()" title="Toggle theme">';
  $output .= '<div class="theme-toggle-content">';
  $output .= '<div class="theme-icon">';
  // Default system icon - will be updated by JavaScript
  $output .= '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">';
  $output .= '<rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>';
  $output .= '<line x1="8" y1="21" x2="16" y2="21"/>';
  $output .= '<line x1="12" y1="17" x2="12" y2="21"/>';
  $output .= '</svg>';
  $output .= '</div>';
  $output .= '<span class="theme-text">System</span>';
  $output .= '</div>';
  $output .= '<div class="theme-indicator">';
  $output .= '<div class="theme-indicator-dot"></div>';
  $output .= '</div>';
  $output .= '</button>';
  $output .= '</div>';

  return $output;
}
