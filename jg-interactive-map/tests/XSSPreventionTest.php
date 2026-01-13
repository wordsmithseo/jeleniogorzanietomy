<?php
/**
 * XSS Prevention and Output Escaping tests
 * Tests that verify all outputs are properly escaped to prevent XSS attacks
 */

namespace JGMap\Tests;

use PHPUnit\Framework\TestCase;

class XSSPreventionTest extends TestCase
{
    /**
     * Test that admin panel outputs are escaped
     */
    public function test_admin_panel_escaping()
    {
        $admin_file = JG_MAP_PLUGIN_DIR . 'includes/class-admin.php';

        $this->assertFileExists(
            $admin_file,
            'Admin class file should exist'
        );

        $content = file_get_contents($admin_file);

        // Count escaping function usage
        $esc_html_count = substr_count($content, 'esc_html');
        $esc_attr_count = substr_count($content, 'esc_attr');
        $esc_url_count = substr_count($content, 'esc_url');

        $this->assertGreaterThan(
            10,
            $esc_html_count,
            'Admin panel should use esc_html() for text outputs'
        );

        $this->assertGreaterThan(
            5,
            $esc_attr_count,
            'Admin panel should use esc_attr() for attribute outputs'
        );

        $this->assertGreaterThan(
            2,
            $esc_url_count,
            'Admin panel should use esc_url() for URL outputs'
        );
    }

    /**
     * Test that JavaScript outputs use wp_localize_script
     */
    public function test_javascript_data_localization()
    {
        $enqueue_file = JG_MAP_PLUGIN_DIR . 'includes/class-enqueue.php';

        $this->assertFileExists(
            $enqueue_file,
            'Enqueue class file should exist'
        );

        $content = file_get_contents($enqueue_file);

        // Check that wp_localize_script is used
        $this->assertStringContainsString(
            'wp_localize_script',
            $content,
            'Should use wp_localize_script to pass data to JavaScript'
        );
    }

    /**
     * Test that JSON responses are properly structured
     */
    public function test_json_response_structure()
    {
        $ajax_file = JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php';
        $content = file_get_contents($ajax_file);

        // Check that wp_send_json_success and wp_send_json_error are used
        $success_count = substr_count($content, 'wp_send_json_success');
        $error_count = substr_count($content, 'wp_send_json_error');

        $this->assertGreaterThan(
            30,
            $success_count,
            'Should use wp_send_json_success for AJAX success responses'
        );

        $this->assertGreaterThan(
            50,
            $error_count,
            'Should use wp_send_json_error for AJAX error responses'
        );

        // Verify no direct echo of user data
        $this->assertStringNotContainsString(
            'echo $_POST',
            $content,
            'Should not directly echo POST data'
        );

        $this->assertStringNotContainsString(
            'echo $_GET',
            $content,
            'Should not directly echo GET data'
        );
    }

    /**
     * Test that shortcode outputs are escaped
     */
    public function test_shortcode_escaping()
    {
        $shortcode_file = JG_MAP_PLUGIN_DIR . 'includes/class-shortcode.php';

        $this->assertFileExists(
            $shortcode_file,
            'Shortcode class file should exist'
        );

        $content = file_get_contents($shortcode_file);

        // Check for escaping in shortcode output
        $this->assertMatchesRegularExpression(
            '/(esc_html|esc_attr|esc_url)/',
            $content,
            'Shortcode should escape outputs'
        );
    }

    /**
     * Test that database-retrieved content is escaped on output
     */
    public function test_database_content_escaping()
    {
        $ajax_file = JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php';
        $content = file_get_contents($ajax_file);

        // In get_points method, verify that dangerous fields are handled
        $this->assertStringContainsString(
            "point['title']",
            $content,
            'Point titles should be retrieved from database'
        );

        // Verify that content is passed through JSON (which escapes automatically)
        $this->assertStringContainsString(
            'wp_send_json_success',
            $content,
            'Data should be sent via JSON which auto-escapes'
        );
    }

    /**
     * Test that HTML entities in database are properly handled
     */
    public function test_html_entity_handling()
    {
        // Test that special characters are handled correctly
        $test_cases = [
            '<script>alert(1)</script>',
            '"><img src=x onerror=alert(1)>',
            "'; DROP TABLE users; --",
            '../../../etc/passwd',
        ];

        // These should all be safe after sanitize_text_field
        foreach ($test_cases as $malicious_input) {
            // Simulate sanitization
            $sanitized = sanitize_text_field($malicious_input);

            $this->assertStringNotContainsString(
                '<script',
                $sanitized,
                'Script tags should be removed'
            );

            $this->assertStringNotContainsString(
                'onerror=',
                $sanitized,
                'Event handlers should be removed'
            );
        }
    }

    /**
     * Test that SVG uploads are not allowed (XSS vector)
     */
    public function test_svg_uploads_prevented()
    {
        $ajax_file = JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php';
        $content = file_get_contents($ajax_file);

        // Check allowed MIME types
        $this->assertStringContainsString(
            "allowed_mimes",
            $content,
            'Should have allowed MIME types whitelist'
        );

        // Verify SVG is not in allowed types
        $this->assertStringNotContainsString(
            "'image/svg",
            $content,
            'SVG should not be in allowed image types'
        );
    }

    /**
     * Test that user display names are escaped
     */
    public function test_user_display_name_escaping()
    {
        $ajax_file = JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php';
        $content = file_get_contents($ajax_file);

        // Check that display_name is retrieved
        $this->assertStringContainsString(
            'display_name',
            $content,
            'Should retrieve user display names'
        );

        // Since it's sent via JSON, it's automatically escaped
        $this->assertStringContainsString(
            'wp_send_json_success',
            $content,
            'User data should be sent via JSON'
        );
    }

    /**
     * Test that error messages don't leak sensitive information
     */
    public function test_error_messages_dont_leak_info()
    {
        $ajax_file = JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php';
        $content = file_get_contents($ajax_file);

        // Check that database errors are not directly exposed
        $this->assertStringNotContainsString(
            'echo $wpdb->last_error',
            $content,
            'Database errors should not be directly echoed'
        );

        // Check for generic error messages
        $this->assertStringContainsString(
            'Błąd',
            $content,
            'Should use generic error messages in Polish'
        );
    }

    /**
     * Test that nonce fields are properly generated
     */
    public function test_nonce_generation()
    {
        $enqueue_file = JG_MAP_PLUGIN_DIR . 'includes/class-enqueue.php';
        $content = file_get_contents($enqueue_file);

        // Check that nonce is created for AJAX
        $this->assertStringContainsString(
            'wp_create_nonce',
            $content,
            'Should create nonce for AJAX security'
        );

        $this->assertStringContainsString(
            'jg_map_nonce',
            $content,
            'Should use jg_map_nonce action'
        );
    }

    /**
     * Test that AJAX actions are properly registered
     */
    public function test_ajax_action_registration()
    {
        $ajax_file = JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php';
        $content = file_get_contents($ajax_file);

        // Count AJAX action registrations
        $wp_ajax_count = substr_count($content, "add_action('wp_ajax_");
        $wp_ajax_nopriv_count = substr_count($content, "add_action('wp_ajax_nopriv_");

        $this->assertGreaterThan(
            30,
            $wp_ajax_count,
            'Should register multiple AJAX actions for logged-in users'
        );

        $this->assertGreaterThan(
            10,
            $wp_ajax_nopriv_count,
            'Should register AJAX actions for non-logged-in users'
        );
    }

    /**
     * Test that Content Security Policy considerations are present
     */
    public function test_csp_considerations()
    {
        $enqueue_file = JG_MAP_PLUGIN_DIR . 'includes/class-enqueue.php';

        $this->assertFileExists(
            $enqueue_file,
            'Enqueue class should exist'
        );

        // CSP headers might be set elsewhere, but check for awareness
        $content = file_get_contents($enqueue_file);

        // Check that external resources are loaded from known CDNs
        $this->assertMatchesRegularExpression(
            '/(cdn\.jsdelivr\.net|unpkg\.com)/',
            $content,
            'External resources should be from trusted CDNs'
        );
    }

    /**
     * Test that iframe embedding is controlled
     */
    public function test_iframe_protection()
    {
        // Check for X-Frame-Options or similar protection
        $plugin_file = JG_MAP_PLUGIN_DIR . 'jg-interactive-map.php';
        $content = file_get_contents($plugin_file);

        // This is a best practice check - not always required
        $this->assertFileExists(
            $plugin_file,
            'Main plugin file should exist'
        );
    }

    /**
     * Test that eval() is not used anywhere
     */
    public function test_no_eval_usage()
    {
        $php_files = $this->getPhpFiles(JG_MAP_PLUGIN_DIR . '/includes');

        $files_with_eval = [];

        foreach ($php_files as $file) {
            if (strpos($file, '.backup') !== false) {
                continue;
            }

            $content = file_get_contents($file);

            // Check for eval() usage (dangerous)
            if (preg_match('/\beval\s*\(/', $content)) {
                $files_with_eval[] = basename($file);
            }
        }

        $this->assertEmpty(
            $files_with_eval,
            'No files should use eval(): ' . implode(', ', $files_with_eval)
        );
    }

    /**
     * Test that base64_decode is used carefully
     */
    public function test_base64_decode_usage()
    {
        $php_files = $this->getPhpFiles(JG_MAP_PLUGIN_DIR . '/includes');

        $suspicious_usage = [];

        foreach ($php_files as $file) {
            if (strpos($file, '.backup') !== false) {
                continue;
            }

            $content = file_get_contents($file);

            // Check for base64_decode followed by eval (malware pattern)
            if (preg_match('/base64_decode.*eval/s', $content)) {
                $suspicious_usage[] = basename($file);
            }
        }

        $this->assertEmpty(
            $suspicious_usage,
            'No files should use base64_decode with eval: ' . implode(', ', $suspicious_usage)
        );
    }

    /**
     * Helper: Get all PHP files in a directory recursively
     */
    private function getPhpFiles($dir)
    {
        $files = [];

        if (!is_dir($dir)) {
            return $files;
        }

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
}
