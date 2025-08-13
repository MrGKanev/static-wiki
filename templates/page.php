<?php

/**
 * Page template for displaying wiki content
 */

// Generate breadcrumbs if not on home page
if (!empty($currentPath)) {
  $breadcrumbs = $wiki->getBreadcrumbs($currentPath);

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
  <div class="debug-info" style="background: #f8f9fa; padding: 15px; margin-top: 20px; border-radius: 5px; font-size: 12px; color: #666;">
    <strong>Debug Info:</strong><br>
    Current Path: <?php echo htmlspecialchars($currentPath ?: 'index'); ?><br>
    File Path: <?php echo htmlspecialchars($wiki->getFilePath($currentPath)); ?><br>
    Page Title: <?php echo htmlspecialchars($pageTitle); ?>
  </div>
<?php endif; ?>