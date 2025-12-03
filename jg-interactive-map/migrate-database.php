<?php
/**
 * Database Migration Script - Dodanie kolumny promo_until
 *
 * Ten skrypt dodaje brakującą kolumnę promo_until do tabeli wp_jg_map_points.
 * Uruchom go RĘCZNIE w przeglądarce po wgraniu nowej wersji pluginu.
 *
 * INSTRUKCJA:
 * 1. Wgraj ten plik do katalogu: wp-content/plugins/jg-interactive-map/
 * 2. Otwórz w przeglądarce: https://TWOJA-DOMENA.pl/wp-content/plugins/jg-interactive-map/migrate-database.php
 * 3. Skrypt automatycznie sprawdzi i doda kolumnę jeśli jej nie ma
 * 4. Po zakończeniu USUŃ ten plik dla bezpieczeństwa!
 */

// Load WordPress
require_once('../../../wp-load.php');

// Check admin permissions
if (!current_user_can('manage_options')) {
    die('❌ BŁĄD: Brak uprawnień administratora. Musisz być zalogowany jako administrator.');
}

echo '<html><head><meta charset="utf-8"><title>Migracja bazy danych - JG Interactive Map</title></head><body>';
echo '<h1>🔧 Migracja bazy danych - JG Interactive Map</h1>';
echo '<p style="color: #666;">Sprawdzanie i aktualizacja struktury bazy danych...</p>';
echo '<hr>';

global $wpdb;
$table = $wpdb->prefix . 'jg_map_points';

// Check if table exists
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");

if (!$table_exists) {
    echo '<p style="color: red;">❌ <strong>BŁĄD:</strong> Tabela <code>' . $table . '</code> nie istnieje!</p>';
    echo '<p>Plugin JG Interactive Map prawdopodobnie nie jest zainstalowany lub aktywowany.</p>';
    die('</body></html>');
}

echo '<p style="color: green;">✓ Tabela <code>' . $table . '</code> istnieje</p>';

// Check if promo_until column exists
$column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'promo_until'");

if (!empty($column_exists)) {
    echo '<p style="color: green;">✓ Kolumna <code>promo_until</code> już istnieje - nie trzeba nic robić!</p>';
    echo '<hr>';
    echo '<h2>✅ Migracja zakończona pomyślnie!</h2>';
    echo '<p><strong>Wszystko jest w porządku. Możesz teraz:</strong></p>';
    echo '<ol>';
    echo '<li>USUŃ ten plik (migrate-database.php) dla bezpieczeństwa</li>';
    echo '<li>Wyczyść cache przeglądarki (Ctrl+Shift+Delete)</li>';
    echo '<li>Przetestuj funkcję sponsorowania miejsc</li>';
    echo '</ol>';
} else {
    echo '<p style="color: orange;">⚠️ Kolumna <code>promo_until</code> nie istnieje - dodaję...</p>';

    // Add promo_until column
    $result = $wpdb->query("ALTER TABLE $table ADD COLUMN promo_until datetime DEFAULT NULL AFTER is_promo");

    if ($result === false) {
        echo '<p style="color: red;">❌ <strong>BŁĄD:</strong> Nie udało się dodać kolumny!</p>';
        echo '<p><strong>Błąd MySQL:</strong> ' . $wpdb->last_error . '</p>';
        echo '<p><strong>Zapytanie:</strong> <code>ALTER TABLE ' . $table . ' ADD COLUMN promo_until datetime DEFAULT NULL AFTER is_promo</code></p>';
        echo '<hr>';
        echo '<h3>Rozwiązanie problemu:</h3>';
        echo '<ol>';
        echo '<li>Zaloguj się do phpMyAdmin</li>';
        echo '<li>Znajdź tabelę: <code>' . $table . '</code></li>';
        echo '<li>Przejdź do zakładki "Struktura"</li>';
        echo '<li>Kliknij "Dodaj kolumnę" i ustaw:';
        echo '<ul>';
        echo '<li>Nazwa: <code>promo_until</code></li>';
        echo '<li>Typ: <code>DATETIME</code></li>';
        echo '<li>Domyślnie: <code>NULL</code></li>';
        echo '<li>Po kolumnie: <code>is_promo</code></li>';
        echo '</ul></li>';
        echo '<li>Zapisz zmiany</li>';
        echo '</ol>';
    } else {
        echo '<p style="color: green;">✓ Kolumna <code>promo_until</code> została pomyślnie dodana!</p>';

        // Verify
        $verify = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'promo_until'");

        if (!empty($verify)) {
            echo '<p style="color: green;">✓ Weryfikacja: Kolumna istnieje w bazie danych</p>';
            echo '<hr>';
            echo '<h2>✅ Migracja zakończona pomyślnie!</h2>';
            echo '<p><strong>Kolumna promo_until została dodana. Teraz:</strong></p>';
            echo '<ol>';
            echo '<li><strong>USUŃ ten plik (migrate-database.php) dla bezpieczeństwa!</strong></li>';
            echo '<li>Wyczyść cache przeglądarki (Ctrl+Shift+Delete)</li>';
            echo '<li>Odśwież stronę z mapą</li>';
            echo '<li>Przetestuj funkcję sponsorowania miejsc</li>';
            echo '</ol>';
        } else {
            echo '<p style="color: red;">❌ Weryfikacja nie powiodła się - kolumna nie jest widoczna</p>';
        }
    }
}

echo '<hr>';
echo '<h3>📊 Aktualna struktura tabeli:</h3>';
echo '<pre style="background: #f5f5f5; padding: 10px; border: 1px solid #ddd;">';

$columns = $wpdb->get_results("SHOW COLUMNS FROM $table");
foreach ($columns as $column) {
    echo sprintf(
        "%-20s %-30s %s\n",
        $column->Field,
        $column->Type,
        ($column->Null === 'YES' ? 'NULL' : 'NOT NULL') . ' ' . ($column->Default ? "DEFAULT '{$column->Default}'" : '')
    );
}

echo '</pre>';

echo '<hr>';
echo '<p style="color: #999; font-size: 12px;">Wersja pluginu: 2.9.1 | Data: ' . date('Y-m-d H:i:s') . '</p>';
echo '</body></html>';
