<?php
/**
 * Testy jednostkowe – klasa JG_Map_Ajax_Handlers.
 *
 * Testują metody pomocnicze i statyczne kategorie.
 */

namespace JGMap\Tests\Unit;

use PHPUnit\Framework\TestCase;

class AjaxHandlersTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists('JG_Map_Ajax_Handlers')) {
            require_once dirname(__DIR__, 2) . '/includes/class-ajax-handlers.php';
        }
    }

    // ─── Kategorie ───────────────────────────────────────────────────

    public function test_get_menu_categories_returns_array(): void
    {
        $categories = \JG_Map_Ajax_Handlers::get_menu_categories();
        $this->assertIsArray($categories);
    }

    public function test_get_place_categories_returns_array(): void
    {
        $categories = \JG_Map_Ajax_Handlers::get_place_categories();
        $this->assertIsArray($categories);
        $this->assertNotEmpty($categories);
    }

    public function test_get_curiosity_categories_returns_array(): void
    {
        $categories = \JG_Map_Ajax_Handlers::get_curiosity_categories();
        $this->assertIsArray($categories);
    }

    public function test_get_report_categories_returns_array(): void
    {
        $categories = \JG_Map_Ajax_Handlers::get_report_categories();
        $this->assertIsArray($categories);
    }

    public function test_get_category_groups_returns_associative_array(): void
    {
        $groups = \JG_Map_Ajax_Handlers::get_category_groups();
        $this->assertIsArray($groups);
        $this->assertNotEmpty($groups);
        // Groups map string keys to string labels
        foreach ($groups as $key => $label) {
            $this->assertIsString($key, "Klucz grupy powinien być stringiem");
            $this->assertIsString($label, "Wartość grupy '$key' powinna być stringiem (label)");
        }
    }

    // ─── Kategorie – spójność ─────────────────────────────────────────

    public function test_place_categories_have_label_key(): void
    {
        $categories = \JG_Map_Ajax_Handlers::get_place_categories();
        foreach ($categories as $key => $cat) {
            $this->assertIsArray($cat, "Kategoria '$key' powinna być tablicą");
            $this->assertArrayHasKey('label', $cat, "Kategoria '$key' nie ma klucza 'label'");
        }
    }

    public function test_curiosity_categories_have_label_key(): void
    {
        $categories = \JG_Map_Ajax_Handlers::get_curiosity_categories();
        foreach ($categories as $key => $cat) {
            $this->assertIsArray($cat, "Kategoria '$key' powinna być tablicą");
            $this->assertArrayHasKey('label', $cat, "Kategoria '$key' nie ma klucza 'label'");
        }
    }

    // ─── Inicjalizacja ───────────────────────────────────────────────

    public function test_suggest_icon_for_label_returns_string(): void
    {
        $icon = \JG_Map_Ajax_Handlers::suggest_icon_for_label('restaurant');
        $this->assertIsString($icon);
    }
}
