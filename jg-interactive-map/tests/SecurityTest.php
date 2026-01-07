<?php
/**
 * Security tests for JG Interactive Map plugin
 */

namespace JGMap\Tests;

use PHPUnit\Framework\TestCase;

class SecurityTest extends TestCase
{
    /**
     * Test that all PHP files have ABSPATH check
     */
    public function test_all_php_files_have_abspath_check()
    {
        $plugin_dir = JG_MAP_PLUGIN_DIR;
        $php_files = $this->getPhpFiles($plugin_dir);

        $files_without_check = [];

        foreach ($php_files as $file) {
            // Skip test files and vendor directory
            if (strpos($file, '/tests/') !== false || strpos($file, '/vendor/') !== false) {
                continue;
            }

            $content = file_get_contents($file);

            // Check for ABSPATH protection
            if (strpos($content, "defined('ABSPATH')") === false &&
                strpos($content, 'defined("ABSPATH")') === false) {
                $files_without_check[] = str_replace($plugin_dir, '', $file);
            }
        }

        $this->assertEmpty(
            $files_without_check,
            "Files without ABSPATH check:\n" . implode("\n", $files_without_check)
        );
    }

    /**
     * Test slug generation prevents directory traversal
     */
    public function test_slug_generation_prevents_directory_traversal()
    {
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-database.php';

        // Test various directory traversal attempts
        $malicious_inputs = [
            '../../../etc/passwd',
            '..\\..\\..\\windows\\system32',
            'test/../../../sensitive',
            './../../config',
        ];

        foreach ($malicious_inputs as $input) {
            $slug = \JG_Map_Database::generate_slug($input);

            // Should not contain .. or path separators
            $this->assertStringNotContainsString('..', $slug);
            $this->assertStringNotContainsString('/', $slug);
            $this->assertStringNotContainsString('\\', $slug);

            // Should only contain safe characters
            $this->assertMatchesRegularExpression('/^[a-z0-9-]*$/', $slug);
        }
    }

    /**
     * Test slug generation removes XSS attempts
     */
    public function test_slug_generation_removes_xss()
    {
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-database.php';

        $xss_attempts = [
            '<script>alert("XSS")</script>',
            '"><script>alert(1)</script>',
            'javascript:alert(1)',
            '<img src=x onerror=alert(1)>',
            '<svg onload=alert(1)>',
        ];

        foreach ($xss_attempts as $attempt) {
            $slug = \JG_Map_Database::generate_slug($attempt);

            // Should not contain any HTML/JavaScript special characters
            $this->assertStringNotContainsString('<', $slug);
            $this->assertStringNotContainsString('>', $slug);
            $this->assertStringNotContainsString('(', $slug);
            $this->assertStringNotContainsString(')', $slug);

            // Should only contain safe characters (lowercase letters, numbers, hyphens)
            // The transformation makes malicious content harmless (e.g., "<script>" becomes "script")
            $this->assertMatchesRegularExpression('/^[a-z0-9-]*$/', $slug);
        }
    }

    /**
     * Test slug generation handles SQL injection attempts
     */
    public function test_slug_generation_removes_sql_injection()
    {
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-database.php';

        $sql_injection_attempts = [
            "'; DROP TABLE users; --",
            "1' OR '1'='1",
            "admin'--",
            "1' UNION SELECT NULL--",
        ];

        foreach ($sql_injection_attempts as $attempt) {
            $slug = \JG_Map_Database::generate_slug($attempt);

            // Should not contain SQL special characters
            $this->assertStringNotContainsString("'", $slug);
            $this->assertStringNotContainsString('"', $slug);
            $this->assertStringNotContainsString(';', $slug);
            $this->assertStringNotContainsString('--', $slug);

            // Should only contain safe characters
            $this->assertMatchesRegularExpression('/^[a-z0-9-]*$/', $slug);
        }
    }

    /**
     * Test that category keys are safe
     */
    public function test_category_keys_are_safe()
    {
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php';

        $categories = \JG_Map_Ajax_Handlers::get_report_categories();

        foreach (array_keys($categories) as $key) {
            // Category keys should only contain safe characters
            // Polish UTF-8 letters are allowed as they're safe in PHP array keys
            $this->assertMatchesRegularExpression(
                '/^[\p{L}a-z0-9_]+$/u',
                $key,
                "Category key '$key' contains unsafe characters"
            );

            // Should not contain SQL or XSS dangerous characters
            $this->assertStringNotContainsString("'", $key);
            $this->assertStringNotContainsString('"', $key);
            $this->assertStringNotContainsString('<', $key);
            $this->assertStringNotContainsString('>', $key);
            $this->assertStringNotContainsString(';', $key);
        }
    }

    /**
     * Helper: Get all PHP files in a directory recursively
     */
    private function getPhpFiles($dir)
    {
        $files = [];
        $items = scandir($dir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;

            if (is_dir($path)) {
                $files = array_merge($files, $this->getPhpFiles($path));
            } elseif (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
                $files[] = $path;
            }
        }

        return $files;
    }

    /**
     * Test for hardcoded credentials or API keys
     */
    public function test_no_hardcoded_credentials()
    {
        $plugin_dir = JG_MAP_PLUGIN_DIR;
        $php_files = $this->getPhpFiles($plugin_dir);

        $suspicious_patterns = [
            '/password\s*=\s*["\'][^"\']+["\']/i',
            '/api[_-]?key\s*=\s*["\'][^"\']+["\']/i',
            '/secret\s*=\s*["\'][^"\']+["\']/i',
            '/token\s*=\s*["\'][a-zA-Z0-9]{20,}["\']/i',
        ];

        $findings = [];

        foreach ($php_files as $file) {
            // Skip test files and vendor directory
            if (strpos($file, '/tests/') !== false || strpos($file, '/vendor/') !== false) {
                continue;
            }

            $content = file_get_contents($file);
            $filename = str_replace($plugin_dir, '', $file);

            foreach ($suspicious_patterns as $pattern) {
                if (preg_match($pattern, $content, $matches)) {
                    // Exclude obvious false positives
                    if (strpos($matches[0], 'example') === false &&
                        strpos($matches[0], 'your_') === false) {
                        $findings[] = "$filename: {$matches[0]}";
                    }
                }
            }
        }

        $this->assertEmpty(
            $findings,
            "Potential hardcoded credentials found:\n" . implode("\n", $findings)
        );
    }
}
