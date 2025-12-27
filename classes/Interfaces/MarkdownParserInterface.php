<?php

declare(strict_types=1);

namespace Wiki\Interfaces;

/**
 * Markdown Parser Interface
 * Defines the contract for markdown parsing implementations
 */
interface MarkdownParserInterface
{
    /**
     * Parse markdown text to HTML
     *
     * @param string $text Markdown text to parse
     * @return string Parsed HTML
     */
    public static function parse(string $text): string;

    /**
     * Extract title from markdown content (first H1)
     *
     * @param string $content Markdown content
     * @return string|null The title or null if not found
     */
    public static function extractTitle(string $content): ?string;

    /**
     * Extract headers from markdown content for table of contents
     *
     * @param string $content Markdown content
     * @return array<int, array{level: int, text: string, id: string}> Array of headers
     */
    public static function extractHeaders(string $content): array;

    /**
     * Create a search snippet with highlighted terms
     *
     * @param string $content Content to create snippet from
     * @param string $searchTerm Term to highlight
     * @param int|null $length Maximum snippet length
     * @return string The snippet with highlighted terms
     */
    public static function createSearchSnippet(string $content, string $searchTerm, ?int $length = null): string;

    /**
     * Get parser information and debug details
     *
     * @return array{name: string, version: string, features: array<string>, available: bool, debug_info: array<string>} Parser info
     */
    public static function getParserInfo(): array;
}
