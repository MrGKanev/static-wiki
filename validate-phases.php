<?php

declare(strict_types=1);

/**
 * Phase Validation Script
 *
 * Validates that all completed optimization phases are correctly implemented.
 * Run this script to verify the integrity of Phase 1 optimizations.
 */

class PhaseValidator
{
    private array $errors = [];
    private array $warnings = [];
    private array $successes = [];
    private string $projectRoot;

    public function __construct(string $projectRoot)
    {
        $this->projectRoot = rtrim($projectRoot, '/');
    }

    public function validate(): void
    {
        echo "🔍 Starting Phase Validation...\n\n";

        $this->validatePhase1_1_PHPVersion();
        $this->validatePhase1_2_StrictTypes();
        $this->validatePhase1_3_JSONCaching();
        $this->validatePhase1_4_Interfaces();
        $this->validatePhase1_5_CacheTypeHints();
        $this->validatePhase1_6_WikiTypeHints();
        $this->validatePhase2_1_MarkdownParserNamespace();
        $this->validatePhase2_2_NamespaceUsage();
        $this->validatePhase2_3_SearchConsolidation();
        $this->validatePhase2_4_HTTPCaching();

        $this->printResults();
    }

    /**
     * Phase 1.1: Validate PHP Version Update to 8.4+
     */
    private function validatePhase1_1_PHPVersion(): void
    {
        echo "📋 Phase 1.1: PHP Version Update\n";

        // Check composer.json
        $composerPath = $this->projectRoot . '/composer.json';
        if (!file_exists($composerPath)) {
            $this->addError('1.1', 'composer.json not found');
            return;
        }

        $composer = json_decode(file_get_contents($composerPath), true);
        if (!isset($composer['require']['php'])) {
            $this->addError('1.1', 'PHP version not specified in composer.json');
            return;
        }

        $phpVersion = $composer['require']['php'];
        if (preg_match('/>=?8\.[4-9]|>=?9\./', $phpVersion)) {
            $this->addSuccess('1.1', "composer.json requires PHP $phpVersion");
        } else {
            $this->addError('1.1', "composer.json PHP version ($phpVersion) is not >= 8.4");
        }

        // Check Dockerfile
        $dockerfilePath = $this->projectRoot . '/Dockerfile';
        if (file_exists($dockerfilePath)) {
            $dockerfile = file_get_contents($dockerfilePath);
            if (preg_match('/php8\.[4-9]|php9\./i', $dockerfile)) {
                $this->addSuccess('1.1', 'Dockerfile uses PHP 8.4+');
            } else {
                $this->addWarning('1.1', 'Dockerfile may not be using PHP 8.4+');
            }
        }

        echo "\n";
    }

    /**
     * Phase 1.2: Validate Strict Types Declaration
     */
    private function validatePhase1_2_StrictTypes(): void
    {
        echo "📋 Phase 1.2: Strict Types Declaration\n";

        $phpFiles = [
            'config.php',
            'index.php',
            'search-api.php',
            'classes/Cache.php',
            'classes/Wiki.php',
            'classes/MarkdownParser.php',
            'classes/PDFexporter.php',
        ];

        $allHaveStrictTypes = true;

        foreach ($phpFiles as $file) {
            $fullPath = $this->projectRoot . '/' . $file;
            if (!file_exists($fullPath)) {
                $this->addWarning('1.2', "$file not found");
                continue;
            }

            $content = file_get_contents($fullPath);
            if (preg_match('/declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;/i', $content)) {
                $this->addSuccess('1.2', "$file has strict_types declaration");
            } else {
                $this->addError('1.2', "$file is missing strict_types declaration");
                $allHaveStrictTypes = false;
            }
        }

        if ($allHaveStrictTypes) {
            echo "  ✅ All core files have strict_types=1\n";
        }

        echo "\n";
    }

    /**
     * Phase 1.3: Validate JSON Caching (no serialize/unserialize)
     */
    private function validatePhase1_3_JSONCaching(): void
    {
        echo "📋 Phase 1.3: JSON Caching Implementation\n";

        $cachePath = $this->projectRoot . '/classes/Cache.php';
        if (!file_exists($cachePath)) {
            $this->addError('1.3', 'Cache.php not found');
            echo "\n";
            return;
        }

        $content = file_get_contents($cachePath);

        // Check for json_encode usage
        if (strpos($content, 'json_encode') !== false) {
            $this->addSuccess('1.3', 'Cache.php uses json_encode()');
        } else {
            $this->addError('1.3', 'Cache.php does not use json_encode()');
        }

        // Check for json_decode usage
        if (strpos($content, 'json_decode') !== false) {
            $this->addSuccess('1.3', 'Cache.php uses json_decode()');
        } else {
            $this->addError('1.3', 'Cache.php does not use json_decode()');
        }

        // Check that serialize is NOT used
        if (preg_match('/\bserialize\s*\(/i', $content)) {
            $this->addError('1.3', 'Cache.php still uses serialize() - security risk!');
        } else {
            $this->addSuccess('1.3', 'Cache.php does not use serialize()');
        }

        // Check that unserialize is NOT used
        if (preg_match('/\bunserialize\s*\(/i', $content)) {
            $this->addError('1.3', 'Cache.php still uses unserialize() - security risk!');
        } else {
            $this->addSuccess('1.3', 'Cache.php does not use unserialize()');
        }

        // Check for JSON_THROW_ON_ERROR flag
        if (strpos($content, 'JSON_THROW_ON_ERROR') !== false) {
            $this->addSuccess('1.3', 'Cache.php uses JSON_THROW_ON_ERROR for error handling');
        } else {
            $this->addWarning('1.3', 'Cache.php may not use JSON_THROW_ON_ERROR');
        }

        echo "\n";
    }

    /**
     * Phase 1.4: Validate Interface Implementation
     */
    private function validatePhase1_4_Interfaces(): void
    {
        echo "📋 Phase 1.4: Interface Implementation\n";

        $interfaces = [
            'classes/Interfaces/CacheInterface.php' => 'CacheInterface',
            'classes/Interfaces/MarkdownParserInterface.php' => 'MarkdownParserInterface',
        ];

        foreach ($interfaces as $file => $interfaceName) {
            $fullPath = $this->projectRoot . '/' . $file;
            if (file_exists($fullPath)) {
                $content = file_get_contents($fullPath);
                if (strpos($content, "interface $interfaceName") !== false) {
                    $this->addSuccess('1.4', "$interfaceName defined correctly");
                } else {
                    $this->addError('1.4', "$interfaceName not properly defined");
                }
            } else {
                $this->addError('1.4', "$file not found");
            }
        }

        // Validate Cache.php implements CacheInterface
        $cachePath = $this->projectRoot . '/classes/Cache.php';
        if (file_exists($cachePath)) {
            $content = file_get_contents($cachePath);
            if (preg_match('/class\s+Cache\s+implements\s+CacheInterface/i', $content)) {
                $this->addSuccess('1.4', 'Cache implements CacheInterface');
            } else {
                $this->addError('1.4', 'Cache does not implement CacheInterface');
            }
        }

        echo "\n";
    }

    /**
     * Phase 1.5: Validate Cache Class Type Hints
     */
    private function validatePhase1_5_CacheTypeHints(): void
    {
        echo "📋 Phase 1.5: Cache Class Type Hints\n";

        $cachePath = $this->projectRoot . '/classes/Cache.php';
        if (!file_exists($cachePath)) {
            $this->addError('1.5', 'Cache.php not found');
            echo "\n";
            return;
        }

        $content = file_get_contents($cachePath);

        // Check for namespace
        if (preg_match('/namespace\s+Wiki\s*;/i', $content)) {
            $this->addSuccess('1.5', 'Cache has namespace declaration');
        } else {
            $this->addWarning('1.5', 'Cache may not have namespace declaration');
        }

        // Check key method signatures
        $requiredMethods = [
            'get(string $key): mixed',
            'set(string $key, mixed $data',
            'has(string $key): bool',
            'delete(string $key): bool',
            'clear(): int',
            'cleanup(): int',
        ];

        foreach ($requiredMethods as $methodSignature) {
            if (strpos($content, $methodSignature) !== false) {
                $methodName = explode('(', $methodSignature)[0];
                $this->addSuccess('1.5', "Method $methodName has correct type hints");
            } else {
                $methodName = explode('(', $methodSignature)[0];
                $this->addWarning('1.5', "Method $methodName may not have correct type hints");
            }
        }

        // Check for typed properties
        if (preg_match('/private\s+string\s+\$cacheDir/i', $content)) {
            $this->addSuccess('1.5', 'Property $cacheDir is typed');
        } else {
            $this->addWarning('1.5', 'Property $cacheDir may not be typed');
        }

        if (preg_match('/private\s+int\s+\$defaultTtl/i', $content)) {
            $this->addSuccess('1.5', 'Property $defaultTtl is typed');
        } else {
            $this->addWarning('1.5', 'Property $defaultTtl may not be typed');
        }

        echo "\n";
    }

    /**
     * Phase 1.6: Validate Wiki Class Type Hints (Partial)
     */
    private function validatePhase1_6_WikiTypeHints(): void
    {
        echo "📋 Phase 1.6: Wiki Class Type Hints (Partial - 60%)\n";

        $wikiPath = $this->projectRoot . '/classes/Wiki.php';
        if (!file_exists($wikiPath)) {
            $this->addError('1.6', 'Wiki.php not found');
            echo "\n";
            return;
        }

        $content = file_get_contents($wikiPath);

        // Check for namespace
        if (preg_match('/namespace\s+Wiki\s*;/i', $content)) {
            $this->addSuccess('1.6', 'Wiki has namespace declaration');
        } else {
            $this->addWarning('1.6', 'Wiki may not have namespace declaration');
        }

        // Check for CacheInterface usage
        if (strpos($content, 'CacheInterface') !== false) {
            $this->addSuccess('1.6', 'Wiki uses CacheInterface');
        } else {
            $this->addWarning('1.6', 'Wiki may not use CacheInterface');
        }

        // Check some key methods that should be typed
        $typedMethods = [
            'getCurrentPath(): string',
            'sanitizePathEnhanced(string $path): string',
        ];

        foreach ($typedMethods as $methodSignature) {
            if (strpos($content, $methodSignature) !== false) {
                $methodName = explode('(', $methodSignature)[0];
                $this->addSuccess('1.6', "Method $methodName has type hints");
            } else {
                $methodName = explode('(', $methodSignature)[0];
                $this->addWarning('1.6', "Method $methodName may not have type hints yet");
            }
        }

        // Check for typed properties
        if (preg_match('/private\s+string\s+\$contentDir/i', $content)) {
            $this->addSuccess('1.6', 'Property $contentDir is typed');
        } else {
            $this->addWarning('1.6', 'Property $contentDir may not be typed');
        }

        echo "  ℹ️  Wiki class type hints were 60% complete, checking if now 100%...\n";

        // Check additional methods that should now be typed
        $additionalTypedMethods = [
            'search(string $query): array',
            'getPageHeadings(string $path): array',
            'getBreadcrumbs(string $currentPath): array'
        ];

        foreach ($additionalTypedMethods as $methodSignature) {
            if (strpos($content, $methodSignature) !== false) {
                $methodName = explode('(', $methodSignature)[0];
                $this->addSuccess('1.6', "Additional method $methodName now typed (100% complete)");
            }
        }

        echo "\n";
    }

    /**
     * Phase 2.1: Validate MarkdownParser Namespace & Types
     */
    private function validatePhase2_1_MarkdownParserNamespace(): void
    {
        echo "📋 Phase 2.1: MarkdownParser Namespace & Types\n";

        $parserPath = $this->projectRoot . '/classes/MarkdownParser.php';
        if (!file_exists($parserPath)) {
            $this->addError('2.1', 'MarkdownParser.php not found');
            echo "\n";
            return;
        }

        $content = file_get_contents($parserPath);

        // Check for namespace
        if (preg_match('/namespace\s+Wiki\s*;/i', $content)) {
            $this->addSuccess('2.1', 'MarkdownParser has namespace declaration');
        } else {
            $this->addError('2.1', 'MarkdownParser missing namespace declaration');
        }

        // Check for interface implementation
        if (preg_match('/class\s+MarkdownParser\s+implements\s+MarkdownParserInterface/i', $content)) {
            $this->addSuccess('2.1', 'MarkdownParser implements interface');
        } else {
            $this->addWarning('2.1', 'MarkdownParser may not implement interface');
        }

        // Check for type hints on key methods
        if (strpos($content, 'public static function parse(string $text): string') !== false) {
            $this->addSuccess('2.1', 'parse() method has type hints');
        } else {
            $this->addError('2.1', 'parse() method missing type hints');
        }

        if (strpos($content, 'public static function extractTitle(string $content): ?string') !== false) {
            $this->addSuccess('2.1', 'extractTitle() method has type hints');
        } else {
            $this->addError('2.1', 'extractTitle() method missing type hints');
        }

        echo "\n";
    }

    /**
     * Phase 2.2: Validate Namespace Usage in index.php and search-api.php
     */
    private function validatePhase2_2_NamespaceUsage(): void
    {
        echo "📋 Phase 2.2: Namespace Usage in Main Files\n";

        // Check index.php
        $indexPath = $this->projectRoot . '/index.php';
        if (file_exists($indexPath)) {
            $content = file_get_contents($indexPath);

            if (strpos($content, 'use Wiki\Wiki;') !== false) {
                $this->addSuccess('2.2', 'index.php uses Wiki namespace');
            } else {
                $this->addError('2.2', 'index.php does not use Wiki namespace');
            }

            if (strpos($content, 'use Wiki\Cache;') !== false) {
                $this->addSuccess('2.2', 'index.php uses Cache namespace');
            } else {
                $this->addError('2.2', 'index.php does not use Cache namespace');
            }
        } else {
            $this->addError('2.2', 'index.php not found');
        }

        // Check search-api.php
        $searchPath = $this->projectRoot . '/search-api.php';
        if (file_exists($searchPath)) {
            $content = file_get_contents($searchPath);

            if (strpos($content, 'use Wiki\Wiki;') !== false) {
                $this->addSuccess('2.2', 'search-api.php uses Wiki namespace');
            } else {
                $this->addError('2.2', 'search-api.php does not use Wiki namespace');
            }
        } else {
            $this->addError('2.2', 'search-api.php not found');
        }

        echo "\n";
    }

    /**
     * Phase 2.3: Validate Search Consolidation
     */
    private function validatePhase2_3_SearchConsolidation(): void
    {
        echo "📋 Phase 2.3: Search Consolidation\n";

        $searchPath = $this->projectRoot . '/search-api.php';
        if (!file_exists($searchPath)) {
            $this->addError('2.3', 'search-api.php not found');
            echo "\n";
            return;
        }

        $content = file_get_contents($searchPath);

        // Check that it uses Wiki::search()
        if (strpos($content, '$wiki->search($query)') !== false) {
            $this->addSuccess('2.3', 'search-api.php uses Wiki::search()');
        } else {
            $this->addError('2.3', 'search-api.php does not use Wiki::search()');
        }

        // Check that duplicate functions are removed
        $duplicateFunctions = ['searchAllFiles', 'searchInDir', 'searchInFile', 'createPagePath'];
        $allRemoved = true;

        foreach ($duplicateFunctions as $func) {
            if (preg_match('/function\s+' . $func . '\s*\(/i', $content)) {
                $this->addError('2.3', "Duplicate function $func still exists");
                $allRemoved = false;
            }
        }

        if ($allRemoved) {
            $this->addSuccess('2.3', 'All duplicate search functions removed');
        }

        // Check file size (should be much smaller now)
        $lines = count(file($searchPath));
        if ($lines < 100) {
            $this->addSuccess('2.3', "search-api.php is concise ($lines lines, was ~270)");
        } else {
            $this->addWarning('2.3', "search-api.php still large ($lines lines)");
        }

        echo "\n";
    }

    /**
     * Phase 2.4: Validate HTTP Caching Headers
     */
    private function validatePhase2_4_HTTPCaching(): void
    {
        echo "📋 Phase 2.4: HTTP Caching Headers\n";

        $indexPath = $this->projectRoot . '/index.php';
        if (!file_exists($indexPath)) {
            $this->addError('2.4', 'index.php not found');
            echo "\n";
            return;
        }

        $content = file_get_contents($indexPath);

        // Check for Last-Modified header
        if (strpos($content, "'Last-Modified: '") !== false || strpos($content, '"Last-Modified: "') !== false) {
            $this->addSuccess('2.4', 'Last-Modified header added');
        } else {
            $this->addError('2.4', 'Last-Modified header not found');
        }

        // Check for Cache-Control header
        if (strpos($content, 'Cache-Control:') !== false) {
            $this->addSuccess('2.4', 'Cache-Control header added');
        } else {
            $this->addError('2.4', 'Cache-Control header not found');
        }

        // Check for ETag header
        if (strpos($content, 'ETag:') !== false) {
            $this->addSuccess('2.4', 'ETag header added');
        } else {
            $this->addError('2.4', 'ETag header not found');
        }

        // Check for 304 Not Modified response
        if (strpos($content, '304 Not Modified') !== false) {
            $this->addSuccess('2.4', '304 Not Modified response implemented');
        } else {
            $this->addError('2.4', '304 Not Modified response not found');
        }

        // Check for If-Modified-Since handling
        if (strpos($content, 'HTTP_IF_MODIFIED_SINCE') !== false) {
            $this->addSuccess('2.4', 'If-Modified-Since check implemented');
        } else {
            $this->addError('2.4', 'If-Modified-Since check not found');
        }

        echo "\n";
    }

    private function addError(string $phase, string $message): void
    {
        $this->errors[] = ['phase' => $phase, 'message' => $message];
        echo "  ❌ ERROR: $message\n";
    }

    private function addWarning(string $phase, string $message): void
    {
        $this->warnings[] = ['phase' => $phase, 'message' => $message];
        echo "  ⚠️  WARNING: $message\n";
    }

    private function addSuccess(string $phase, string $message): void
    {
        $this->successes[] = ['phase' => $phase, 'message' => $message];
        echo "  ✅ $message\n";
    }

    private function printResults(): void
    {
        echo str_repeat('=', 70) . "\n";
        echo "📊 VALIDATION RESULTS\n";
        echo str_repeat('=', 70) . "\n\n";

        echo "✅ Successes: " . count($this->successes) . "\n";
        echo "⚠️  Warnings:  " . count($this->warnings) . "\n";
        echo "❌ Errors:    " . count($this->errors) . "\n\n";

        if (count($this->errors) === 0) {
            echo "🎉 All critical validations passed!\n";
            echo "Phase 1 optimizations are correctly implemented.\n\n";
            exit(0);
        } else {
            echo "⚠️  Some validations failed. Please review the errors above.\n\n";
            exit(1);
        }
    }
}

// Run validation
$validator = new PhaseValidator(__DIR__);
$validator->validate();
