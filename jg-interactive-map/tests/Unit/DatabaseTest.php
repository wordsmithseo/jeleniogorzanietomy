<?php
/**
 * Testy jednostkowe – klasa JG_Map_Database.
 *
 * Testują czyste funkcje (bez zależności od bazy danych)
 * takie jak generowanie slugów i walidacja danych.
 */

namespace JGMap\Tests\Unit;

use PHPUnit\Framework\TestCase;

class DatabaseTest extends TestCase
{
    protected function setUp(): void
    {
        // Załaduj klasę bez ładowania całego pluginu
        if (!class_exists('JG_Map_Database')) {
            require_once dirname(__DIR__, 2) . '/includes/class-database.php';
        }
    }

    // ─── generate_slug() ──────────────────────────────────────────────

    public function test_generate_slug_basic(): void
    {
        $this->assertSame('hello-world', \JG_Map_Database::generate_slug('Hello World'));
    }

    public function test_generate_slug_polish_characters(): void
    {
        $this->assertSame('zolty-kon', \JG_Map_Database::generate_slug('Żółty koń'));
    }

    public function test_generate_slug_all_polish_chars(): void
    {
        $slug = \JG_Map_Database::generate_slug('ąćęłńóśźżĄĆĘŁŃÓŚŹŻ');
        // All Polish chars transliterated, result is all lowercase latin
        $this->assertMatchesRegularExpression('/^[a-z]+$/', $slug);
        $this->assertStringContainsString('acelnoszzacelnoszz', $slug);
    }

    public function test_generate_slug_special_characters(): void
    {
        $slug = \JG_Map_Database::generate_slug('Café & Bar — "Nowy"');
        $this->assertStringNotContainsString('&', $slug);
        $this->assertStringNotContainsString('"', $slug);
        $this->assertStringNotContainsString('—', $slug);
    }

    public function test_generate_slug_strips_leading_trailing_hyphens(): void
    {
        $slug = \JG_Map_Database::generate_slug('---Test---');
        $this->assertStringNotContainsString('---', $slug);
        $this->assertNotSame('-', substr($slug, 0, 1));
        $this->assertNotSame('-', substr($slug, -1));
    }

    public function test_generate_slug_limits_length_to_200(): void
    {
        $longTitle = str_repeat('Test długiego tytułu ', 50);
        $slug = \JG_Map_Database::generate_slug($longTitle);
        $this->assertLessThanOrEqual(200, strlen($slug));
    }

    public function test_generate_slug_empty_input(): void
    {
        $slug = \JG_Map_Database::generate_slug('');
        $this->assertSame('', $slug);
    }

    public function test_generate_slug_only_special_chars(): void
    {
        $slug = \JG_Map_Database::generate_slug('!@#$%^&*()');
        $this->assertSame('', $slug);
    }

    public function test_generate_slug_numbers(): void
    {
        $slug = \JG_Map_Database::generate_slug('Punkt 123 na mapie');
        $this->assertSame('punkt-123-na-mapie', $slug);
    }

    public function test_generate_slug_consecutive_spaces(): void
    {
        $slug = \JG_Map_Database::generate_slug('Hello     World');
        $this->assertStringNotContainsString('--', $slug);
    }

    public function test_generate_slug_unicode_non_polish(): void
    {
        // Characters like German umlauts should be stripped
        $slug = \JG_Map_Database::generate_slug('München Straße');
        $this->assertMatchesRegularExpression('/^[a-z0-9\-]*$/', $slug);
    }

    // ─── Table name getters ──────────────────────────────────────────

    public function test_table_names_use_wp_prefix(): void
    {
        // Te metody wymagają globalnego $wpdb
        if (!isset($GLOBALS['wpdb'])) {
            $this->markTestSkipped('Wymaga $wpdb');
        }

        $this->assertStringContainsString('jg_map_points', \JG_Map_Database::get_points_table());
        $this->assertStringContainsString('jg_map_votes', \JG_Map_Database::get_votes_table());
        $this->assertStringContainsString('jg_map_reports', \JG_Map_Database::get_reports_table());
    }
}
