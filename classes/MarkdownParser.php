<?php

/**
 * Enhanced Markdown Parser with League/CommonMark and Fallback
 * Automatically falls back to simple parser if League/CommonMark is not available
 */
class MarkdownParser
{
  private static $converter = null;
  private static $useLeague = null;
  private static $debugInfo = [];

  /**
   * Check if League/CommonMark is available
   */
  private static function isLeagueAvailable()
  {
    if (self::$useLeague === null) {
      self::$debugInfo[] = 'Checking League/CommonMark availability...';

      // Try to load composer autoloader if not already loaded
      if (!class_exists('League\CommonMark\MarkdownConverter')) {
        self::$debugInfo[] = 'MarkdownConverter class not found, trying to load autoloader...';

        // Determine the application root directory
        $appRoot = dirname(__DIR__); // Go up from classes/ to application root

        $autoloaderPaths = [
          // Standard PHP application paths
          $appRoot . '/vendor/autoload.php',           // Most likely location
          __DIR__ . '/../vendor/autoload.php',         // Relative to classes dir
          __DIR__ . '/../../vendor/autoload.php',      // Two levels up
          __DIR__ . '/../../../vendor/autoload.php',   // Three levels up
          dirname(__DIR__) . '/vendor/autoload.php',   // More explicit parent dir
        ];

        $autoloaderLoaded = false;
        foreach ($autoloaderPaths as $path) {
          self::$debugInfo[] = "Checking autoloader path: {$path}";

          if (file_exists($path)) {
            self::$debugInfo[] = "Found autoloader at: {$path}";
            try {
              require_once $path;
              $autoloaderLoaded = true;
              self::$debugInfo[] = "Successfully loaded autoloader from: {$path}";
              break;
            } catch (Exception $e) {
              self::$debugInfo[] = "Failed to load autoloader from {$path}: " . $e->getMessage();
            }
          } else {
            self::$debugInfo[] = "Autoloader not found at: {$path}";
          }
        }

        if (!$autoloaderLoaded) {
          self::$debugInfo[] = 'No composer autoloader found in any expected location';
          self::$debugInfo[] = 'Application root detected as: ' . $appRoot;
          self::$debugInfo[] = 'You may need to run: cd ' . $appRoot . ' && composer install';
        }
      } else {
        self::$debugInfo[] = 'MarkdownConverter class already available';
      }

      // Check if the class is available after trying to load autoloader
      $classExists = class_exists('League\CommonMark\MarkdownConverter');
      self::$useLeague = $classExists;

      if ($classExists) {
        self::$debugInfo[] = 'League/CommonMark is available - will use advanced parser';
      } else {
        self::$debugInfo[] = 'League/CommonMark not available - will use simple fallback parser';
      }

      // Log debug info if debug mode is enabled AND markdown debug is requested
      if (defined('DEBUG_MODE') && DEBUG_MODE && isset($_GET['debug_markdown'])) {
        error_log('MarkdownParser Debug: ' . implode(' | ', self::$debugInfo));
      }
    }

    return self::$useLeague;
  }

  /**
   * Get configured markdown converter instance (League/CommonMark)
   */
  private static function getLeagueConverter()
  {
    if (self::$converter === null && self::isLeagueAvailable()) {
      try {
        $config = [
          'html_input' => 'allow',
          'allow_unsafe_links' => false,
        ];

        $environment = new \League\CommonMark\Environment\Environment($config);
        $environment->addExtension(new \League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension());
        $environment->addExtension(new \League\CommonMark\Extension\GithubFlavoredMarkdownExtension());

        // Add HeadingPermalink extension to generate IDs
        if (class_exists('\League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension')) {
          $environment->addExtension(new \League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension());
        }

        self::$converter = new \League\CommonMark\MarkdownConverter($environment);

        if (defined('DEBUG_MODE') && DEBUG_MODE && isset($_GET['debug_markdown'])) {
          error_log('MarkdownParser: Successfully initialized League/CommonMark converter with ID generation');
        }
      } catch (Exception $e) {
        self::$debugInfo[] = 'Failed to initialize League/CommonMark: ' . $e->getMessage();
        error_log('Failed to initialize League/CommonMark: ' . $e->getMessage());
        self::$useLeague = false;
        self::$converter = null;
      }
    }

    return self::$converter;
  }

  /**
   * Parse markdown text to HTML
   */
  public static function parse($text)
  {
    if (empty($text)) {
      return '';
    }

    $result = '';

    // Try League/CommonMark first
    if (self::isLeagueAvailable()) {
      try {
        $converter = self::getLeagueConverter();
        if ($converter) {
          $result = $converter->convert($text)->getContent();

          if (defined('DEBUG_MODE') && DEBUG_MODE && isset($_GET['debug_markdown'])) {
            error_log('MarkdownParser: Used League/CommonMark for parsing');
          }
        }
      } catch (Exception $e) {
        error_log('League/CommonMark parsing error: ' . $e->getMessage());
        // Fall through to simple parser
      }
    }

    // Fallback to simple parser if League failed or not available
    if (empty($result)) {
      if (defined('DEBUG_MODE') && DEBUG_MODE && isset($_GET['debug_markdown'])) {
        error_log('MarkdownParser: Using simple fallback parser');
      }
      $result = self::parseSimple($text);
    }

    // ALWAYS ensure headings have IDs, regardless of parser used
    $result = self::ensureHeadingIds($result);

    return $result;
  }

  /**
   * Ensure all headings have IDs (post-processing step)
   */
  private static function ensureHeadingIds($html)
  {
    // Use DOMDocument to properly parse and modify HTML
    if (class_exists('DOMDocument')) {
      return self::ensureHeadingIdsWithDOM($html);
    }

    // Fallback to regex-based approach
    return self::ensureHeadingIdsWithRegex($html);
  }

  /**
   * Ensure heading IDs using DOMDocument (preferred method)
   */
  private static function ensureHeadingIdsWithDOM($html)
  {
    try {
      $dom = new DOMDocument('1.0', 'UTF-8');
      $dom->preserveWhiteSpace = false;

      // Load HTML with UTF-8 encoding
      $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

      $xpath = new DOMXPath($dom);
      $headings = $xpath->query('//h1 | //h2 | //h3 | //h4 | //h5 | //h6');

      foreach ($headings as $heading) {
        if (!$heading->hasAttribute('id')) {
          $id = self::generateHeaderId($heading->textContent);
          $heading->setAttribute('id', $id);

          if (defined('DEBUG_MODE') && DEBUG_MODE && isset($_GET['debug_markdown'])) {
            error_log("Added ID '{$id}' to heading: {$heading->textContent}");
          }
        }
      }

      return $dom->saveHTML();
    } catch (Exception $e) {
      if (defined('DEBUG_MODE') && DEBUG_MODE) {
        error_log('DOM-based ID generation failed: ' . $e->getMessage());
      }
      return self::ensureHeadingIdsWithRegex($html);
    }
  }

  /**
   * Ensure heading IDs using regex (fallback method)
   */
  private static function ensureHeadingIdsWithRegex($html)
  {
    return preg_replace_callback(
      '/<(h[1-6])(?![^>]*\sid=)([^>]*)>(.+?)<\/\1>/i',
      function ($matches) {
        $tag = $matches[1];
        $attributes = $matches[2];
        $content = $matches[3];
        $id = self::generateHeaderId(strip_tags($content));

        if (defined('DEBUG_MODE') && DEBUG_MODE && isset($_GET['debug_markdown'])) {
          error_log("Added ID '{$id}' to heading: {$content}");
        }

        return "<{$tag} id=\"{$id}\"{$attributes}>{$content}</{$tag}>";
      },
      $html
    );
  }

  /**
   * Simple markdown parser fallback
   */
  private static function parseSimple($text)
  {
    // Normalize line endings
    $text = str_replace(["\r\n", "\r"], "\n", $text);

    // Handle fenced code blocks first
    $text = self::parseFencedCodeBlocks($text);

    // Process other elements
    $text = self::parseHeaders($text);
    $text = self::parseHorizontalRules($text);
    $text = self::parseBlockquotes($text);
    $text = self::parseTables($text);
    $text = self::parseTaskLists($text);
    $text = self::parseLists($text);
    $text = self::parseInlineCode($text);
    $text = self::parseLinks($text);
    $text = self::parseImages($text);
    $text = self::parseStrikethrough($text);
    $text = self::parseBold($text);
    $text = self::parseItalic($text);
    $text = self::parseAutolinks($text);
    $text = self::parseParagraphs($text);

    return $text;
  }

  /**
   * Parse fenced code blocks (```)
   */
  private static function parseFencedCodeBlocks($text)
  {
    return preg_replace_callback(
      '/^```(\w+)?\s*\n(.*?)\n```$/ms',
      function ($matches) {
        $language = isset($matches[1]) && $matches[1] ? $matches[1] : '';
        $code = htmlspecialchars($matches[2]);
        $langClass = $language ? ' class="language-' . htmlspecialchars($language) . '"' : '';
        return '<pre><code' . $langClass . '>' . $code . '</code></pre>';
      },
      $text
    );
  }

  /**
   * Parse headers (# ## ### ####) with auto-generated IDs
   */
  private static function parseHeaders($text)
  {
    return preg_replace_callback('/^(#{1,6})\s+(.+)$/m', function ($matches) {
      $level = strlen($matches[1]);
      $title = trim($matches[2]);
      $id = self::generateHeaderId($title);
      return "<h{$level} id=\"{$id}\">{$title}</h{$level}>";
    }, $text);
  }

  /**
   * Parse horizontal rules (--- or *** or ___)
   */
  private static function parseHorizontalRules($text)
  {
    return preg_replace('/^[ \t]*(-{3,}|\*{3,}|_{3,})[ \t]*$/m', '<hr>', $text);
  }

  /**
   * Parse blockquotes (> text)
   */
  private static function parseBlockquotes($text)
  {
    return preg_replace_callback('/^>\s?(.+)$/m', function ($matches) {
      return '<blockquote><p>' . $matches[1] . '</p></blockquote>';
    }, $text);
  }

  /**
   * Parse simple tables
   */
  private static function parseTables($text)
  {
    $lines = explode("\n", $text);
    $result = [];
    $inTable = false;
    $tableRows = [];

    foreach ($lines as $line) {
      if (preg_match('/\|/', $line) && trim($line)) {
        if (!$inTable) {
          $inTable = true;
          $tableRows = [];
        }

        // Skip separator line
        if (preg_match('/^\|?\s*:?-+:?\s*(\|\s*:?-+:?\s*)*\|?\s*$/', trim($line))) {
          continue;
        }

        $tableRows[] = $line;
      } else {
        if ($inTable) {
          $result[] = self::renderSimpleTable($tableRows);
          $inTable = false;
          $tableRows = [];
        }
        $result[] = $line;
      }
    }

    if ($inTable && !empty($tableRows)) {
      $result[] = self::renderSimpleTable($tableRows);
    }

    return implode("\n", $result);
  }

  /**
   * Render simple table
   */
  private static function renderSimpleTable($rows)
  {
    if (empty($rows)) return '';

    $html = '<table>';
    $firstRow = true;

    foreach ($rows as $row) {
      $cells = array_map('trim', explode('|', trim($row, '| ')));
      $cells = array_filter($cells); // Remove empty cells from ends

      if ($firstRow) {
        $html .= '<thead><tr>';
        foreach ($cells as $cell) {
          $html .= '<th>' . htmlspecialchars($cell) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        $firstRow = false;
      } else {
        $html .= '<tr>';
        foreach ($cells as $cell) {
          $html .= '<td>' . htmlspecialchars($cell) . '</td>';
        }
        $html .= '</tr>';
      }
    }

    $html .= '</tbody></table>';
    return $html;
  }

  /**
   * Parse task lists (- [ ] and - [x])
   */
  private static function parseTaskLists($text)
  {
    return preg_replace_callback('/^[-*+]\s+\[([ xX])\]\s+(.+)$/m', function ($matches) {
      $checked = strtolower($matches[1]) === 'x' ? 'checked' : '';
      $content = htmlspecialchars($matches[2]);
      return '<li class="task-list-item"><input type="checkbox" ' . $checked . ' disabled> ' . $content . '</li>';
    }, $text);
  }

  /**
   * Parse simple lists
   */
  private static function parseLists($text)
  {
    // Unordered lists
    $text = preg_replace('/^[-*+]\s+(.+)$/m', '<li>$1</li>', $text);
    $text = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $text);

    // Ordered lists  
    $text = preg_replace('/^\d+\.\s+(.+)$/m', '<li>$1</li>', $text);

    return $text;
  }

  /**
   * Parse inline code (`)
   */
  private static function parseInlineCode($text)
  {
    return preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
  }

  /**
   * Parse links [text](url)
   */
  private static function parseLinks($text)
  {
    return preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $text);
  }

  /**
   * Parse images ![alt](src)
   */
  private static function parseImages($text)
  {
    return preg_replace('/!\[([^\]]*)\]\(([^)]+)\)/', '<img src="$2" alt="$1">', $text);
  }

  /**
   * Parse strikethrough (~~text~~)
   */
  private static function parseStrikethrough($text)
  {
    return preg_replace('/~~([^~]+)~~/', '<del>$1</del>', $text);
  }

  /**
   * Parse bold text (** and __)
   */
  private static function parseBold($text)
  {
    $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/__([^_]+)__/', '<strong>$1</strong>', $text);
    return $text;
  }

  /**
   * Parse italic text (* and _)
   */
  private static function parseItalic($text)
  {
    $text = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $text);
    $text = preg_replace('/(?<!_)_([^_]+)_(?!_)/', '<em>$1</em>', $text);
    return $text;
  }

  /**
   * Parse autolinks
   */
  private static function parseAutolinks($text)
  {
    return preg_replace_callback(
      '/\b(https?:\/\/[^\s<>"`{}\[\]\\\\]+)/i',
      function ($matches) {
        $url = htmlspecialchars($matches[1]);
        return '<a href="' . $url . '">' . $url . '</a>';
      },
      $text
    );
  }

  /**
   * Parse paragraphs
   */
  private static function parseParagraphs($text)
  {
    $paragraphs = explode("\n\n", $text);
    $result = [];

    foreach ($paragraphs as $paragraph) {
      $paragraph = trim($paragraph);
      if (empty($paragraph)) continue;

      // Skip if already contains HTML tags
      if (preg_match('/<(?:h[1-6]|ul|ol|li|table|pre|code|blockquote|hr|div|p)\b/', $paragraph)) {
        $result[] = $paragraph;
      } else {
        $result[] = '<p>' . $paragraph . '</p>';
      }
    }

    return implode("\n\n", $result);
  }

  /**
   * Generate a URL-friendly ID from header text
   */
  private static function generateHeaderId($text)
  {
    $id = strip_tags($text);
    $id = strtolower($id);
    $id = preg_replace('/[^a-z0-9]+/', '-', $id);
    $id = trim($id, '-');

    // Debug: Log ID generation if debug mode is on
    if (defined('DEBUG_MODE') && DEBUG_MODE && isset($_GET['debug_markdown'])) {
      error_log("Generated ID for '{$text}': '{$id}'");
    }

    return $id ?: 'header';
  }

  /**
   * Extract title from markdown content (first H1)
   */
  public static function extractTitle($content)
  {
    if (empty($content)) {
      return null;
    }

    if (preg_match('/^# (.+)$/m', $content, $matches)) {
      return trim($matches[1]);
    }

    return null;
  }

  /**
   * Extract headers from markdown content for table of contents
   */
  public static function extractHeaders($content)
  {
    $headers = [];
    if (empty($content)) {
      return $headers;
    }

    preg_match_all('/^(#{1,6})\s+(.+)$/m', $content, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
      $level = strlen($match[1]);
      $text = trim($match[2]);
      $id = self::generateHeaderId($text);

      $headers[] = [
        'level' => $level,
        'text' => $text,
        'id' => $id
      ];
    }

    return $headers;
  }

  /**
   * Create a search snippet with highlighted terms
   */
  public static function createSearchSnippet($content, $searchTerm, $length = null)
  {
    $length = $length ?: (defined('SEARCH_SNIPPET_LENGTH') ? SEARCH_SNIPPET_LENGTH : 200);

    // Remove markdown formatting for clean snippet
    $cleanContent = preg_replace('/[#*`\[\]()]/', '', $content);
    $cleanContent = trim(preg_replace('/\s+/', ' ', $cleanContent));

    $pos = stripos($cleanContent, $searchTerm);
    if ($pos === false) {
      return substr($cleanContent, 0, $length) . '...';
    }

    $start = max(0, $pos - intval($length / 2));
    $snippet = substr($cleanContent, $start, $length);

    // Highlight the search term
    $snippet = str_ireplace(
      $searchTerm,
      '<mark>' . htmlspecialchars($searchTerm) . '</mark>',
      htmlspecialchars($snippet)
    );

    return ($start > 0 ? '...' : '') . $snippet . '...';
  }

  /**
   * Get parser information and debug details
   */
  public static function getParserInfo()
  {
    $isLeagueAvailable = self::isLeagueAvailable();

    if ($isLeagueAvailable) {
      return [
        'name' => 'League/CommonMark',
        'version' => 'available',
        'features' => [
          'GitHub Flavored Markdown',
          'Tables',
          'Task Lists',
          'Strikethrough',
          'Autolinks',
          'Code Blocks'
        ],
        'available' => true,
        'debug_info' => self::$debugInfo
      ];
    }

    return [
      'name' => 'Simple Fallback Parser',
      'version' => '1.0.0',
      'features' => ['Basic markdown', 'Tables', 'Code blocks', 'Task lists'],
      'available' => true,
      'debug_info' => self::$debugInfo
    ];
  }

  /**
   * Force refresh of League availability check (useful for debugging)
   */
  public static function refreshLeagueCheck()
  {
    self::$useLeague = null;
    self::$converter = null;
    self::$debugInfo = [];
    return self::isLeagueAvailable();
  }
}
