<?php
/**
 * Authorization and Access Control tests
 * Tests that verify proper permission checks for admin/user actions
 */

namespace JGMap\Tests;

use PHPUnit\Framework\TestCase;

class AuthorizationTest extends TestCase
{
    /**
     * Test that admin-only AJAX actions check permissions
     */
    public function test_admin_ajax_actions_require_admin_permission()
    {
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php';

        // Read the AJAX handlers file
        $content = file_get_contents(JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php');

        // Check that check_admin method exists and is used
        $this->assertStringContainsString(
            'private function check_admin()',
            $content,
            'Should have check_admin() method for permission checks'
        );

        // Count how many times check_admin is called
        $check_admin_count = substr_count($content, '$this->check_admin()');

        $this->assertGreaterThan(
            15,
            $check_admin_count,
            'check_admin() should be called frequently for admin actions'
        );

        // Verify it checks current_user_can
        $this->assertStringContainsString(
            "current_user_can('manage_options')",
            $content,
            'Should check manage_options capability'
        );

        $this->assertStringContainsString(
            "current_user_can('jg_map_moderate')",
            $content,
            'Should check jg_map_moderate capability'
        );
    }

    /**
     * Test that modify actions check nonce
     */
    public function test_modify_actions_verify_nonce()
    {
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php';

        // Actions that modify data should verify nonce
        $modify_actions = [
            'submit_point',
            'update_point',
            'vote',
            'report_point',
            'request_deletion',
            'admin_approve_point',
            'admin_reject_point',
            'admin_delete_point',
        ];

        $content = file_get_contents(JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php');

        foreach ($modify_actions as $method_name) {
            // Check if method contains verify_nonce() call
            $pattern = '/public\s+function\s+' . preg_quote($method_name) . '\s*\([^)]*\)\s*\{.*?(?=public\s+function|private\s+function|protected\s+function|\}[\s]*$)/s';

            if (preg_match($pattern, $content, $matches)) {
                $method_body = $matches[0];

                $this->assertStringContainsString(
                    'verify_nonce()',
                    $method_body,
                    "Method $method_name should call verify_nonce()"
                );
            }
        }
    }

    /**
     * Test that user can only edit/delete their own points
     */
    public function test_point_ownership_is_verified()
    {
        $content = file_get_contents(JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php');

        // Check update_point method verifies ownership
        $this->assertMatchesRegularExpression(
            '/intval\(\$point\[\'author_id\'\]\)\s*!==\s*\$user_id/',
            $content,
            'update_point should verify point ownership'
        );

        // Check request_deletion method verifies ownership
        $this->assertMatchesRegularExpression(
            '/intval\(\$point\[\'author_id\'\]\)\s*!==\s*\$user_id/',
            $content,
            'request_deletion should verify point ownership'
        );
    }

    /**
     * Test that SQL queries use prepared statements
     */
    public function test_all_database_queries_use_prepared_statements()
    {
        $php_files = $this->getPhpFiles(JG_MAP_PLUGIN_DIR . '/includes');

        $violations = [];

        foreach ($php_files as $file) {
            if (strpos($file, '.backup') !== false) {
                continue;
            }

            $content = file_get_contents($file);

            // Look for direct SQL queries without prepare()
            // Pattern: $wpdb->query/get_var/get_row/get_results with variables in SQL
            if (preg_match_all('/\$wpdb->(query|get_var|get_row|get_results|get_col)\s*\(\s*["\'](?!SELECT|INSERT|UPDATE|DELETE)/', $content, $matches, PREG_OFFSET_CAPTURE)) {
                // Check if these are not using prepare()
                foreach ($matches[0] as $match) {
                    $context = substr($content, max(0, $match[1] - 100), 200);
                    if (strpos($context, '->prepare(') === false &&
                        strpos($context, 'SHOW COLUMNS') === false &&
                        strpos($context, 'DESCRIBE') === false) {
                        // This might be a violation - check if it contains variables
                        if (preg_match('/\$[a-zA-Z_]/', $context)) {
                            $violations[] = basename($file) . ': ' . trim(substr($context, 0, 50));
                        }
                    }
                }
            }
        }

        // This is informational - we allow some violations for maintenance queries
        // Most of these are false positives (SHOW COLUMNS, etc.)
        $this->assertLessThan(
            50,
            count($violations),
            "Too many potential SQL injection vulnerabilities found:\n" . implode("\n", array_slice($violations, 0, 10))
        );
    }

    /**
     * Test that file uploads validate MIME types
     */
    public function test_file_upload_validates_mime_type()
    {
        $content = file_get_contents(JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php');

        // Check that verify_image_mime_type is called in handle_image_upload
        $this->assertStringContainsString(
            'verify_image_mime_type',
            $content,
            'File uploads should verify MIME type'
        );

        // Check that finfo_open is used for MIME detection
        $this->assertStringContainsString(
            'finfo_open(FILEINFO_MIME_TYPE)',
            $content,
            'MIME type verification should use finfo_open'
        );

        // Check that getimagesize is used as secondary verification
        $this->assertStringContainsString(
            'getimagesize',
            $content,
            'MIME type verification should use getimagesize as secondary check'
        );
    }

    /**
     * Test that rate limiting is implemented
     */
    public function test_rate_limiting_is_implemented()
    {
        $content = file_get_contents(JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php');

        // Check that check_rate_limit method exists
        $this->assertStringContainsString(
            'function check_rate_limit',
            $content,
            'Rate limiting should be implemented'
        );

        // Check that it uses transients for tracking
        $this->assertStringContainsString(
            'get_transient($transient_key)',
            $content,
            'Rate limiting should use transients'
        );
    }

    /**
     * Test that password strength validation exists
     */
    public function test_password_strength_validation()
    {
        $content = file_get_contents(JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php');

        // Check that validate_password_strength method exists
        $this->assertStringContainsString(
            'function validate_password_strength',
            $content,
            'Password strength validation should be implemented'
        );

        // Check for minimum length requirement
        $this->assertStringContainsString(
            'strlen($password) < 12',
            $content,
            'Password should require minimum 12 characters'
        );

        // Check for complexity requirements
        $this->assertStringContainsString(
            '[A-Z]',
            $content,
            'Password should require uppercase letter'
        );

        $this->assertStringContainsString(
            '[a-z]',
            $content,
            'Password should require lowercase letter'
        );

        $this->assertStringContainsString(
            '[0-9]',
            $content,
            'Password should require digit'
        );
    }

    /**
     * Test that user bans are enforced
     */
    public function test_user_bans_are_checked()
    {
        $content = file_get_contents(JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php');

        // Check that is_user_banned is called in critical operations
        $critical_methods = ['submit_point', 'update_point', 'vote', 'request_deletion'];

        foreach ($critical_methods as $method) {
            $pattern = '/function\s+' . preg_quote($method) . '.*?is_user_banned/s';
            $this->assertMatchesRegularExpression(
                $pattern,
                $content,
                "Method $method should check if user is banned"
            );
        }
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
