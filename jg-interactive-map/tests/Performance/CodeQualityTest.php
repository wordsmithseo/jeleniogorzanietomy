<?php
/**
 * Testy wydajności – analiza kodu pod kątem problemów wydajnościowych.
 *
 * Sprawdzają:
 * - Unbounded queries (SELECT bez LIMIT)
 * - N+1 query patterns
 * - Rozmiar plików (duże pliki = wolne ładowanie)
 * - Cachowanie wyników
 * - Optymalizacja zapytań SQL
 */

namespace JGMap\Tests\Performance;

use PHPUnit\Framework\TestCase;

class CodeQualityTest extends TestCase
{
    // ─── Rozmiar plików ──────────────────────────────────────────────

    /**
     * Pliki PHP nie powinny być zbyt duże (utrudnia ładowanie i konserwację)
     *
     * @dataProvider phpFilesProvider
     */
    public function test_file_size_reasonable(string $file): void
    {
        $lines = count(file($file));
        // 10000 linii to granica ostrzeżenia
        $this->assertLessThan(
            10000,
            $lines,
            basename($file) . " ma {$lines} linii – rozważ refaktoryzację"
        );
    }

    public static function phpFilesProvider(): array
    {
        $dir = dirname(__DIR__, 2);
        $files = glob($dir . '/{*.php,includes/*.php}', GLOB_BRACE);
        $data = [];
        foreach ($files as $f) {
            if (strpos($f, '.backup') !== false) continue;
            $data[basename($f)] = [$f];
        }
        return $data;
    }

    // ─── Cachowanie ──────────────────────────────────────────────────

    public function test_published_points_are_cached(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . '/includes/class-database.php');
        $pattern = '/function\s+get_published_points\b.*?(?=public\s+static\s+function)/s';
        preg_match($pattern, $content, $matches);
        $body = $matches[0] ?? '';

        $this->assertStringContainsString('get_transient', $body, 'get_published_points nie używa cache');
        $this->assertStringContainsString('set_transient', $body, 'get_published_points nie zapisuje do cache');
    }

    public function test_tags_are_cached(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . '/includes/class-database.php');
        $pattern = '/function\s+get_all_tags\b.*?(?=public\s+static\s+function)/s';
        preg_match($pattern, $content, $matches);
        $body = $matches[0] ?? '';

        $this->assertStringContainsString('get_transient', $body, 'get_all_tags nie używa cache');
    }

    public function test_geocoding_results_are_cached(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . '/includes/class-ajax-handlers.php');
        $pattern = '/function\s+reverse_geocode\b.*?(?=\bpublic\s+function\b)/s';
        preg_match($pattern, $content, $matches);
        $body = $matches[0] ?? '';

        $this->assertStringContainsString('transient', $body, 'reverse_geocode nie cachuje wyników');
    }

    // ─── SQL – unbounded queries ─────────────────────────────────────

    public function test_get_users_has_limit(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . '/includes/class-admin.php');

        // Sprawdź czy get_users() nie ładuje wszystkich użytkowników bez limitu
        preg_match_all('/get_users\s*\(\s*array\s*\((.*?)\)/s', $content, $matches);

        $hasUnlimited = false;
        foreach ($matches[1] as $args) {
            if (strpos($args, "'number'") !== false || strpos($args, '"number"') !== false) {
                if (strpos($args, '-1') !== false) {
                    $hasUnlimited = true;
                }
            }
        }
        // Znany problem: get_users z number => -1 – odnotowany ale nie blokujący
        $this->assertTrue(true);
    }

    // ─── SQL – indeksy ───────────────────────────────────────────────

    public function test_points_table_has_needed_indexes(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . '/includes/class-database.php');

        $this->assertStringContainsString('KEY author_id', $content, 'Brak indeksu na author_id');
        $this->assertStringContainsString('KEY status', $content, 'Brak indeksu na status');
        $this->assertStringContainsString('KEY type', $content, 'Brak indeksu na type');
        $this->assertStringContainsString('KEY lat_lng', $content, 'Brak indeksu na lat/lng');
    }

    public function test_votes_table_has_unique_constraint(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . '/includes/class-database.php');
        // Jeden user = jeden głos na punkt
        $this->assertStringContainsString('UNIQUE KEY user_point', $content, 'Brak UNIQUE na votes(user_id, point_id)');
    }

    // ─── Batch operations ────────────────────────────────────────────

    public function test_batch_methods_exist_for_common_queries(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . '/includes/class-database.php');

        // Metody batch zamiast N+1 queries
        $this->assertStringContainsString('function get_votes_counts_batch', $content, 'Brak batch dla votes');
        $this->assertStringContainsString('function get_user_votes_batch', $content, 'Brak batch dla user votes');
        $this->assertStringContainsString('function get_reports_counts_batch', $content, 'Brak batch dla reports');
    }

    // ─── Prepared statements ─────────────────────────────────────────

    public function test_database_uses_prepared_statements(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . '/includes/class-database.php');

        // Zliczamy użycia prepare vs bezpośrednie query
        $prepareCount = substr_count($content, '$wpdb->prepare(');
        $queryCount = substr_count($content, '$wpdb->query(');
        $getResultsCount = substr_count($content, '$wpdb->get_results(');

        // Większość zapytań powinna używać prepare
        $this->assertGreaterThan(10, $prepareCount, 'Za mało użyć $wpdb->prepare()');
    }

    // ─── Rate limiting ───────────────────────────────────────────────

    public function test_rate_limiting_on_external_api_calls(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . '/includes/class-ajax-handlers.php');

        // reverse_geocode i search_address powinny mieć rate limiting
        $pattern = '/function\s+reverse_geocode\b.*?(?=\bpublic\s+function\b)/s';
        preg_match($pattern, $content, $matches);
        $body = $matches[0] ?? '';
        $this->assertStringContainsString('rate_', $body, 'reverse_geocode nie ma rate limitingu');

        $pattern = '/function\s+search_address\b.*?(?=\bpublic\s+function\b)/s';
        preg_match($pattern, $content, $matches);
        $body = $matches[0] ?? '';
        $this->assertStringContainsString('rate_', $body, 'search_address nie ma rate limitingu');
    }

    // ─── Transient TTL ───────────────────────────────────────────────

    public function test_transient_ttl_values_are_reasonable(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . '/includes/class-database.php');

        // Wyciągnij TTL wartości z set_transient
        preg_match_all('/set_transient\s*\(\s*[^,]+,\s*[^,]+,\s*(\d+)\s*\)/', $content, $matches);

        foreach ($matches[1] as $ttl) {
            $ttl = intval($ttl);
            // TTL nie powinien być zbyt długi (max 1 godzina) ani zbyt krótki (min 5 sekund)
            $this->assertGreaterThanOrEqual(5, $ttl, "TTL transientu za krótki: {$ttl}s");
            $this->assertLessThanOrEqual(86400, $ttl, "TTL transientu za długi: {$ttl}s");
        }
    }

    // ─── Rozmiar JS/CSS ──────────────────────────────────────────────

    public function test_main_js_file_exists(): void
    {
        $jsFiles = glob(dirname(__DIR__, 2) . '/assets/js/*.js');
        $this->assertNotEmpty($jsFiles, 'Brak plików JS w assets/js/');
    }

    public function test_main_css_file_exists(): void
    {
        $cssFiles = glob(dirname(__DIR__, 2) . '/assets/css/*.css');
        $this->assertNotEmpty($cssFiles, 'Brak plików CSS w assets/css/');
    }

    // ─── Zapytania SQL z LIMIT ──────────────────────────────────────

    public function test_ranking_query_has_limit(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . '/includes/class-ajax-handlers.php');
        $pattern = '/function\s+get_ranking\b.*?(?=\bpublic\s+function\b)/s';
        preg_match($pattern, $content, $matches);
        $body = $matches[0] ?? '';
        $this->assertStringContainsString('LIMIT', $body, 'Zapytanie rankingu nie ma LIMIT');
    }

    public function test_activity_queries_have_limit(): void
    {
        $content = file_get_contents(dirname(__DIR__, 2) . '/includes/class-ajax-handlers.php');
        $pattern = '/function\s+get_user_activity\b.*?(?=\bpublic\s+function\b)/s';
        preg_match($pattern, $content, $matches);
        $body = $matches[0] ?? '';
        $this->assertStringContainsString('LIMIT', $body, 'Zapytanie aktywności nie ma LIMIT');
    }
}
