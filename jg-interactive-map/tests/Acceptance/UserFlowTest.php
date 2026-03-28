<?php
/**
 * Testy akceptacyjne – weryfikacja przepływów użytkownika.
 *
 * Sprawdzają kompletność ścieżek użytkownika od rejestracji
 * po zarządzanie punktami, bez wymagania działającej bazy danych.
 * Analizują kod źródłowy pod kątem poprawności logiki biznesowej.
 */

namespace JGMap\Tests\Acceptance;

use PHPUnit\Framework\TestCase;

class UserFlowTest extends TestCase
{
    private string $ajaxContent;
    private string $dbContent;

    protected function setUp(): void
    {
        $this->ajaxContent = file_get_contents(dirname(__DIR__, 2) . '/includes/class-ajax-handlers.php');
        $this->dbContent = file_get_contents(dirname(__DIR__, 2) . '/includes/class-database.php');
    }

    // ─── AC1: Rejestracja → Logowanie → Profil ──────────────────────

    public function test_registration_flow_complete(): void
    {
        // 1. Rejestracja istnieje
        $this->assertStringContainsString('function register_user', $this->ajaxContent);

        // 2. Rejestracja tworzy użytkownika WordPress
        $pattern = '/function\s+register_user\b.*?(?=\bpublic\s+function\b)/s';
        preg_match($pattern, $this->ajaxContent, $matches);
        $body = $matches[0] ?? '';
        $this->assertStringContainsString('wp_create_user', $body, 'Rejestracja nie tworzy użytkownika WP');

        // 3. Rejestracja wysyła email aktywacyjny
        $this->assertTrue(
            strpos($body, 'wp_mail') !== false || strpos($body, 'send_plugin_email') !== false,
            'Rejestracja nie wysyła emaila'
        );
    }

    public function test_login_flow_complete(): void
    {
        $pattern = '/function\s+login_user\b.*?(?=\bpublic\s+function\b)/s';
        preg_match($pattern, $this->ajaxContent, $matches);
        $body = $matches[0] ?? '';

        // Logowanie używa wp_signon
        $this->assertStringContainsString('wp_signon', $body);
        // Logowanie ustawia cookie
        $this->assertStringContainsString('wp_set_auth_cookie', $body, 'Login nie ustawia cookie auth');
    }

    public function test_password_reset_flow_complete(): void
    {
        $pattern = '/function\s+forgot_password\b.*?(?=\bpublic\s+function\b)/s';
        preg_match($pattern, $this->ajaxContent, $matches);
        $body = $matches[0] ?? '';

        // Generuje token
        $this->assertStringContainsString('reset_key', $body, 'Reset hasła nie generuje klucza');
        // Wysyła email
        $this->assertTrue(
            strpos($body, 'wp_mail') !== false || strpos($body, 'send_plugin_email') !== false,
            'Reset hasła nie wysyła emaila'
        );
    }

    // ─── AC2: Dodawanie punktu → Moderacja → Publikacja ─────────────

    public function test_point_submission_flow(): void
    {
        // 1. Użytkownik dodaje punkt
        $pattern = '/function\s+submit_point\b.*?(?=\bpublic\s+function\b)/s';
        preg_match($pattern, $this->ajaxContent, $matches);
        $body = $matches[0] ?? '';

        // Punkt trafia jako pending
        $this->assertStringContainsString("'pending'", $body, 'Nowy punkt nie ma statusu pending');
        // Zapisuje punkt w bazie
        $this->assertStringContainsString('insert_point', $body, 'submit_point nie wywołuje insert_point');
    }

    public function test_point_approval_flow(): void
    {
        // Admin zatwierdza punkt
        $pattern = '/function\s+admin_approve_point\b.*?(?=\bpublic\s+function\b)/s';
        preg_match($pattern, $this->ajaxContent, $matches);
        $body = $matches[0] ?? '';

        // Zmienia status na publish
        $this->assertStringContainsString("'publish'", $body, 'Zatwierdzenie nie ustawia statusu publish');
        // Sprawdza uprawnienia (przez check_admin() lub bezpośrednio)
        $this->assertTrue(
            strpos($body, 'check_admin') !== false ||
            strpos($body, 'manage_options') !== false ||
            strpos($body, 'jg_map_moderate') !== false ||
            strpos($body, 'jg_map_manage') !== false,
            'Zatwierdzenie nie sprawdza uprawnień'
        );
    }

    public function test_point_rejection_flow(): void
    {
        $pattern = '/function\s+admin_reject_point\b.*?(?=\bpublic\s+function\b)/s';
        preg_match($pattern, $this->ajaxContent, $matches);
        $body = $matches[0] ?? '';

        // Odrzucenie kasuje punkt i rozwiązuje powiązane raporty
        $this->assertStringContainsString('delete_point', $body, 'Odrzucenie nie usuwa punktu');
        $this->assertStringContainsString('resolve_reports', $body, 'Odrzucenie nie rozwiązuje raportów');
    }

    // ─── AC3: Edycja punktu → Zatwierdzenie edycji ──────────────────

    public function test_point_edit_flow(): void
    {
        // Użytkownik edytuje punkt
        $pattern = '/function\s+update_point\b.*?(?=\bpublic\s+function\b)/s';
        preg_match($pattern, $this->ajaxContent, $matches);
        $body = $matches[0] ?? '';

        // Zapisuje historię edycji
        $this->assertStringContainsString('add_history', $body, 'Edycja nie zapisuje historii');
    }

    public function test_edit_approval_flow(): void
    {
        $this->assertStringContainsString('function admin_approve_edit', $this->ajaxContent);
        $this->assertStringContainsString('function admin_reject_edit', $this->ajaxContent);
    }

    // ─── AC4: Głosowanie i raporty ───────────────────────────────────

    public function test_voting_flow(): void
    {
        $pattern = '/function\s+vote\b.*?(?=\bpublic\s+function\b)/s';
        preg_match($pattern, $this->ajaxContent, $matches);
        $body = $matches[0] ?? '';

        // Wymaga zalogowania
        $this->assertStringContainsString('is_user_logged_in', $body, 'Głosowanie nie wymaga zalogowania');
        // Zapisuje głos
        $this->assertStringContainsString('set_vote', $body, 'Głosowanie nie wywołuje set_vote');
    }

    public function test_report_flow(): void
    {
        $pattern = '/function\s+report_point\b.*?(?=\bpublic\s+function\b)/s';
        preg_match($pattern, $this->ajaxContent, $matches);
        $body = $matches[0] ?? '';

        // Sprawdza czy już nie zgłoszono
        $this->assertStringContainsString('has_user_reported', $body, 'Raportowanie nie sprawdza duplikatów');
        // Zapisuje raport
        $this->assertStringContainsString('add_report', $body, 'Raportowanie nie wywołuje add_report');
    }

    // ─── AC5: Usuwanie konta ─────────────────────────────────────────

    public function test_account_deletion_requires_password(): void
    {
        $pattern = '/function\s+delete_profile\b.*?(?=\bpublic\s+function\b)/s';
        preg_match($pattern, $this->ajaxContent, $matches);
        $body = $matches[0] ?? '';

        $this->assertStringContainsString('password', $body, 'Usuwanie konta nie wymaga hasła');
        $this->assertTrue(
            strpos($body, 'wp_check_password') !== false,
            'Usuwanie konta nie weryfikuje hasła'
        );
    }

    public function test_account_deletion_blocks_admins(): void
    {
        $pattern = '/function\s+delete_profile\b.*?(?=\bpublic\s+function\b)/s';
        preg_match($pattern, $this->ajaxContent, $matches);
        $body = $matches[0] ?? '';

        $this->assertStringContainsString('manage_options', $body, 'Usuwanie nie blokuje adminów');
    }

    // ─── AC6: System XP / Poziomów ──────────────────────────────────

    public function test_xp_system_integrated(): void
    {
        // Sprawdź czy submit_point przyznaje XP
        $this->assertStringContainsString('award_xp', $this->ajaxContent, 'Brak integracji XP w AJAX handlers');
    }

    // ─── AC7: Geokodowanie ──────────────────────────────────────────

    public function test_geocoding_has_rate_limiting(): void
    {
        $pattern = '/function\s+reverse_geocode\b.*?(?=\bpublic\s+function\b)/s';
        preg_match($pattern, $this->ajaxContent, $matches);
        $body = $matches[0] ?? '';

        $this->assertStringContainsString('rate_', $body, 'Geokodowanie nie ma rate limitingu');
    }

    public function test_geocoding_caches_results(): void
    {
        $pattern = '/function\s+reverse_geocode\b.*?(?=\bpublic\s+function\b)/s';
        preg_match($pattern, $this->ajaxContent, $matches);
        $body = $matches[0] ?? '';

        $this->assertStringContainsString('transient', $body, 'Geokodowanie nie cachuje wyników');
    }

    // ─── AC8: Baner system ──────────────────────────────────────────

    public function test_banner_tracking_exists(): void
    {
        $this->assertStringContainsString('function track_banner_impression', $this->ajaxContent);
        $this->assertStringContainsString('function track_banner_click', $this->ajaxContent);
    }

    // ─── AC9: Menu system ────────────────────────────────────────────

    public function test_menu_crud_operations_exist(): void
    {
        $this->assertStringContainsString('function get_menu', $this->ajaxContent);
        $this->assertStringContainsString('function save_menu', $this->ajaxContent);
        $this->assertStringContainsString('function upload_menu_photo', $this->ajaxContent);
        $this->assertStringContainsString('function delete_menu_photo', $this->ajaxContent);
    }

    // ─── AC10: Soft delete / Trash ──────────────────────────────────

    public function test_soft_delete_exists(): void
    {
        $this->assertStringContainsString('function soft_delete_point', $this->dbContent);
        $this->assertStringContainsString('function restore_point', $this->dbContent);
    }

    public function test_trash_management(): void
    {
        $this->assertStringContainsString('function admin_restore_point', $this->ajaxContent);
        $this->assertStringContainsString('function admin_empty_trash', $this->ajaxContent);
    }
}
