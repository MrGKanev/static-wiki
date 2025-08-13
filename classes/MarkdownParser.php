<?php

/**
 * Simple Markdown Parser
 * For production use, consider using Parsedown: composer require erusev/parsedown
 */

class MarkdownParser
{

  /**
   * Parse markdown text to HTML
   */
  public static function parse($text)
  {
    if (empty($text)) {
      return '';
    }

    // Normalize line endings
    $text = str_replace(["\r\n", "\r"], "\n", $text);

    // Process in order of importance
    $text = self::parseHeaders($text);
    $text = self::parseCodeBlocks($text);
    $text = self::parseInlineCode($text);
    $text = self::parseLinks($text);
    $text = self::parseBold($text);
    $text = self::parseItalic($text);
    $text = self::parseLists($text);
    $text = self::parseParagraphs($text);

    return $text;
  }

  /**
   * Parse headers (# ## ### ####)
   */
  private static function parseHeaders($text)
  {
    $text = preg_replace('/^#### (.+)$/m', '<h4>$1</h4>', $text);
    $text = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $text);
    $text = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $text);
    $text = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $text);
    return $text;
  }

  /**
   * Parse code blocks (```)
   */
  private static function parseCodeBlocks($text)
  {
    return preg_replace('/```(.*?)```/s', '<pre><code>$1</code></pre>', $text);
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
   * Parse bold text (**)
   */
  private static function parseBold($text)
  {
    return preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text);
  }

  /**
   * Parse italic text (*)
   */
  private static function parseItalic($text)
  {
    return preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $text);
  }

  /**
   * Parse unordered lists (*)
   */
  private static function parseLists($text)
  {
    // Split into lines
    $lines = explode("\n", $text);
    $inList = false;
    $result = [];

    foreach ($lines as $line) {
      $trimmed = trim($line);

      if (preg_match('/^\* (.+)$/', $trimmed, $matches)) {
        if (!$inList) {
          $result[] = '<ul>';
          $inList = true;
        }
        $result[] = '<li>' . $matches[1] . '</li>';
      } else {
        if ($inList) {
          $result[] = '</ul>';
          $inList = false;
        }
        $result[] = $line;
      }
    }

    if ($inList) {
      $result[] = '</ul>';
    }

    return implode("\n", $result);
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

      if (empty($paragraph)) {
        continue;
      }

      // Skip if already contains HTML tags
      if (preg_match('/<\w+/', $paragraph)) {
        $result[] = $paragraph;
      } else {
        $result[] = '<p>' . $paragraph . '</p>';
      }
    }

    return implode("\n\n", $result);
  }

  /**
   * Extract title from markdown content (first H1)
   */
  public static function extractTitle($content)
  {
    if (preg_match('/^# (.+)$/m', $content, $matches)) {
      return trim($matches[1]);
    }
    return null;
  }

  /**
   * Create a search snippet with highlighted terms
   */
  public static function createSearchSnippet($content, $searchTerm, $length = null)
  {
    $length = $length ?: SEARCH_SNIPPET_LENGTH;

    // Remove markdown formatting for snippet
    $cleanContent = preg_replace('/[#*`\[\]()]/', '', $content);

    $pos = stripos($cleanContent, $searchTerm);
    if ($pos === false) {
      return substr($cleanContent, 0, $length) . '...';
    }

    $start = max(0, $pos - $length / 2);
    $snippet = substr($cleanContent, $start, $length);

    // Highlight the search term
    $snippet = str_ireplace($searchTerm, '<mark>' . $searchTerm . '</mark>', $snippet);

    return ($start > 0 ? '...' : '') . $snippet . '...';
  }
}
