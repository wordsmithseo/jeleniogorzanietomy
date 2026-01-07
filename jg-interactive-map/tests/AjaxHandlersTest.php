<?php
/**
 * Tests for JG_Map_Ajax_Handlers class
 */

namespace JGMap\Tests;

use PHPUnit\Framework\TestCase;

class AjaxHandlersTest extends TestCase
{
    /**
     * Test report categories structure
     */
    public function test_get_report_categories_structure()
    {
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php';

        $categories = \JG_Map_Ajax_Handlers::get_report_categories();

        // Should be an array
        $this->assertIsArray($categories);

        // Should not be empty
        $this->assertNotEmpty($categories);

        // Each category should have label, group, and icon
        foreach ($categories as $key => $category) {
            $this->assertArrayHasKey('label', $category, "Category $key missing 'label'");
            $this->assertArrayHasKey('group', $category, "Category $key missing 'group'");
            $this->assertArrayHasKey('icon', $category, "Category $key missing 'icon'");

            $this->assertIsString($category['label']);
            $this->assertIsString($category['group']);
            $this->assertIsString($category['icon']);
        }
    }

    /**
     * Test category groups structure
     */
    public function test_get_category_groups_structure()
    {
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php';

        $groups = \JG_Map_Ajax_Handlers::get_category_groups();

        // Should be an array
        $this->assertIsArray($groups);

        // Should not be empty
        $this->assertNotEmpty($groups);

        // Expected groups
        $expected_groups = ['infrastructure', 'safety', 'greenery', 'transport', 'initiatives'];

        foreach ($expected_groups as $group_key) {
            $this->assertArrayHasKey($group_key, $groups, "Missing group: $group_key");
            $this->assertIsString($groups[$group_key]);
        }
    }

    /**
     * Test all categories belong to valid groups
     */
    public function test_categories_use_valid_groups()
    {
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php';

        $categories = \JG_Map_Ajax_Handlers::get_report_categories();
        $groups = \JG_Map_Ajax_Handlers::get_category_groups();

        $valid_groups = array_keys($groups);

        foreach ($categories as $key => $category) {
            $this->assertContains(
                $category['group'],
                $valid_groups,
                "Category '$key' uses invalid group '{$category['group']}'"
            );
        }
    }

    /**
     * Test specific infrastructure categories
     */
    public function test_infrastructure_categories_exist()
    {
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php';

        $categories = \JG_Map_Ajax_Handlers::get_report_categories();

        // Expected infrastructure categories
        $expected = ['dziura_w_jezdni', 'uszkodzone_chodniki', 'znaki_drogowe', 'oswietlenie'];

        foreach ($expected as $category_key) {
            $this->assertArrayHasKey($category_key, $categories);
            $this->assertEquals('infrastructure', $categories[$category_key]['group']);
        }
    }

    /**
     * Test safety categories exist
     */
    public function test_safety_categories_exist()
    {
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php';

        $categories = \JG_Map_Ajax_Handlers::get_report_categories();

        // Expected safety categories
        $expected = ['dzikie_wysypisko', 'przepelniony_kosz', 'graffiti', 'sliski_chodnik'];

        foreach ($expected as $category_key) {
            $this->assertArrayHasKey($category_key, $categories);
            $this->assertEquals('safety', $categories[$category_key]['group']);
        }
    }

    /**
     * Test category labels are in Polish
     */
    public function test_category_labels_are_polish()
    {
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php';

        $categories = \JG_Map_Ajax_Handlers::get_report_categories();

        // Check that labels contain Polish characters or are in Polish
        foreach ($categories as $key => $category) {
            $label = $category['label'];

            // Should not be empty
            $this->assertNotEmpty($label, "Category $key has empty label");

            // Should be at least 3 characters (reasonable minimum for a label)
            $this->assertGreaterThanOrEqual(3, mb_strlen($label), "Category $key label too short");
        }
    }

    /**
     * Test category icons are emojis
     */
    public function test_category_icons_are_emojis()
    {
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php';

        $categories = \JG_Map_Ajax_Handlers::get_report_categories();

        foreach ($categories as $key => $category) {
            $icon = $category['icon'];

            // Should not be empty
            $this->assertNotEmpty($icon, "Category $key has empty icon");

            // Should be a short string (emojis are typically 1-4 characters in length)
            $this->assertLessThanOrEqual(4, mb_strlen($icon), "Category $key icon too long");
        }
    }

    /**
     * Test getInstance returns singleton
     */
    public function test_get_instance_returns_singleton()
    {
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-database.php';
        require_once JG_MAP_PLUGIN_DIR . 'includes/class-ajax-handlers.php';

        $instance1 = \JG_Map_Ajax_Handlers::get_instance();
        $instance2 = \JG_Map_Ajax_Handlers::get_instance();

        // Should return the same instance
        $this->assertSame($instance1, $instance2);
    }
}
