<?php
// Load template helper functions
require_once INCLUDES_DIR . '/template-functions.php';

// Helper function to check if logo should be displayed
function shouldShowLogo()
{
  if (!defined('LOGO_IMAGE') || empty(LOGO_IMAGE)) {
    return false;
  }

  // If it's a URL (starts with http:// or https://), always show it
  if (strpos(LOGO_IMAGE, 'http://') === 0 || strpos(LOGO_IMAGE, 'https://') === 0) {
    return true;
  }

  // If it's a local file, check if it exists
  return file_exists(ASSETS_DIR . '/' . LOGO_IMAGE);
}

// Helper function to get logo src
function getLogoSrc()
{
  if (!defined('LOGO_IMAGE') || empty(LOGO_IMAGE)) {
    return '';
  }

  // If it's a URL, return as-is
  if (strpos(LOGO_IMAGE, 'http://') === 0 || strpos(LOGO_IMAGE, 'https://') === 0) {
    return LOGO_IMAGE;
  }

  // If it's a local file, prepend assets/
  return 'assets/' . LOGO_IMAGE;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="system">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($pageTitle ?? WIKI_TITLE); ?></title>

  <!-- Core CSS Files -->
  <link rel="stylesheet" href="assets/css/base.css">
  <link rel="stylesheet" href="assets/css/header.css">
  <link rel="stylesheet" href="assets/css/layout.css">
  <link rel="stylesheet" href="assets/css/navigation.css">
  <link rel="stylesheet" href="assets/css/content.css">
  <link rel="stylesheet" href="assets/css/components.css">
  <link rel="stylesheet" href="assets/css/responsive.css">
  <link rel="stylesheet" href="assets/css/print.css">

  <!-- Feature-specific CSS -->
  <link rel="stylesheet" href="assets/css/live-search.css">
  <link rel="stylesheet" href="assets/css/markdown.css">
  <link rel="stylesheet" href="assets/css/page-actions.css">
  <link rel="stylesheet" href="assets/css/dark-mode.css">

  <meta name="description" content="<?php echo htmlspecialchars($pageTitle ?? 'Company Wiki'); ?>">
</head>

<body>
  <!-- Top Header -->
  <header class="top-header">
    <div class="header-content">
      <div class="header-left">
        <!-- Mobile menu toggle -->
        <button class="mobile-menu-toggle" onclick="toggleMobileMenu()" aria-label="Toggle navigation menu">
          <span>☰</span>
        </button>

        <h1 class="wiki-title">
          <a href="?" aria-label="Home - Return to main page" title="Go to Home">
            <?php if (shouldShowLogo()): ?>
              <!-- Image Logo (Local or URL) -->
              <img
                src="<?php echo htmlspecialchars(getLogoSrc()); ?>"
                alt="<?php echo htmlspecialchars(defined('LOGO_ALT') ? LOGO_ALT : WIKI_TITLE); ?>"
                class="wiki-logo-image"
                height="<?php echo htmlspecialchars(defined('LOGO_HEIGHT') ? LOGO_HEIGHT : '32'); ?>"
                loading="lazy"
                onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';">
              <span class="wiki-title-text" style="<?php echo shouldShowLogo() ? 'display: none;' : ''; ?>">
                <?php echo htmlspecialchars(WIKI_TITLE); ?>
              </span>
            <?php else: ?>
              <!-- Text Logo (fallback) -->
              <span class="wiki-title-text"><?php echo htmlspecialchars(WIKI_TITLE); ?></span>
            <?php endif; ?>
          </a>
        </h1>


      </div>

      <div class="header-right">
        <!-- Search Form -->
        <form class="search-form" method="GET" role="search">
          <div class="search-container">
            <input type="hidden" name="search" value="1">
            <input
              type="text"
              name="q"
              placeholder="Search documentation... (/)"
              value="<?php echo htmlspecialchars($searchQuery ?? ''); ?>"
              aria-label="Search the wiki"
              class="search-input"
              autocomplete="off">
            <button type="submit" aria-label="Submit search" class="search-button">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                <path d="m21 21-6-6m2-5a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </button>
          </div>
        </form>

        <!-- Keyboard shortcut indicator -->
        <button class="help-indicator" onclick="wikiShortcuts?.showHelp()" aria-label="Show keyboard shortcuts" title="Press ? for shortcuts">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" />
            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            <line x1="12" y1="17" x2="12.01" y2="17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </button>
      </div>
    </div>
  </header>

  <!-- Main Layout Container -->
  <div class="layout-container">
    <!-- Left Sidebar - Navigation with Theme Toggle -->
    <aside class="left-sidebar">
      <div class="navigation-container">
        <nav class="navigation" role="navigation" aria-label="Main navigation">
          <?php if (!empty($navigation)): ?>
            <?php echo renderNavigation($navigation, 0, $currentPath); ?>
          <?php else: ?>
            <div class="empty-message">
              <p>No content found.</p>
              <p>Add markdown files to the content directory to get started.</p>
            </div>
          <?php endif; ?>
        </nav>

        <!-- Theme Toggle -->
        <?php echo renderThemeToggle(); ?>
      </div>
    </aside>

    <!-- Main Content Area -->
    <main class="main-content" role="main">
      <?php echo renderCacheFeedback(); ?>

      <?php
      // Always show breadcrumbs
      if (!empty($currentPath)) {
        // Get full breadcrumbs for non-home pages
        $breadcrumbs = $wiki->getBreadcrumbs($currentPath);
      } else {
        // Show just "Home" for the home page
        $breadcrumbs = [['name' => 'Home', 'path' => '']];
      }

      if (!empty($breadcrumbs)) {
        echo renderBreadcrumbs($breadcrumbs, $currentPath);
      }
      ?>

      <div class="content">
        <?php echo $content; ?>
      </div>

      <?php echo renderDebugInfo($wiki, $currentPath, $pageTitle); ?>
    </main>

    <!-- Right Sidebar - Table of Contents -->
    <aside class="right-sidebar">
      <?php if (!$isSearch && !empty($pageHeadings)): ?>
        <div class="toc-container">
          <h3 class="toc-title">
            On this page
          </h3>
          <nav class="table-of-contents" aria-label="Table of contents">
            <?php echo renderTableOfContents($pageHeadings); ?>
          </nav>
        </div>
      <?php endif; ?>

      <!-- PDF Export Actions -->
      <?php if (!$isSearch && $pageContent !== null): ?>
        <div class="export-actions">
          <h4>Export Options</h4>
          <div class="export-buttons">
            <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'pdf', 'type' => 'page'])); ?>"
              class="page-action-button"
              title="Export current page to PDF (shortcut: e)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                <polyline points="14,2 14,8 20,8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                <line x1="16" y1="13" x2="8" y2="13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                <line x1="16" y1="17" x2="8" y2="17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                <polyline points="10,9 9,9 8,9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
              Export Page
            </a>
            <button onclick="window.print()"
              class="page-action-button"
              title="Print page (shortcut: p)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                <polyline points="6,9 6,2 18,2 18,9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                <polyline points="6,14 6,22 18,22 18,14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
              Print Page
            </button>
          </div>
        </div>
      <?php endif; ?>

      <!-- Page Actions -->
      <?php if (!$isSearch && $pageContent !== null): ?>
        <?php echo renderPageActions($currentPath, $pageModified); ?>
      <?php endif; ?>
    </aside>
  </div>

  <!-- JavaScript -->
  <script src="assets/js/layout.js"></script>
  <script src="assets/js/keyboard-shortcuts.js"></script>
  <script src="assets/js/theme-toggle.js"></script>
  <script src="assets/js/live-search.js"></script>
</body>

</html>