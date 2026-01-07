<?php
/**
 * Tests for JG_Map_Database class
 */

namespace JGMap\Tests;

use PHPUnit\Framework\TestCase;

class DatabaseTest extends TestCase
{
    /**
     * Test slug generation with Polish characters
     */
    public function test_generate_slug_with_polish_characters()
    {
        // Mock the database class
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-database.php';

        // Test Polish characters transliteration
        $title = 'Kawiarnia Łąka - Świętokrzyska 123';
        $slug = \JG_Map_Database::generate_slug($title);

        // Should convert Polish characters to Latin
        $this->assertEquals('kawiarnia-laka-swietokrzyska-123', $slug);
    }

    /**
     * Test slug generation with special characters
     */
    public function test_generate_slug_with_special_characters()
    {
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-database.php';

        $title = 'Test!@#$%^&*()_+{}[]|;:"<>?,./';
        $slug = \JG_Map_Database::generate_slug($title);

        // Should remove special characters and replace with hyphens
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $slug);
        $this->assertStringNotContainsString('!', $slug);
        $this->assertStringNotContainsString('@', $slug);
    }

    /**
     * Test slug length limitation
     */
    public function test_generate_slug_length_limit()
    {
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-database.php';

        // Create a very long title
        $title = str_repeat('bardzo długi tytuł miejsca ', 20);
        $slug = \JG_Map_Database::generate_slug($title);

        // Should be limited to 200 characters
        $this->assertLessThanOrEqual(200, strlen($slug));
    }

    /**
     * Test slug doesn't start or end with hyphen
     */
    public function test_generate_slug_no_leading_trailing_hyphens()
    {
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-database.php';

        $title = '---Test Title---';
        $slug = \JG_Map_Database::generate_slug($title);

        // Should not start or end with hyphen
        $this->assertStringStartsNotWith('-', $slug);
        $this->assertStringEndsNotWith('-', $slug);
    }

    /**
     * Test table name generation
     */
    public function test_get_points_table()
    {
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-database.php';

        // Mock wpdb
        global $wpdb;
        $wpdb = new \stdClass();
        $wpdb->prefix = 'wp_';

        $table = \JG_Map_Database::get_points_table();
        $this->assertEquals('wp_jg_map_points', $table);
    }

    /**
     * Test votes table name generation
     */
    public function test_get_votes_table()
    {
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-database.php';

        global $wpdb;
        $wpdb = new \stdClass();
        $wpdb->prefix = 'wp_';

        $table = \JG_Map_Database::get_votes_table();
        $this->assertEquals('wp_jg_map_votes', $table);
    }

    /**
     * Test reports table name generation
     */
    public function test_get_reports_table()
    {
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-database.php';

        global $wpdb;
        $wpdb = new \stdClass();
        $wpdb->prefix = 'wp_';

        $table = \JG_Map_Database::get_reports_table();
        $this->assertEquals('wp_jg_map_reports', $table);
    }

    /**
     * Test history table name generation
     */
    public function test_get_history_table()
    {
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-database.php';

        global $wpdb;
        $wpdb = new \stdClass();
        $wpdb->prefix = 'wp_';

        $table = \JG_Map_Database::get_history_table();
        $this->assertEquals('wp_jg_map_history', $table);
    }

    /**
     * Test relevance votes table name generation
     */
    public function test_get_relevance_votes_table()
    {
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-database.php';

        global $wpdb;
        $wpdb = new \stdClass();
        $wpdb->prefix = 'wp_';

        $table = \JG_Map_Database::get_relevance_votes_table();
        $this->assertEquals('wp_jg_map_relevance_votes', $table);
    }
}
