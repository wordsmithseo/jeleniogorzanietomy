<?php
/**
 * Bootstrap dla testów jednostkowych JG Interactive Map.
 *
 * Definiuje stałe i funkcje WordPress potrzebne do uruchomienia testów
 * bez pełnej instalacji WordPress.
 */

// Wymagane przez wszystkie klasy pluginu
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

// Minimalne stuby WordPress używane przez testowane metody
if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags(string $text, bool $remove_breaks = false): string {
        $text = strip_tags($text);
        if ($remove_breaks) {
            $text = preg_replace('/[\r\n\t ]+/', ' ', $text);
        }
        return trim($text);
    }
}

// Załaduj klasy pluginu
require_once dirname(__DIR__) . '/includes/class-database.php';
require_once dirname(__DIR__) . '/includes/class-levels-achievements.php';
