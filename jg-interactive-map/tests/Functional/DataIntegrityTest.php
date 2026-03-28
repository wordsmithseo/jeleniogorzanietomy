<?php
/**
 * Testy funkcjonalne – spójność danych i walidacja.
 *
 * Sprawdzają poprawność walidacji danych wejściowych,
 * formatów JSON, kategorii i typów punktów.
 */

namespace JGMap\Tests\Functional;

use PHPUnit\Framework\TestCase;

class DataIntegrityTest extends TestCase
{
    private string $ajaxContent;

    protected function setUp(): void
    {
        $this->ajaxContent = file_get_contents(dirname(__DIR__, 2) . '/includes/class-ajax-handlers.php');
    }

    // ─── Walidacja danych wejściowych w submit_point ─────────────────

    public function test_submit_point_validates_title(): void
    {
        $pattern = '/function\s+submit_point\b.*?(?=function\s)/s';
        preg_match($pattern, $this->ajaxContent, $matches);
        $body = $matches[0] ?? '';
        $this->assertStringContainsString('title', $body, 'submit_point nie waliduje tytułu');
    }

    public function test_submit_point_validates_coordinates(): void
    {
        $pattern = '/function\s+submit_point\b.*?(?=function\s)/s';
        preg_match($pattern, $this->ajaxContent, $matches);
        $body = $matches[0] ?? '';
        $this->assertStringContainsString('lat', $body, 'submit_point nie waliduje współrzędnych');
        $this->assertStringContainsString('lng', $body, 'submit_point nie waliduje współrzędnych');
    }

    public function test_submit_point_validates_type(): void
    {
        $pattern = '/function\s+submit_point\b.*?(?=function\s)/s';
        preg_match($pattern, $this->ajaxContent, $matches);
        $body = $matches[0] ?? '';
        $this->assertStringContainsString('type', $body, 'submit_point nie waliduje typu');
    }

    // ─── Walidacja rejestracji ───────────────────────────────────────

    public function test_register_validates_email(): void
    {
        $pattern = '/function\s+register_user\b.*?(?=function\s)/s';
        preg_match($pattern, $this->ajaxContent, $matches);
        $body = $matches[0] ?? '';
        $this->assertStringContainsString('email', $body);
        $this->assertTrue(
            strpos($body, 'is_email') !== false || strpos($body, 'sanitize_email') !== false,
            'Rejestracja nie waliduje emaila'
        );
    }

    public function test_register_validates_password_strength(): void
    {
        $pattern = '/function\s+register_user\b.*?(?=\bpublic\s+function\b)/s';
        preg_match($pattern, $this->ajaxContent, $matches);
        $body = $matches[0] ?? '';
        // Powinno walidować siłę hasła
        $this->assertTrue(
            strpos($body, 'validate_password_strength') !== false ||
            strpos($body, 'strlen') !== false ||
            strpos($body, 'mb_strlen') !== false,
            'Rejestracja nie sprawdza siły hasła'
        );
    }

    public function test_register_has_honeypot(): void
    {
        $pattern = '/function\s+register_user\b.*?(?=function\s)/s';
        preg_match($pattern, $this->ajaxContent, $matches);
        $body = $matches[0] ?? '';
        $this->assertStringContainsString('honeypot', $body, 'Rejestracja nie ma pola honeypot');
    }

    // ─── Walidacja logowania ─────────────────────────────────────────

    public function test_login_uses_wp_signon(): void
    {
        $pattern = '/function\s+login_user\b.*?(?=function\s)/s';
        preg_match($pattern, $this->ajaxContent, $matches);
        $body = $matches[0] ?? '';
        $this->assertStringContainsString('wp_signon', $body, 'Login nie używa wp_signon()');
    }

    // ─── JSON danych – images ────────────────────────────────────────

    public function test_images_field_handled_as_json(): void
    {
        $dbContent = file_get_contents(dirname(__DIR__, 2) . '/includes/class-database.php');
        // images jest longtext – powinien być parsowany jako JSON
        $this->assertStringContainsString('json_decode', $this->ajaxContent, 'Brak json_decode dla pól JSON');
        $this->assertStringContainsString('json_encode', $this->ajaxContent, 'Brak json_encode dla pól JSON');
    }

    // ─── Spójność statusów punktów ───────────────────────────────────

    public function test_known_point_statuses(): void
    {
        $dbContent = file_get_contents(dirname(__DIR__, 2) . '/includes/class-database.php');
        $allContent = $dbContent . $this->ajaxContent;

        // Znane statusy
        $expectedStatuses = ['publish', 'pending', 'trash'];
        foreach ($expectedStatuses as $status) {
            $this->assertStringContainsString("'{$status}'", $allContent, "Status '{$status}' nie jest używany");
        }
    }

    // ─── Spójność typów punktów ──────────────────────────────────────

    public function test_default_point_type_in_schema(): void
    {
        $dbContent = file_get_contents(dirname(__DIR__, 2) . '/includes/class-database.php');
        $this->assertStringContainsString("DEFAULT 'zgloszenie'", $dbContent, 'Domyślny typ punktu nie jest ustawiony w schemacie');
    }

    // ─── Sanityzacja wyjścia ─────────────────────────────────────────

    public function test_admin_output_uses_escaping(): void
    {
        $adminContent = file_get_contents(dirname(__DIR__, 2) . '/includes/class-admin.php');
        $this->assertStringContainsString('esc_html', $adminContent, 'Admin nie używa esc_html()');
        $this->assertStringContainsString('esc_attr', $adminContent, 'Admin nie używa esc_attr()');
    }

    // ─── Cache invalidation ──────────────────────────────────────────

    public function test_cache_invalidation_method_exists(): void
    {
        $dbContent = file_get_contents(dirname(__DIR__, 2) . '/includes/class-database.php');
        $this->assertStringContainsString('function invalidate_points_cache', $dbContent);
        $this->assertStringContainsString('delete_transient', $dbContent);
    }

    public function test_insert_point_invalidates_cache(): void
    {
        $dbContent = file_get_contents(dirname(__DIR__, 2) . '/includes/class-database.php');
        $pattern = '/function\s+insert_point\b.*?(?=public\s+static\s+function)/s';
        preg_match($pattern, $dbContent, $matches);
        $body = $matches[0] ?? '';
        $this->assertStringContainsString('invalidate_points_cache', $body, 'insert_point nie invaliduje cache');
    }
}
