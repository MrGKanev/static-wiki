<?php

/**
 * Page template for displaying wiki content
 */

// Show cache action feedback if in debug mode
if (DEBUG_MODE) {
  if (isset($_GET['cache_cleared'])) {
    echo '<div style="background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 15px; border: 1px solid #c3e6cb;">
                Cache cleared successfully! Removed ' . (int)$_GET['cache_cleared'] . ' entries.
              </div>';
  }
  if (isset($_GET['cache_cleaned'])) {
    echo '<div style="background: #fff3cd; color: #856404; padding: 10px; border-radius: 5px; margin-bottom: 15px; border: 1px solid #ffeaa7;">
                Cache cleanup completed! Removed ' . (int)$_GET['cache_cleaned'] . ' expired entries.
              </div>';
  }
}

// Always show breadcrumbs
if (!empty($currentPath)) {
  // Get full breadcrumbs for non-home pages
  $breadcrumbs = $wiki->getBreadcrumbs($currentPath);
} else {
  // Show just "Home" for the home page
  $breadcrumbs = [['name' => 'Home', 'path' => '']];
}

if (!empty($breadcrumbs)) {
  echo '<nav class="breadcrumb" aria-label="Breadcrumb navigation">';

  foreach ($breadcrumbs as $index => $crumb) {
    if ($index > 0) {
      echo '<span class="separator" aria-hidden="true">&gt;</span>';
    }

    if ($index === count($breadcrumbs) - 1) {
      // Current page - no link
      echo '<span aria-current="page">' . htmlspecialchars($crumb['name']) . '</span>';
    } else {
      // Linked breadcrumb
      $href = empty($crumb['path']) ? '?' : '?page=' . urlencode($crumb['path']);
      echo '<a href="' . $href . '">' . htmlspecialchars($crumb['name']) . '</a>';
    }
  }

  echo '</nav>';
}
?>

<div class="content">
  <?php if ($pageContent !== null): ?>
    <?php echo $pageContent; ?>
  <?php else: ?>
    <div class="error-page">
      <h1>404</h1>
      <p>The page you're looking for could not be found.</p>
      <p><a href="?">Return to Home</a></p>
    </div>
  <?php endif; ?>
</div>

<?php if (DEBUG_MODE && $pageContent !== null): ?>
  <!-- Debug information (only shown when DEBUG_MODE is true) -->
  <div class="debug-info" style="background: #f8f9fa; padding: 15px; margin-top: 20px; border-radius: 5px; font-size: 12px; color: #666; border: 1px solid #dee2e6;">
    <strong>Debug Info:</strong><br>
    Current Path: <?php echo htmlspecialchars($currentPath ?: 'index'); ?><br>
    Page Title: <?php echo htmlspecialchars($pageTitle); ?><br>

    <?php if ($wiki->isCacheEnabled()): ?>
      <?php $cacheStats = $wiki->getCacheStats(); ?>
      <strong>Cache Stats:</strong><br>
      Enabled: Yes |
      Total Entries: <?php echo $cacheStats['total']; ?> |
      Valid: <?php echo $cacheStats['valid']; ?> |
      Expired: <?php echo $cacheStats['expired']; ?> |
      Size: <?php echo $cacheStats['size_human']; ?>
      <br>
      <a href="?action=clear_cache" style="color: #e74c3c; text-decoration: none; font-size: 11px;">[Clear Cache]</a>
      <a href="?action=cleanup_cache" style="color: #f39c12; text-decoration: none; font-size: 11px; margin-left: 10px;">[Cleanup Expired]</a>
    <?php else: ?>
      <strong>Cache:</strong> Disabled
    <?php endif; ?>
  </div>
<?php endif; ?>