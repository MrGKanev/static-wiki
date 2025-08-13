<?php

/**
 * Search results template
 */
?>

<div class="search-results">
  <h1>Search Results</h1>

  <?php if (!empty($searchQuery)): ?>
    <div class="search-meta">
      <?php if (!empty($searchResults)): ?>
        Found <strong><?php echo count($searchResults); ?></strong>
        result<?php echo count($searchResults) !== 1 ? 's' : ''; ?>
        for "<strong><?php echo htmlspecialchars($searchQuery); ?></strong>"
      <?php else: ?>
        No results found for "<strong><?php echo htmlspecialchars($searchQuery); ?></strong>"
      <?php endif; ?>
    </div>

    <?php if (!empty($searchResults)): ?>
      <div class="search-results-list" role="list">
        <?php foreach ($searchResults as $result): ?>
          <article class="search-result" role="listitem">
            <h3>
              <a href="?page=<?php echo urlencode($result['path']); ?>">
                <?php echo htmlspecialchars($result['title']); ?>
              </a>
            </h3>

            <?php if (!empty($result['snippet'])): ?>
              <div class="snippet">
                <?php echo $result['snippet']; ?>
              </div>
            <?php endif; ?>

            <?php if (DEBUG_MODE): ?>
              <div class="debug-path" style="font-size: 11px; color: #999; margin-top: 5px;">
                Path: <?php echo htmlspecialchars($result['path']); ?>
              </div>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="no-results">
        <h2>No Results Found</h2>
        <p>Try adjusting your search terms or browse the navigation menu.</p>

        <div style="margin-top: 20px;">
          <h3>Search Tips:</h3>
          <ul style="text-align: left; display: inline-block; margin-top: 10px;">
            <li>Check your spelling</li>
            <li>Try more general terms</li>
            <li>Use different keywords</li>
            <li>Browse categories in the sidebar</li>
          </ul>
        </div>
      </div>
    <?php endif; ?>

    <?php if (count($searchResults) >= MAX_SEARCH_RESULTS): ?>
      <div class="search-limit-notice" style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 5px; color: #856404;">
        <strong>Note:</strong> Only the first <?php echo MAX_SEARCH_RESULTS; ?> results are shown.
        Try refining your search for more specific results.
      </div>
    <?php endif; ?>

  <?php else: ?>
    <div class="search-prompt">
      <p>Enter a search term above to find content in the wiki.</p>

      <div style="margin-top: 30px;">
        <h3>Popular Sections:</h3>
        <div style="margin-top: 15px;">
          <?php if (!empty($navigation)): ?>
            <?php
            // Show first few navigation items as suggestions
            $suggestions = array_slice($navigation, 0, 5);
            foreach ($suggestions as $item):
              if ($item['type'] === 'category'):
            ?>
                <a href="?page=<?php echo urlencode($item['path']); ?>"
                  style="display: inline-block; margin: 5px 10px 5px 0; padding: 8px 12px; background: #3498db; color: white; text-decoration: none; border-radius: 4px; font-size: 14px;">
                  <?php echo htmlspecialchars($item['name']); ?>
                </a>
            <?php
              endif;
            endforeach;
            ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php if (DEBUG_MODE): ?>
  <!-- Debug information for search -->
  <div class="debug-info" style="background: #f8f9fa; padding: 15px; margin-top: 20px; border-radius: 5px; font-size: 12px; color: #666;">
    <strong>Search Debug Info:</strong><br>
    Query: "<?php echo htmlspecialchars($searchQuery); ?>"<br>
    Query Length: <?php echo strlen($searchQuery); ?><br>
    Results Count: <?php echo count($searchResults); ?><br>
    Max Results: <?php echo MAX_SEARCH_RESULTS; ?>
  </div>
<?php endif; ?>