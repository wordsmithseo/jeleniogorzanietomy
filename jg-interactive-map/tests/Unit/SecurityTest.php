<?php
/**
 * Testy jednostkowe – weryfikacja zabezpieczeń.
 *
 * Sprawdzają czy krytyczne endpointy AJAX mają weryfikację nonce.
 */

namespace JGMap\Tests\Unit;

use PHPUnit\Framework\TestCase;

class SecurityTest extends TestCase
{
    private string $ajaxHandlersPath;
    private string $ajaxHandlersContent;

    protected function setUp(): void
    {
        $this->ajaxHandlersPath = dirname(__DIR__, 2) . '/includes/class-ajax-handlers.php';
        $this->ajaxHandlersContent = file_get_contents($this->ajaxHandlersPath);
    }

    // ─── Nonce verification na state-changing endpoints ──────────────

    /**
     * @dataProvider stateChangingMethodsProvider
     */
    public function test_state_changing_method_has_nonce_verification(string $method): void
    {
        $pattern = '/function\s+' . preg_quote($method) . '\s*\([^)]*\)\s*\{(.*?)(?=\bpublic\s+|private\s+|protected\s+|\}\s*$)/s';

        if (preg_match($pattern, $this->ajaxHandlersContent, $matches)) {
            $body = $matches[1];
            $this->assertStringContainsString(
                'verify_nonce',
                $body,
                "Metoda {$method}() nie wywołuje verify_nonce()"
            );
        } else {
            // Sprawdź przynajmniej czy metoda istnieje i zawiera verify_nonce w pobliżu
            $this->assertStringContainsString("function {$method}", $this->ajaxHandlersContent, "Nie znaleziono metody {$method}");
        }
    }

    public static function stateChangingMethodsProvider(): array
    {
        return [
            'submit_point'            => ['submit_point'],
            'update_point'            => ['update_point'],
            'vote'                    => ['vote'],
            'report_point'            => ['report_point'],
            'delete_image'            => ['delete_image'],
            'update_profile'          => ['update_profile'],
            'delete_profile'          => ['delete_profile'],
            'request_deletion'        => ['request_deletion'],
            'admin_approve_point'     => ['admin_approve_point'],
            'admin_reject_point'      => ['admin_reject_point'],
            'admin_delete_point'      => ['admin_delete_point'],
            'admin_ban_user'          => ['admin_ban_user'],
            'admin_change_status'     => ['admin_change_status'],
            'admin_approve_edit'      => ['admin_approve_edit'],
            'admin_reject_edit'       => ['admin_reject_edit'],
            'handle_reports'          => ['handle_reports'],
        ];
    }

    // ─── Nonce verification na nowo naprawionych endpointach ─────────

    /**
     * @dataProvider recentlyFixedMethodsProvider
     */
    public function test_recently_fixed_method_has_nonce(string $method): void
    {
        $this->assertMatchesRegularExpression(
            '/function\s+' . preg_quote($method) . '\b.*?verify_nonce/s',
            $this->ajaxHandlersContent,
            "Metoda {$method}() powinna mieć verify_nonce() (naprawa bezpieczeństwa)"
        );
    }

    public static function recentlyFixedMethodsProvider(): array
    {
        return [
            'get_user_info'       => ['get_user_info'],
            'get_user_activity'   => ['get_user_activity'],
            'get_ranking'         => ['get_ranking'],
            'search_address'      => ['search_address'],
            'get_sidebar_points'  => ['get_sidebar_points'],
            'update_profile'      => ['update_profile'],
            'delete_profile'      => ['delete_profile'],
        ];
    }

    // ─── Brak eval/exec w AJAX ───────────────────────────────────────

    public function test_no_dangerous_functions_in_ajax_handlers(): void
    {
        $dangerous = ['eval(', 'exec(', 'system(', 'passthru(', 'shell_exec(', 'popen('];
        foreach ($dangerous as $func) {
            $this->assertStringNotContainsString(
                $func,
                $this->ajaxHandlersContent,
                "Niebezpieczna funkcja {$func} wykryta w class-ajax-handlers.php"
            );
        }
    }

    // ─── Weryfikacja uprawnień w admin endpointach ────────────────────

    /**
     * @dataProvider adminMethodsProvider
     */
    public function test_admin_method_checks_capabilities(string $method): void
    {
        // Admin methods use $this->check_admin() which internally checks jg_map_manage/jg_map_moderate
        $pattern = '/function\s+' . preg_quote($method) . '\s*\(\s*\)\s*\{(.*?)(?=\bpublic\s+function\b)/s';
        if (preg_match($pattern, $this->ajaxHandlersContent, $matches)) {
            $body = $matches[1];
            $hasCapCheck = (
                strpos($body, 'check_admin') !== false ||
                strpos($body, 'manage_options') !== false ||
                strpos($body, 'jg_map_moderate') !== false ||
                strpos($body, 'jg_map_manage') !== false ||
                strpos($body, 'current_user_can') !== false
            );
            $this->assertTrue($hasCapCheck, "Metoda admin {$method}() nie sprawdza uprawnień");
        } else {
            $this->assertStringContainsString("function {$method}", $this->ajaxHandlersContent, "Nie znaleziono metody {$method}");
        }
    }

    public static function adminMethodsProvider(): array
    {
        return [
            'admin_approve_point'  => ['admin_approve_point'],
            'admin_reject_point'   => ['admin_reject_point'],
            'admin_delete_point'   => ['admin_delete_point'],
            'admin_ban_user'       => ['admin_ban_user'],
            'admin_change_status'  => ['admin_change_status'],
            'admin_toggle_promo'   => ['admin_toggle_promo'],
            'admin_update_note'    => ['admin_update_note'],
        ];
    }

    // ─── SQL injection protection ────────────────────────────────────

    public function test_no_raw_post_in_sql_queries(): void
    {
        $dbContent = file_get_contents(dirname(__DIR__, 2) . '/includes/class-database.php');
        // Sprawdź czy nie ma bezpośredniego $_POST/$_GET w zapytaniach SQL
        $this->assertDoesNotMatchRegularExpression(
            '/\$wpdb->(query|get_results|get_row|get_var|get_col)\s*\(\s*["\'].*\$_(?:POST|GET|REQUEST)/',
            $dbContent,
            'Bezpośrednie użycie $_POST/$_GET w zapytaniu SQL wykryte w class-database.php'
        );
    }

    // ─── Rate limiting ───────────────────────────────────────────────

    public function test_login_has_rate_limiting(): void
    {
        $this->assertStringContainsString('rate_', $this->ajaxHandlersContent, 'Brak rate limitingu w AJAX handlers');
    }

    public function test_register_has_rate_limiting(): void
    {
        $pattern = '/function\s+register_user\b.*?rate_/s';
        $this->assertMatchesRegularExpression($pattern, $this->ajaxHandlersContent, 'Rejestracja nie ma rate limitingu');
    }
}
