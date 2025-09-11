<?php

/**
 * Page template for displaying wiki content
 */
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