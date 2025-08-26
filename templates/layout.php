<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($pageTitle ?? WIKI_TITLE); ?></title>
  <link rel="stylesheet" href="assets/style.css">
  <meta name="description" content="<?php echo htmlspecialchars($pageTitle ?? 'Company Wiki'); ?>">
</head>

<body>
  <!-- Top Header -->
  <header class="top-header">
    <div class="header-content">
      <div class="header-left">
        <!-- Mobile menu toggle -->
        <button class="mobile-menu-toggle" onclick="wikiShortcuts?.toggleMobileMenu()" aria-label="Toggle navigation menu">
          <span>☰</span>
        </button>

        <h1 class="wiki-title">
          <a href="?" aria-label="Home - Return to main page" title="Go to Home">
            <?php echo htmlspecialchars(WIKI_TITLE); ?>
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
              class="search-input">
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
    <!-- Left Sidebar - Navigation -->
    <aside class="left-sidebar">
      <nav class="navigation" role="navigation" aria-label="Main navigation">
        <?php if (!empty($navigation)): ?>
          <?php echo renderNavigation($navigation); ?>
        <?php else: ?>
          <div class="empty-message">
            <p>No content found.</p>
            <p>Add markdown files to the content directory to get started.</p>
          </div>
        <?php endif; ?>
      </nav>
    </aside>

    <!-- Main Content Area -->
    <main class="main-content" role="main">
      <?php echo $content; ?>
    </main>

    <!-- Right Sidebar - Table of Contents -->
    <aside class="right-sidebar">
      <?php if (!$isSearch && !empty($pageHeadings)): ?>
        <div class="toc-container">
          <h3 class="toc-title">
            On this page
            <button class="toc-toggle" onclick="wikiShortcuts?.toggleTOC()" title="Toggle TOC (shortcut: t)">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                <path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </button>
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
              class="export-button"
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

            <?php if (!empty($currentPath)): ?>
              <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'pdf', 'type' => 'section'])); ?>"
                class="export-button secondary"
                title="Export entire section to PDF (shortcut: E)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                  <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                  <polyline points="14,2 14,8 20,8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                  <line x1="12" y1="11" x2="8" y2="15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                  <line x1="8" y1="11" x2="12" y2="15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                Export Section
              </a>
            <?php endif; ?>

            <button onclick="window.print()"
              class="export-button secondary"
              title="Print page (shortcut: p)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                <polyline points="6,9 6,2 18,2 18,9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                <rect x="6" y="14" width="12" height="8" rx="1" ry="1" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
              Print
            </button>
          </div>
        </div>
      <?php endif; ?>
    </aside>
  </div>

  <!-- Keyboard Shortcuts JavaScript -->
  <script>
    <?php include __DIR__ . '/../assets/keyboard-shortcuts.js'; ?>
  </script>

  <!-- Existing functionality scripts -->
  <script>
    // Mobile menu functionality
    function toggleMobileMenu() {
      const sidebar = document.querySelector('.left-sidebar');
      sidebar.classList.toggle('mobile-open');
    }

    // Close mobile menu when clicking outside
    document.addEventListener('click', function(event) {
      const sidebar = document.querySelector('.left-sidebar');
      const isClickInsideSidebar = sidebar.contains(event.target);
      const isMenuToggle = event.target.closest('.mobile-menu-toggle');

      if (window.innerWidth <= 768 && !isClickInsideSidebar && !isMenuToggle && sidebar.classList.contains('mobile-open')) {
        sidebar.classList.remove('mobile-open');
      }
    });

    // Handle window resize
    window.addEventListener('resize', function() {
      const sidebar = document.querySelector('.left-sidebar');
      if (window.innerWidth > 768) {
        sidebar.classList.remove('mobile-open');
      }
    });

    // Smooth scrolling for table of contents links
    document.querySelectorAll('.table-of-contents a').forEach(anchor => {
      anchor.addEventListener('click', function(e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
          target.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
          });
        }
      });
    });

    // Highlight current section in table of contents
    function updateTocHighlight() {
      const headings = document.querySelectorAll('h1, h2, h3, h4, h5, h6');
      const tocLinks = document.querySelectorAll('.table-of-contents a');

      let current = '';
      headings.forEach(heading => {
        const rect = heading.getBoundingClientRect();
        if (rect.top <= 100) {
          current = '#' + heading.id;
        }
      });

      tocLinks.forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('href') === current) {
          link.classList.add('active');
        }
      });
    }

    // Update TOC highlight on scroll
    window.addEventListener('scroll', updateTocHighlight);
    // Initial highlight
    document.addEventListener('DOMContentLoaded', updateTocHighlight);

    // Page change detection
    let lastUrl = location.href;
    new MutationObserver(() => {
      const url = location.href;
      if (url !== lastUrl) {
        lastUrl = url;
        // Page changed - could trigger analytics or other functions here
        console.log('Page changed to:', url);

        // Update keyboard shortcuts navigation links
        if (window.wikiShortcuts) {
          window.wikiShortcuts.updateNavigableLinks();
        }
      }
    }).observe(document, {
      subtree: true,
      childList: true
    });
  </script>
</body>

</html>

<?php
/**
 * Render navigation tree recursively
 */
function renderNavigation($items, $level = 0)
{
  if (empty($items)) {
    return '';
  }

  $output = '<ul class="nav-list' . ($level === 0 ? ' nav-root' : '') . '">';

  foreach ($items as $item) {
    $output .= '<li class="nav-item">';

    if ($item['type'] === 'category') {
      $output .= '<div class="nav-category">' . htmlspecialchars($item['name']) . '</div>';

      if (!empty($item['children'])) {
        $output .= renderNavigation($item['children'], $level + 1);
      }
    } else {
      $output .= '<a href="?page=' . urlencode($item['path']) . '" class="nav-link">';
      $output .= htmlspecialchars($item['name']);
      $output .= '</a>';
    }

    $output .= '</li>';
  }

  $output .= '</ul>';
  return $output;
}

/**
 * Render table of contents from headings
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
?>