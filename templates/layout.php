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
  <div class="container">
    <!-- Mobile menu toggle -->
    <button class="mobile-menu-toggle" onclick="toggleMobileMenu()" aria-label="Toggle navigation menu">
      ☰
    </button>

    <!-- Sidebar Navigation -->
    <div class="sidebar" id="sidebar">
      <div class="header">
        <h1>
          <a href="?" aria-label="Home - Return to main page" title="Go to Home">
            <?php echo htmlspecialchars(WIKI_TITLE); ?>
          </a>
        </h1>

        <!-- Search Form -->
        <form class="search-form" method="GET" role="search">
          <input type="hidden" name="search" value="1">
          <input
            type="text"
            name="q"
            placeholder="Search..."
            value="<?php echo htmlspecialchars($searchQuery ?? ''); ?>"
            aria-label="Search the wiki">
          <button type="submit" aria-label="Submit search">Search</button>
        </form>
      </div>

      <!-- Navigation Menu -->
      <nav class="nav" role="navigation" aria-label="Main navigation">
        <?php if (!empty($navigation)): ?>
          <?php echo renderNavigation($navigation); ?>
        <?php else: ?>
          <div class="empty-message">
            <p>No content found.</p>
            <p>Add markdown files to the content directory to get started.</p>
          </div>
        <?php endif; ?>
      </nav>
    </div>

    <!-- Main Content Area -->
    <main class="main-content" role="main">
      <?php echo $content; ?>
    </main>
  </div>

  <script>
    // Mobile menu functionality
    function toggleMobileMenu() {
      const sidebar = document.getElementById('sidebar');
      sidebar.classList.toggle('active');
    }

    // Close mobile menu when clicking outside
    document.addEventListener('click', function(event) {
      const sidebar = document.getElementById('sidebar');
      const toggle = document.querySelector('.mobile-menu-toggle');

      if (window.innerWidth <= 768 &&
        !sidebar.contains(event.target) &&
        !toggle.contains(event.target) &&
        sidebar.classList.contains('active')) {
        sidebar.classList.remove('active');
      }
    });

    // Handle window resize
    window.addEventListener('resize', function() {
      const sidebar = document.getElementById('sidebar');
      if (window.innerWidth > 768) {
        sidebar.classList.remove('active');
      }
    });

    // Keyboard navigation for accessibility
    document.addEventListener('keydown', function(event) {
      if (event.key === 'Escape') {
        const sidebar = document.getElementById('sidebar');
        if (sidebar.classList.contains('active')) {
          sidebar.classList.remove('active');
        }
      }
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

  $output = '<ul' . ($level === 0 ? ' class="nav-root"' : '') . '>';

  foreach ($items as $item) {
    $output .= '<li>';

    if ($item['type'] === 'category') {
      $output .= '<div class="category">' . htmlspecialchars($item['name']) . '</div>';

      if (!empty($item['children'])) {
        $output .= '<div class="subcategory">';
        $output .= renderNavigation($item['children'], $level + 1);
        $output .= '</div>';
      }
    } else {
      $output .= '<a href="?page=' . urlencode($item['path']) . '">';
      $output .= htmlspecialchars($item['name']);
      $output .= '</a>';
    }

    $output .= '</li>';
  }

  $output .= '</ul>';
  return $output;
}
