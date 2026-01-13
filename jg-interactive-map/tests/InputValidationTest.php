<?php
/**
 * Input Validation and Sanitization tests
 * Tests that verify all user inputs are properly validated and sanitized
 */

namespace JGMap\Tests;

use PHPUnit\Framework\TestCase;

class InputValidationTest extends TestCase
{
    /**
     * Test that all $_POST inputs are sanitized
     */
    public function test_post_inputs_are_sanitized()
    {
        $content = file_get_contents(JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php');

        // Find all $_POST accesses
        preg_match_all('/\$_POST\s*\[\s*[\'"]([^\'"]+)[\'"]\s*\]/', $content, $matches);

        $unsanitized_posts = [];

        foreach ($matches[0] as $index => $post_access) {
            $key = $matches[1][$index];

            // Find context around this $_POST access (200 chars)
            $pos = strpos($content, $post_access);
            $context = substr($content, $pos, 200);

            // Check if followed by sanitization functions
            $sanitization_functions = [
                'sanitize_text_field',
                'sanitize_email',
                'sanitize_textarea_field',
                'intval',
                'floatval',
                'esc_url_raw',
                'wp_kses_post',
                'absint',
                'isset',
                '??'
            ];

            $is_sanitized = false;
            foreach ($sanitization_functions as $func) {
                if (strpos($context, $func) !== false) {
                    $is_sanitized = true;
                    break;
                }
            }

            // Special cases that don't need sanitization
            $special_cases = [
                '_ajax_nonce',       // Used for nonce verification
                'images',            // Handled by wp_handle_upload
                'facebook_url',      // Normalized by normalize_social_url
                'instagram_url',     // Normalized by normalize_social_url
                'linkedin_url',      // Normalized by normalize_social_url
                'tiktok_url',        // Normalized by normalize_social_url
                'password',          // Handled by wp_set_password
                'honeypot',          // Honeypot field for spam protection
                'admin_delete',      // Boolean flag
                'is_unique',         // Boolean flag
            ];

            if (!$is_sanitized && !in_array($key, $special_cases)) {
                $unsanitized_posts[] = $key;
            }
        }

        $this->assertEmpty(
            $unsanitized_posts,
            "Unsanitized \$_POST inputs found: " . implode(', ', array_unique($unsanitized_posts))
        );
    }

    /**
     * Test that email inputs are validated
     */
    public function test_email_validation()
    {
        $content = file_get_contents(JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php');

        // Check that emails are validated with is_email() or filter_var()
        $this->assertMatchesRegularExpression(
            '/(is_email|FILTER_VALIDATE_EMAIL)/',
            $content,
            'Email validation should use is_email() or filter_var()'
        );
    }

    /**
     * Test that URLs are validated
     */
    public function test_url_validation()
    {
        $content = file_get_contents(JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php');

        // Check that URLs are validated
        $this->assertMatchesRegularExpression(
            '/(FILTER_VALIDATE_URL|esc_url_raw)/',
            $content,
            'URL validation should use filter_var() or esc_url_raw()'
        );
    }

    /**
     * Test that numeric inputs are type-cast
     */
    public function test_numeric_inputs_are_typecast()
    {
        $content = file_get_contents(JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php');

        // Count intval and floatval usage
        $intval_count = substr_count($content, 'intval(');
        $floatval_count = substr_count($content, 'floatval(');
        $absint_count = substr_count($content, 'absint(');

        $this->assertGreaterThan(
            50,
            $intval_count + $absint_count,
            'Should have sufficient integer type-casting for numeric inputs'
        );

        $this->assertGreaterThan(
            5,
            $floatval_count,
            'Should have float type-casting for decimal inputs (lat/lng)'
        );
    }

    /**
     * Test that rich text content is sanitized with wp_kses_post
     */
    public function test_rich_text_sanitization()
    {
        $content = file_get_contents(JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php');

        // Check that wp_kses_post is used for content fields
        $this->assertStringContainsString(
            'wp_kses_post',
            $content,
            'Rich text content should be sanitized with wp_kses_post'
        );

        // Verify it's used on content field
        $this->assertMatchesRegularExpression(
            '/wp_kses_post\s*\(\s*\$_POST\s*\[\s*[\'"]content[\'"]\s*\]/',
            $content,
            'Content field should be sanitized with wp_kses_post'
        );
    }

    /**
     * Test that category validation uses whitelist
     */
    public function test_category_whitelist_validation()
    {
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php';

        $content = file_get_contents(JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php');

        // Check that category is validated against get_report_categories()
        $this->assertStringContainsString(
            'get_report_categories()',
            $content,
            'Category validation should use get_report_categories() whitelist'
        );

        $this->assertStringContainsString(
            'in_array($category,',
            $content,
            'Category should be validated with in_array against valid categories'
        );
    }

    /**
     * Test that status fields use whitelist validation
     */
    public function test_status_whitelist_validation()
    {
        $content = file_get_contents(JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php');

        // Check that status values are validated with in_array
        $this->assertMatchesRegularExpression(
            '/in_array\s*\(\s*\$[a-zA-Z_]+\s*,\s*array\s*\(/',
            $content,
            'Status values should be validated with in_array whitelist'
        );
    }

    /**
     * Test that file size limits are enforced
     */
    public function test_file_size_limits()
    {
        $content = file_get_contents(JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php');

        // Check that MAX_FILE_SIZE is defined
        $this->assertStringContainsString(
            'MAX_FILE_SIZE',
            $content,
            'File size limit should be defined'
        );

        // Check that file size is validated
        $this->assertStringContainsString(
            "files['size']",
            $content,
            'File size should be checked before upload'
        );

        // Check for 2MB limit
        $this->assertStringContainsString(
            '2 * 1024 * 1024',
            $content,
            'File size limit should be 2MB'
        );
    }

    /**
     * Test that image dimensions are limited
     */
    public function test_image_dimension_limits()
    {
        $content = file_get_contents(JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php');

        // Check that MAX_DIMENSION is defined
        $this->assertStringContainsString(
            'MAX_DIMENSION',
            $content,
            'Image dimension limit should be defined'
        );

        // Check that images are resized
        $this->assertStringContainsString(
            'resize_image_if_needed',
            $content,
            'Images should be resized to limit dimensions'
        );

        // Check for 800px limit
        $this->assertStringContainsString(
            '$MAX_DIMENSION = 800',
            $content,
            'Image dimension limit should be 800px'
        );
    }

    /**
     * Test that phone number validation exists
     */
    public function test_phone_number_validation()
    {
        $content = file_get_contents(JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php');

        // Check that phone numbers are validated with regex
        $this->assertStringContainsString(
            'preg_match',
            $content,
            'Should use preg_match for validation'
        );

        $this->assertStringContainsString(
            '$phone',
            $content,
            'Phone number validation should check $phone variable'
        );
    }

    /**
     * Test that duplicate report detection works
     */
    public function test_duplicate_report_detection()
    {
        $content = file_get_contents(JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php');

        // Check that nearby reports are detected using Haversine formula
        $this->assertStringContainsString(
            'Haversine',
            $content,
            'Duplicate detection should use Haversine formula for radius check'
        );

        // Check for 50m radius
        $this->assertStringContainsString(
            '$radius = 50',
            $content,
            'Duplicate detection should check 50m radius'
        );
    }

    /**
     * Test that monthly upload limits are enforced
     */
    public function test_monthly_upload_limits()
    {
        $content = file_get_contents(JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php');

        // Check that get_monthly_photo_usage is called
        $this->assertStringContainsString(
            'get_monthly_photo_usage',
            $content,
            'Monthly upload limits should be checked'
        );

        // Check for 100MB limit
        $this->assertStringContainsString(
            'MONTHLY_LIMIT_MB = 100',
            $content,
            'Monthly upload limit should be 100MB'
        );

        // Check that limit is enforced
        $this->assertMatchesRegularExpression(
            '/if\s*\(\s*\$used_mb\s*>=\s*\$limit_mb/',
            $content,
            'Monthly upload limit should be enforced'
        );
    }

    /**
     * Test that daily submission limits are enforced
     */
    public function test_daily_submission_limits()
    {
        $content = file_get_contents(JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php');

        // Check that check_daily_limit is called
        $this->assertStringContainsString(
            'check_daily_limit',
            $content,
            'Daily submission limits should be checked'
        );

        // Check for limits configuration
        $this->assertMatchesRegularExpression(
            '/[\'"]places[\'"]\s*=>\s*5/',
            $content,
            'Should have 5 daily limit for places'
        );

        $this->assertMatchesRegularExpression(
            '/[\'"]reports[\'"]\s*=>\s*5/',
            $content,
            'Should have 5 daily limit for reports'
        );
    }

    /**
     * Test that IP addresses are properly captured
     */
    public function test_ip_address_capture()
    {
        $content = file_get_contents(JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php');

        // Check that get_user_ip method exists
        $this->assertStringContainsString(
            'function get_user_ip',
            $content,
            'Should have get_user_ip method for tracking'
        );

        // Check that CloudFlare IPs are handled
        $this->assertStringContainsString(
            'HTTP_CF_CONNECTING_IP',
            $content,
            'Should handle CloudFlare IP headers'
        );
    }

    /**
     * Test social media URL normalization
     */
    public function test_social_media_url_normalization()
    {
        $content = file_get_contents(JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php');

        // Check that normalize_social_url method exists
        $this->assertStringContainsString(
            'normalize_social_url',
            $content,
            'Should have normalize_social_url method'
        );
    }
}
