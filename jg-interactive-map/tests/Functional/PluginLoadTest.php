<?php
/**
 * Testy funkcjonalne – ładowanie wtyczki i rejestracja hooków.
 *
 * Weryfikują poprawność struktury klas, rejestracji AJAX hooków,
 * spójność schematów bazy danych.
 */

namespace JGMap\Tests\Functional;

use PHPUnit\Framework\TestCase;

class PluginLoadTest extends TestCase
{
    // ─── Singleton pattern ───────────────────────────────────────────

    public function test_main_plugin_class_exists(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . '/jg-interactive-map.php');
        $this->assertStringContainsString('class JG_Interactive_Map', $content);
    }

    public function test_main_class_uses_singleton(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . '/jg-interactive-map.php');
        $this->assertStringContainsString('get_instance', $content);
        $this->assertStringContainsString('private static $instance', $content);
    }

    // ─── Zależności ładowane poprawnie ────────────────────────────────

    /**
     * @dataProvider classFileMappingProvider
     */
    public function test_class_file_declares_expected_class(string $file, string $class): void
    {
        $path = dirname(__DIR__, 2) . '/includes/' . $file;
        $content = file_get_contents($path);
        $this->assertStringContainsString("class {$class}", $content, "Plik {$file} nie deklaruje klasy {$class}");
    }

    public static function classFileMappingProvider(): array
    {
        return [
            ['class-database.php', 'JG_Map_Database'],
            ['class-ajax-handlers.php', 'JG_Map_Ajax_Handlers'],
            ['class-enqueue.php', 'JG_Map_Enqueue'],
            ['class-admin.php', 'JG_Map_Admin'],
            ['class-maintenance.php', 'JG_Map_Maintenance'],
            ['class-levels-achievements.php', 'JG_Map_Levels_Achievements'],
            ['class-activity-log.php', 'JG_Map_Activity_Log'],
            ['class-sync-manager.php', 'JG_Map_Sync_Manager'],
            ['class-shortcode.php', 'JG_Map_Shortcode'],
            ['class-banner-manager.php', 'JG_Map_Banner_Manager'],
            ['class-banner-admin.php', 'JG_Map_Banner_Admin'],
            ['class-slot-keys.php', 'JG_Slot_Keys'],
        ];
    }

    // ─── AJAX hooks ──────────────────────────────────────────────────

    public function test_ajax_handlers_register_hooks(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . '/includes/class-ajax-handlers.php');

        // Sprawdź kluczowe hooki
        $expectedHooks = [
            'wp_ajax_jg_submit_point',
            'wp_ajax_jg_update_point',
            'wp_ajax_jg_vote',
            'wp_ajax_jg_report_point',
            'wp_ajax_nopriv_jg_map_login',
            'wp_ajax_nopriv_jg_map_register',
        ];

        foreach ($expectedHooks as $hook) {
            $this->assertStringContainsString($hook, $content, "Brak rejestracji hooka {$hook}");
        }
    }

    public function test_nopriv_endpoints_are_intentional(): void
    {
        // Scan all PHP files for nopriv registrations
        $dir = dirname(__DIR__, 2) . '/includes/';
        $content = '';
        foreach (glob($dir . '*.php') as $file) {
            $content .= file_get_contents($file);
        }

        // Lista dozwolonych nopriv endpointów (publicznych)
        $allowedNopriv = [
            'jg_points',                  // Pobieranie punktów na mapie
            'jg_map_login',               // Logowanie
            'jg_map_register',            // Rejestracja
            'jg_get_tags',                // Lista tagów
            'jg_get_user_info',           // Profil użytkownika
            'jg_get_user_activity',       // Aktywność użytkownika
            'jg_get_ranking',             // Ranking
            'jg_reverse_geocode',         // Geokodowanie
            'jg_search_address',          // Wyszukiwanie adresów
            'jg_get_sidebar_points',      // Sidebar
            'jg_map_ext_fetch',           // Baner
            'jg_map_ext_ping',            // Impression tracking
            'jg_map_ext_tap',             // Click tracking
            'jg_check_updates',           // Sprawdzenie aktualizacji
            'jg_check_point_exists',      // Sprawdzenie czy punkt istnieje
            'jg_map_forgot_password',     // Reset hasła
            'jg_map_resend_activation',   // Ponowne wysłanie aktywacji
            'jg_check_registration_status', // Status rejestracji
            'jg_check_user_session_status', // Status sesji
            'jg_logout_user',             // Wylogowanie
            'jg_track_stat',              // Śledzenie statystyk
            'jg_get_point_stats',         // Statystyki punktu
            'jg_get_point_visitors',      // Odwiedzający punkt
            'jg_get_menu',                // Odczyt menu (publiczny)
            'jg_get_user_level_info',     // Informacje o poziomie usera
            'jg_get_user_achievements',   // Osiągnięcia usera
            'jg_user_leave',              // Opuszczenie przez usera (sync)
        ];

        // Znajdź wszystkie nopriv hooki
        preg_match_all('/wp_ajax_nopriv_(\w+)/', $content, $matches);
        $registeredNopriv = array_unique($matches[1]);

        foreach ($registeredNopriv as $hook) {
            $this->assertContains(
                $hook,
                $allowedNopriv,
                "Niespodziewany publiczny endpoint wp_ajax_nopriv_{$hook} – czy jest zamierzony?"
            );
        }
    }

    // ─── Schemat bazy danych ─────────────────────────────────────────

    public function test_database_schema_uses_dbdelta(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . '/includes/class-database.php');
        $this->assertStringContainsString('dbDelta(', $content);
        $this->assertStringContainsString('wp-admin/includes/upgrade.php', $content);
    }

    public function test_database_schema_uses_charset(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . '/includes/class-database.php');
        $this->assertStringContainsString('get_charset_collate', $content);
    }

    public function test_all_tables_have_primary_key(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . '/includes/class-database.php');

        // Każdy CREATE TABLE powinien mieć PRIMARY KEY
        preg_match_all('/CREATE TABLE.*?;/s', $content, $matches);
        foreach ($matches[0] as $createStmt) {
            $this->assertStringContainsString(
                'PRIMARY KEY',
                $createStmt,
                "CREATE TABLE bez PRIMARY KEY:\n" . substr($createStmt, 0, 100) . '...'
            );
        }
    }

    // ─── Activation / Deactivation ───────────────────────────────────

    public function test_activation_hook_registered(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . '/jg-interactive-map.php');
        $this->assertStringContainsString('register_activation_hook', $content);
    }

    public function test_deactivation_hook_registered(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . '/jg-interactive-map.php');
        $this->assertStringContainsString('register_deactivation_hook', $content);
    }

    public function test_deactivation_cleans_up_cron(): void
    {
        $maintenanceContent = file_get_contents(dirname(__DIR__, 2) . '/includes/class-maintenance.php');
        $hasCronCleanup = (
            strpos($maintenanceContent, 'wp_clear_scheduled_hook') !== false ||
            strpos($maintenanceContent, 'wp_unschedule_event') !== false
        );
        $this->assertTrue($hasCronCleanup, 'Maintenance.deactivate() nie czyści cron hooków');
    }

    // ─── Shortcode ───────────────────────────────────────────────────

    public function test_shortcode_is_registered(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . '/includes/class-shortcode.php');
        $this->assertStringContainsString('add_shortcode', $content);
    }

    // ─── Rewrite rules ──────────────────────────────────────────────

    public function test_plugin_adds_rewrite_rules(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . '/jg-interactive-map.php');
        $this->assertStringContainsString('add_rewrite_rules', $content);
    }

    // ─── Security headers ────────────────────────────────────────────

    public function test_plugin_adds_security_headers(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . '/jg-interactive-map.php');
        $this->assertStringContainsString('add_security_headers', $content);
    }
}
