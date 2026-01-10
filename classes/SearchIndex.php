<?php

declare(strict_types=1);

namespace Wiki;

use Wiki\Interfaces\CacheInterface;

/**
 * Inverted search index for fast full-text search
 *
 * Implements an inverted index data structure that maps words to the documents
 * containing them. This enables O(1) word lookups instead of O(n) file scanning,
 * dramatically improving search performance for large wikis.
 *
 * Features:
 * - TF-IDF scoring for relevance ranking
 * - Stop words filtering (common words excluded)
 * - Title match boosting (2x weight)
 * - Automatic cache invalidation
 * - Lazy loading of index
 *
 * @package Wiki
 * @author  Static Wiki Contributors
 * @license MIT
 *
 * @example Basic usage:
 * ```php
 * $index = new SearchIndex('/path/to/content', $cache);
 *
 * // Search returns scored results
 * $results = $index->search('api documentation', 20);
 * foreach ($results as $result) {
 *     echo "{$result['title']} (score: {$result['score']})\n";
 * }
 *
 * // Get index statistics
 * $stats = $index->getStats();
 * echo "Indexed {$stats['words']} words in {$stats['documents']} documents\n";
 * ```
 */
class SearchIndex
{
    /** @var string Cache key for the search index */
    private const INDEX_CACHE_KEY = 'search_index';

    /** @var int Cache TTL for index (1 hour) */
    private const INDEX_CACHE_TTL = 3600;

    /** @var int Minimum word length to index */
    private const MIN_WORD_LENGTH = 2;

    /** @var array<string> Common words excluded from indexing */
    private const STOP_WORDS = [
        'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
        'of', 'with', 'by', 'from', 'as', 'is', 'was', 'are', 'were', 'been',
        'be', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would',
        'could', 'should', 'may', 'might', 'must', 'shall', 'can', 'need',
        'it', 'its', 'this', 'that', 'these', 'those', 'i', 'you', 'he',
        'she', 'we', 'they', 'what', 'which', 'who', 'whom', 'how', 'when',
        'where', 'why', 'all', 'each', 'every', 'both', 'few', 'more', 'most',
        'other', 'some', 'such', 'no', 'not', 'only', 'same', 'so', 'than',
        'too', 'very', 'just', 'also', 'now', 'here', 'there', 'then'
    ];

    private readonly string $contentDir;
    private ?CacheInterface $cache;

    /** @var array<string, array<string, array{title: string, path: string, positions: array<int>}>> */
    private array $index = [];

    /** @var array<string, array{title: string, content: string}> */
    private array $documents = [];

    /** @var bool Whether the index has been loaded */
    private bool $indexLoaded = false;

    /**
     * Create a new SearchIndex instance
     *
     * @param string|null         $contentDir Directory containing Markdown files
     * @param CacheInterface|null $cache      Cache instance for storing index
     */
    public function __construct(?string $contentDir = null, ?CacheInterface $cache = null)
    {
        $this->contentDir = $contentDir ?? (defined('CONTENT_DIR') ? CONTENT_DIR : __DIR__ . '/../content');
        $this->cache = $cache;
    }

    /**
     * Search the index for matching documents
     *
     * Uses TF-IDF scoring to rank results by relevance. Title matches
     * receive a 2x boost. Results are sorted by score descending.
     *
     * @param string $query Search query (will be tokenized)
     * @param int    $limit Maximum number of results (default: 50)
     *
     * @return array<array{title: string, path: string, snippet: string, score: float}>
     */
    public function search(string $query, int $limit = 50): array
    {
        $this->ensureIndexLoaded();

        $query = trim($query);
        if (strlen($query) < self::MIN_WORD_LENGTH) {
            return [];
        }

        $queryWords = $this->tokenize($query);
        if (empty($queryWords)) {
            return [];
        }

        // Find matching documents with scores
        $scores = $this->calculateScores($queryWords);

        // Sort by score descending
        arsort($scores);

        // Build results
        $results = [];
        $count = 0;

        foreach ($scores as $path => $score) {
            if ($count >= $limit) {
                break;
            }

            if (!isset($this->documents[$path])) {
                continue;
            }

            $doc = $this->documents[$path];
            $results[] = [
                'title' => $doc['title'],
                'path' => $path,
                'snippet' => $this->createSnippet($doc['content'], $query),
                'score' => $score
            ];
            $count++;
        }

        return $results;
    }

    /**
     * Calculate relevance scores for documents matching query words
     *
     * @param array<string> $queryWords
     * @return array<string, float>
     */
    private function calculateScores(array $queryWords): array
    {
        $scores = [];
        $totalDocs = count($this->documents);

        foreach ($queryWords as $word) {
            if (!isset($this->index[$word])) {
                continue;
            }

            $docsWithWord = count($this->index[$word]);
            // IDF: Inverse Document Frequency
            $idf = log(($totalDocs + 1) / ($docsWithWord + 1)) + 1;

            foreach ($this->index[$word] as $path => $info) {
                // TF: Term Frequency (number of occurrences)
                $tf = count($info['positions']);

                // TF-IDF score
                $tfIdf = $tf * $idf;

                // Boost for title matches
                if (stripos($info['title'], $word) !== false) {
                    $tfIdf *= 2.0;
                }

                if (!isset($scores[$path])) {
                    $scores[$path] = 0;
                }
                $scores[$path] += $tfIdf;
            }
        }

        return $scores;
    }

    /**
     * Ensure the index is loaded (from cache or built fresh)
     */
    private function ensureIndexLoaded(): void
    {
        if ($this->indexLoaded) {
            return;
        }

        // Try to load from cache
        if ($this->cache) {
            $cached = $this->cache->get(self::INDEX_CACHE_KEY);
            if ($cached !== null && is_array($cached)) {
                $this->index = $cached['index'] ?? [];
                $this->documents = $cached['documents'] ?? [];
                $this->indexLoaded = true;
                return;
            }
        }

        // Build fresh index
        $this->buildIndex();
        $this->indexLoaded = true;

        // Cache the index
        if ($this->cache) {
            $this->cache->set(self::INDEX_CACHE_KEY, [
                'index' => $this->index,
                'documents' => $this->documents,
                'built_at' => time()
            ], self::INDEX_CACHE_TTL);
        }
    }

    /**
     * Build the inverted index from all Markdown files
     *
     * Scans the content directory recursively, tokenizes each file,
     * and builds the inverted index mapping words to documents.
     * This is called automatically on first search if cache is empty.
     *
     * @return void
     */
    public function buildIndex(): void
    {
        $this->index = [];
        $this->documents = [];

        $this->indexDirectory($this->contentDir, '');
    }

    /**
     * Recursively index a directory
     */
    private function indexDirectory(string $dir, string $relativePath): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = scandir($dir);
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || $file[0] === '.') {
                continue;
            }

            $fullPath = $dir . '/' . $file;
            $relativeFilePath = $relativePath ? $relativePath . '/' . $file : $file;

            if (is_dir($fullPath)) {
                $this->indexDirectory($fullPath, $relativeFilePath);
            } elseif ($this->isMarkdownFile($file)) {
                $this->indexFile($fullPath, $relativeFilePath);
            }
        }
    }

    /**
     * Index a single markdown file
     */
    private function indexFile(string $filePath, string $relativeFilePath): void
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return;
        }

        // Extract title
        $title = $this->extractTitle($content);
        if ($title === null) {
            $name = pathinfo($relativeFilePath, PATHINFO_FILENAME);
            $title = $this->generateTitleFromPath($name);
        }

        // Build document path (same logic as Wiki class)
        $name = pathinfo($relativeFilePath, PATHINFO_FILENAME);
        $docPath = $this->constructPagePath($relativeFilePath, $name);

        // Store document metadata
        $this->documents[$docPath] = [
            'title' => $title,
            'content' => $content
        ];

        // Tokenize and index
        $words = $this->tokenize($content);
        $titleWords = $this->tokenize($title);

        // Index content words with positions
        foreach ($words as $position => $word) {
            if (!isset($this->index[$word])) {
                $this->index[$word] = [];
            }
            if (!isset($this->index[$word][$docPath])) {
                $this->index[$word][$docPath] = [
                    'title' => $title,
                    'path' => $docPath,
                    'positions' => []
                ];
            }
            $this->index[$word][$docPath]['positions'][] = $position;
        }

        // Also index title words (they're important)
        foreach ($titleWords as $word) {
            if (!isset($this->index[$word])) {
                $this->index[$word] = [];
            }
            if (!isset($this->index[$word][$docPath])) {
                $this->index[$word][$docPath] = [
                    'title' => $title,
                    'path' => $docPath,
                    'positions' => []
                ];
            }
        }
    }

    /**
     * Tokenize text into searchable words
     *
     * @return array<int, string>
     */
    private function tokenize(string $text): array
    {
        // Remove markdown syntax
        $text = preg_replace('/```[\s\S]*?```/', ' ', $text) ?? $text;
        $text = preg_replace('/`[^`]+`/', ' ', $text) ?? $text;
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text) ?? $text;
        $text = preg_replace('/[#*_~\[\](){}|>]/', ' ', $text) ?? $text;

        // Convert to lowercase and split
        $text = mb_strtolower($text, 'UTF-8');
        $words = preg_split('/[\s\-_.,;:!?\'\"\/\\\\]+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        if ($words === false) {
            return [];
        }

        // Filter words
        $filtered = [];
        foreach ($words as $word) {
            $word = trim($word);
            if (strlen($word) >= self::MIN_WORD_LENGTH && !in_array($word, self::STOP_WORDS, true)) {
                $filtered[] = $word;
            }
        }

        return $filtered;
    }

    /**
     * Create a search snippet highlighting the query
     */
    private function createSnippet(string $content, string $query, int $length = 200): string
    {
        // Remove markdown formatting
        $text = preg_replace('/```[\s\S]*?```/', ' ', $content) ?? $content;
        $text = preg_replace('/`[^`]+`/', ' ', $text) ?? $text;
        $text = preg_replace('/[#*_~\[\](){}|>]/', '', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        $text = trim($text);

        // Find query position
        $pos = stripos($text, $query);
        if ($pos === false) {
            // Try first word of query
            $firstWord = explode(' ', $query)[0];
            $pos = stripos($text, $firstWord);
        }

        if ($pos === false) {
            // Return beginning of content
            return mb_substr($text, 0, $length) . (mb_strlen($text) > $length ? '...' : '');
        }

        // Calculate snippet boundaries
        $start = max(0, $pos - (int)($length / 3));
        $snippet = mb_substr($text, $start, $length);

        // Add ellipsis
        if ($start > 0) {
            $snippet = '...' . ltrim($snippet);
        }
        if ($start + $length < mb_strlen($text)) {
            $snippet = rtrim($snippet) . '...';
        }

        return $snippet;
    }

    /**
     * Extract title from markdown content
     */
    private function extractTitle(string $content): ?string
    {
        // Look for # heading
        if (preg_match('/^#\s+(.+)$/m', $content, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    /**
     * Generate title from path
     */
    private function generateTitleFromPath(string $name): string
    {
        $name = str_replace(['-', '_'], ' ', $name);
        return ucwords($name);
    }

    /**
     * Construct page path from file path
     */
    private function constructPagePath(string $relativeFilePath, string $name): string
    {
        $dir = dirname($relativeFilePath);

        if ($name === 'index' || $name === 'readme') {
            return $dir === '.' ? '' : $dir;
        }

        $pathWithoutExt = preg_replace('/\.md$/i', '', $relativeFilePath);
        return $pathWithoutExt ?? $relativeFilePath;
    }

    /**
     * Check if file is a markdown file
     */
    private function isMarkdownFile(string $file): bool
    {
        return strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'md';
    }

    /**
     * Get index statistics
     *
     * @return array{words: int, documents: int, cache_enabled: bool}
     */
    public function getStats(): array
    {
        $this->ensureIndexLoaded();

        return [
            'words' => count($this->index),
            'documents' => count($this->documents),
            'cache_enabled' => $this->cache !== null
        ];
    }

    /**
     * Clear the cached index
     *
     * Forces a rebuild on the next search operation.
     * Use this after adding/removing content files.
     *
     * @return bool True if cache was cleared successfully
     */
    public function clearCache(): bool
    {
        $this->index = [];
        $this->documents = [];
        $this->indexLoaded = false;

        if ($this->cache) {
            return $this->cache->delete(self::INDEX_CACHE_KEY);
        }

        return true;
    }

    /**
     * Force rebuild the index immediately
     *
     * Clears the cache and rebuilds the entire index from scratch.
     * Use this after significant content changes.
     *
     * @return void
     */
    public function rebuild(): void
    {
        $this->clearCache();
        $this->ensureIndexLoaded();
    }
}
