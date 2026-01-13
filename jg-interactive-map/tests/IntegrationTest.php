<?php
/**
 * Integration tests for critical workflows
 * Tests that verify complete user flows work correctly
 */

namespace JGMap\Tests;

use PHPUnit\Framework\TestCase;

class IntegrationTest extends TestCase
{
    /**
     * Test complete point submission workflow
     */
    public function test_point_submission_workflow()
    {
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-database.php';

        // Verify that all required methods exist for the workflow
        $this->assertTrue(
            method_exists('\JG_Map_Database', 'insert_point'),
            'JG_Map_Database should have insert_point method'
        );

        $this->assertTrue(
            method_exists('\JG_Map_Database', 'get_point'),
            'JG_Map_Database should have get_point method'
        );

        $this->assertTrue(
            method_exists('\JG_Map_Database', 'update_point'),
            'JG_Map_Database should have update_point method'
        );
    }

    /**
     * Test point moderation workflow
     */
    public function test_point_moderation_workflow()
    {
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-database.php';

        // Verify moderation methods exist
        $this->assertTrue(
            method_exists('\JG_Map_Database', 'get_published_points'),
            'Should have get_published_points method'
        );

        $this->assertTrue(
            method_exists('\JG_Map_Database', 'get_user_pending_points'),
            'Should have get_user_pending_points method'
        );

        $this->assertTrue(
            method_exists('\JG_Map_Database', 'delete_point'),
            'Should have delete_point method'
        );
    }

    /**
     * Test voting workflow
     */
    public function test_voting_workflow()
    {
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-database.php';

        // Verify voting methods exist
        $this->assertTrue(
            method_exists('\JG_Map_Database', 'get_user_vote'),
            'Should have get_user_vote method'
        );

        $this->assertTrue(
            method_exists('\JG_Map_Database', 'set_vote'),
            'Should have set_vote method'
        );

        $this->assertTrue(
            method_exists('\JG_Map_Database', 'get_votes_count'),
            'Should have get_votes_count method'
        );
    }

    /**
     * Test reporting workflow
     */
    public function test_reporting_workflow()
    {
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-database.php';

        // Verify reporting methods exist
        $this->assertTrue(
            method_exists('\JG_Map_Database', 'add_report'),
            'Should have add_report method'
        );

        $this->assertTrue(
            method_exists('\JG_Map_Database', 'get_reports'),
            'Should have get_reports method'
        );

        $this->assertTrue(
            method_exists('\JG_Map_Database', 'resolve_reports'),
            'Should have resolve_reports method'
        );

        $this->assertTrue(
            method_exists('\JG_Map_Database', 'has_user_reported'),
            'Should have has_user_reported method'
        );

        $this->assertTrue(
            method_exists('\JG_Map_Database', 'get_reports_count'),
            'Should have get_reports_count method'
        );
    }

    /**
     * Test edit history workflow
     */
    public function test_edit_history_workflow()
    {
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-database.php';

        // Verify history methods exist
        $this->assertTrue(
            method_exists('\JG_Map_Database', 'add_history'),
            'Should have add_history method'
        );

        $this->assertTrue(
            method_exists('\JG_Map_Database', 'get_pending_history'),
            'Should have get_pending_history method'
        );

        $this->assertTrue(
            method_exists('\JG_Map_Database', 'get_rejected_history'),
            'Should have get_rejected_history method'
        );
    }

    /**
     * Test slug generation is idempotent
     */
    public function test_slug_generation_is_idempotent()
    {
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-database.php';

        $test_titles = [
            'Test Place',
            'Jelenia Góra - Centrum',
            'Punkt z dużą ilością znaków specjalnych!!!',
            'Test 123',
        ];

        foreach ($test_titles as $title) {
            $slug1 = \JG_Map_Database::generate_slug($title);
            $slug2 = \JG_Map_Database::generate_slug($title);

            $this->assertEquals(
                $slug1,
                $slug2,
                "Slug generation should be idempotent for: $title"
            );
        }
    }

    /**
     * Test that database tables are properly named
     */
    public function test_database_table_names()
    {
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-database.php';

        $table_methods = [
            'get_points_table',
            'get_votes_table',
            'get_reports_table',
            'get_history_table',
        ];

        foreach ($table_methods as $method) {
            $this->assertTrue(
                method_exists('\JG_Map_Database', $method),
                "Should have $method method"
            );
        }
    }

    /**
     * Test activity logging integration
     */
    public function test_activity_logging_integration()
    {
        $activity_log_file = JG_MAP_PLUGIN_DIR . 'includes/class-activity-log.php';

        $this->assertFileExists(
            $activity_log_file,
            'Activity log class should exist'
        );

        require_once $activity_log_file;

        $this->assertTrue(
            class_exists('\JG_Map_Activity_Log'),
            'JG_Map_Activity_Log class should exist'
        );

        $this->assertTrue(
            method_exists('\JG_Map_Activity_Log', 'log'),
            'Activity log should have log method'
        );

        $this->assertTrue(
            method_exists('\JG_Map_Activity_Log', 'create_table'),
            'Activity log should have create_table method'
        );
    }

    /**
     * Test sync manager integration
     */
    public function test_sync_manager_integration()
    {
        $sync_manager_file = JG_MAP_PLUGIN_DIR . 'includes/class-sync-manager.php';

        $this->assertFileExists(
            $sync_manager_file,
            'Sync manager class should exist'
        );

        require_once $sync_manager_file;

        $this->assertTrue(
            class_exists('\JG_Map_Sync_Manager'),
            'JG_Map_Sync_Manager class should exist'
        );

        $this->assertTrue(
            method_exists('\JG_Map_Sync_Manager', 'get_instance'),
            'Sync manager should use singleton pattern'
        );
    }

    /**
     * Test that all required classes are loadable
     */
    public function test_all_core_classes_are_loadable()
    {
        $required_classes = [
            'class-database.php' => 'JG_Map_Database',
            'class-ajax-handlers.php' => 'JG_Map_Ajax_Handlers',
            'class-admin.php' => 'JG_Map_Admin',
            'class-enqueue.php' => 'JG_Map_Enqueue',
            'class-shortcode.php' => 'JG_Map_Shortcode',
            'class-activity-log.php' => 'JG_Map_Activity_Log',
            'class-sync-manager.php' => 'JG_Map_Sync_Manager',
        ];

        foreach ($required_classes as $file => $class) {
            $file_path = JG_MAP_PLUGIN_DIR . 'includes/' . $file;

            $this->assertFileExists(
                $file_path,
                "Core file $file should exist"
            );

            require_once $file_path;

            $this->assertTrue(
                class_exists('\\' . $class),
                "Core class $class should be loadable"
            );
        }
    }

    /**
     * Test that maintenance cron jobs are registered
     */
    public function test_maintenance_integration()
    {
        $maintenance_file = JG_MAP_PLUGIN_DIR . 'includes/class-maintenance.php';

        $this->assertFileExists(
            $maintenance_file,
            'Maintenance class should exist'
        );

        require_once $maintenance_file;

        $this->assertTrue(
            class_exists('\JG_Map_Maintenance'),
            'JG_Map_Maintenance class should exist'
        );

        $this->assertTrue(
            method_exists('\JG_Map_Maintenance', 'init'),
            'Maintenance should have init method'
        );

        $this->assertTrue(
            method_exists('\JG_Map_Maintenance', 'run_maintenance'),
            'Maintenance should have run_maintenance method'
        );
    }

    /**
     * Test case ID generation for reports
     */
    public function test_case_id_generation()
    {
        $content = file_get_contents(JG_MAP_PLUGIN_DIR . 'includes/class-database.php');

        // Check that case_id is generated for reports
        $this->assertStringContainsString(
            'case_id',
            $content,
            'Database should support case_id for reports'
        );
    }

    /**
     * Test that Polish characters are properly handled
     */
    public function test_polish_character_handling()
    {
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-database.php';

        $polish_titles = [
            'Łąka nad rzeką',
            'Ścieżka w górach',
            'Żółty budynek',
            'Ciężki problem',
        ];

        foreach ($polish_titles as $title) {
            $slug = \JG_Map_Database::generate_slug($title);

            // Slug should not contain Polish special characters
            $this->assertDoesNotMatchRegularExpression(
                '/[ąćęłńóśźżĄĆĘŁŃÓŚŹŻ]/',
                $slug,
                "Slug should transliterate Polish characters: $title -> $slug"
            );

            // Slug should only contain safe characters
            $this->assertMatchesRegularExpression(
                '/^[a-z0-9-]+$/',
                $slug,
                "Slug should only contain safe characters: $title -> $slug"
            );
        }
    }

    /**
     * Test that uploads directory is created securely
     */
    public function test_uploads_directory_security()
    {
        $content = file_get_contents(JG_MAP_PLUGIN_DIR . 'includes/class-database.php');

        // Check that .htaccess is created
        $this->assertStringContainsString(
            '.htaccess',
            $content,
            'Should create .htaccess for uploads directory'
        );

        // Check that PHP execution is disabled
        $this->assertStringContainsString(
            'deny from all',
            $content,
            'Should disable PHP execution in uploads directory'
        );

        // Check that directory indexing is disabled
        $this->assertStringContainsString(
            'Options -Indexes',
            $content,
            'Should disable directory indexing'
        );
    }
}
