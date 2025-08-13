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
              placeholder="Search documentation..."
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
          <h3 class="toc-title">On this page</h3>
          <nav class="table-of-contents" aria-label="Table of contents">
            <?php echo renderTableOfContents($pageHeadings); ?>
          </nav>
        </div>
      <?php endif; ?>
    </aside>
  </div>

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

      if (window.innerWidth <= 768 && !isClickInsideSidebar && sidebar.classList.contains('mobile-open')) {
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