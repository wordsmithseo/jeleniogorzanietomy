<?php
/**
 * Testy smoke – weryfikacja podstawowej spójności wtyczki.
 *
 * Sprawdzają czy:
 * - Pliki PHP nie mają błędów składni
 * - Klasy i stałe są zdefiniowane
 * - Brak oczywistych regresji po zmianach
 */

namespace JGMap\Tests;

use PHPUnit\Framework\TestCase;

class SmokeTest extends TestCase
{
    // ─── Składnia PHP ────────────────────────────────────────────────

    /**
     * @dataProvider phpFilesProvider
     */
    public function test_php_syntax_is_valid(string $file): void
    {
        $output = [];
        $exitCode = 0;
        exec('php -l ' . escapeshellarg($file) . ' 2>&1', $output, $exitCode);
        $this->assertSame(0, $exitCode, "Błąd składni w {$file}:\n" . implode("\n", $output));
    }

    public static function phpFilesProvider(): array
    {
        $dir = dirname(__DIR__);
        $files = glob($dir . '/{*.php,includes/*.php}', GLOB_BRACE);
        $data = [];
        foreach ($files as $f) {
            if (strpos($f, '.backup') !== false) continue;
            $data[basename($f)] = [$f];
        }
        return $data;
    }

    // ─── Pliki ────────────────────────────────────────────────────────

    /**
     * @dataProvider requiredFilesProvider
     */
    public function test_required_file_exists(string $path): void
    {
        $this->assertFileExists($path);
    }

    public static function requiredFilesProvider(): array
    {
        $base = dirname(__DIR__);
        return [
            'main plugin file'         => [$base . '/jg-interactive-map.php'],
            'class-database'           => [$base . '/includes/class-database.php'],
            'class-ajax-handlers'      => [$base . '/includes/class-ajax-handlers.php'],
            'class-enqueue'            => [$base . '/includes/class-enqueue.php'],
            'class-admin'              => [$base . '/includes/class-admin.php'],
            'class-maintenance'        => [$base . '/includes/class-maintenance.php'],
            'class-levels-achievements' => [$base . '/includes/class-levels-achievements.php'],
            'class-activity-log'       => [$base . '/includes/class-activity-log.php'],
            'class-sync-manager'       => [$base . '/includes/class-sync-manager.php'],
            'class-shortcode'          => [$base . '/includes/class-shortcode.php'],
            'class-banner-manager'     => [$base . '/includes/class-banner-manager.php'],
            'class-banner-admin'       => [$base . '/includes/class-banner-admin.php'],
            'class-slot-keys'          => [$base . '/includes/class-slot-keys.php'],
        ];
    }

    // ─── Nagłówek wtyczki ─────────────────────────────────────────────

    public function test_plugin_header_contains_required_fields(): void
    {
        $content = file_get_contents(dirname(__DIR__) . '/jg-interactive-map.php');
        $this->assertStringContainsString('Plugin Name:', $content);
        $this->assertStringContainsString('Version:', $content);
        $this->assertStringContainsString('Text Domain:', $content);
    }

    public function test_plugin_version_constant_matches_header(): void
    {
        $content = file_get_contents(dirname(__DIR__) . '/jg-interactive-map.php');
        preg_match('/Version:\s*(\S+)/', $content, $headerMatch);
        preg_match("/define\(\s*'JG_MAP_VERSION'\s*,\s*'([^']+)'/", $content, $constMatch);

        $this->assertNotEmpty($headerMatch[1] ?? '', 'Brak Version w nagłówku');
        $this->assertNotEmpty($constMatch[1] ?? '', 'Brak stałej JG_MAP_VERSION');
        $this->assertSame($headerMatch[1], $constMatch[1], 'Wersja w nagłówku nie zgadza się ze stałą');
    }

    // ─── Bezpieczeństwo – blokada bezpośredniego dostępu ──────────

    public function test_main_file_blocks_direct_access(): void
    {
        $content = file_get_contents(dirname(__DIR__) . '/jg-interactive-map.php');
        $this->assertStringContainsString('ABSPATH', $content, 'Brak sprawdzenia ABSPATH w głównym pliku');
    }

    // ─── Composer ─────────────────────────────────────────────────────

    public function test_composer_json_is_valid(): void
    {
        $path = dirname(__DIR__) . '/composer.json';
        $this->assertFileExists($path);
        $data = json_decode(file_get_contents($path), true);
        $this->assertNotNull($data, 'composer.json nie jest poprawnym JSON');
        $this->assertArrayHasKey('require', $data);
    }

    // ─── PHPUnit config ───────────────────────────────────────────────

    public function test_phpunit_config_exists(): void
    {
        $this->assertFileExists(dirname(__DIR__) . '/phpunit.xml.dist');
    }

    // ─── Brak hardcoded credentials ───────────────────────────────────

    /**
     * @dataProvider phpFilesProvider
     */
    public function test_no_hardcoded_passwords(string $file): void
    {
        $content = file_get_contents($file);
        // Szukamy wzorców jak password = 'xxx' (nie w komentarzach/zmiennych POST)
        $this->assertDoesNotMatchRegularExpression(
            '/(?:db_password|mysql_password|api_key|secret_key)\s*=\s*[\'"][^\'"]{4,}[\'"]/i',
            $content,
            "Potencjalnie hardcoded credentials w {$file}"
        );
    }

    // ─── Brak eval() ──────────────────────────────────────────────────

    /**
     * @dataProvider phpFilesProvider
     */
    public function test_no_eval_usage(string $file): void
    {
        $content = file_get_contents($file);
        $this->assertDoesNotMatchRegularExpression(
            '/\beval\s*\(/',
            $content,
            "Użycie eval() wykryte w {$file}"
        );
    }

    // ─── Brak plików debug/backup na produkcji ────────────────────────

    public function test_no_debug_files_in_includes(): void
    {
        $debugFiles = glob(dirname(__DIR__) . '/includes/*.{log,tmp,bak,swp}', GLOB_BRACE);
        $this->assertEmpty($debugFiles, 'Pliki debug/temp w katalogu includes: ' . implode(', ', $debugFiles ?: []));
    }
}
