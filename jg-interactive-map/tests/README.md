# Testy dla JG Interactive Map

## Instalacja środowiska testowego

### 1. Zainstaluj Composer (jeśli nie masz)

```bash
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
```

### 2. Zainstaluj zależności testowe

```bash
cd jg-interactive-map
composer install --dev
```

## Uruchomienie testów

### Wszystkie testy

```bash
composer test
```

lub bezpośrednio:

```bash
vendor/bin/phpunit
```

### Testy z pokryciem kodu

```bash
composer test:coverage
```

Raport HTML zostanie wygenerowany w `tests/coverage-report/index.html`

### Pojedyncza klasa testów

```bash
vendor/bin/phpunit tests/DatabaseTest.php
```

### Pojedynczy test

```bash
vendor/bin/phpunit --filter test_generate_slug_with_polish_characters
```

## Analiza statyczna

### PHP_CodeSniffer (sprawdzanie standardów WordPress)

```bash
composer phpcs
```

### PHPStan (analiza statyczna)

```bash
composer phpstan
```

### Wszystkie sprawdzenia

```bash
composer check
```

## Struktura testów

```
tests/
├── bootstrap.php           # Bootstrap dla PHPUnit
├── DatabaseTest.php        # Testy klasy JG_Map_Database
├── AjaxHandlersTest.php    # Testy klasy JG_Map_Ajax_Handlers
├── SecurityTest.php        # Testy bezpieczeństwa
└── README.md              # Ten plik
```

## Kategorie testów

### DatabaseTest
- Generowanie slug-ów z polskimi znakami
- Walidacja długości slug-ów
- Poprawność nazw tabel

### AjaxHandlersTest
- Struktura kategorii zgłoszeń
- Walidacja grup kategorii
- Singleton pattern

### SecurityTest
- Ochrona przed directory traversal
- Ochrona przed XSS
- Ochrona przed SQL injection
- Sprawdzanie ABSPATH w plikach
- Wykrywanie hardcoded credentials

## Wymagania

- PHP >= 7.4
- PHPUnit ^9.5
- Composer

## Continuous Integration

Testy można zintegrować z CI/CD:

### GitHub Actions

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - uses: php-actions/composer@v6
      - run: composer install
      - run: composer test
```

## Pokrycie kodu

Cel: osiągnięcie minimum 70% pokrycia kodu testami.

Aktualne pokrycie można sprawdzić uruchamiając:

```bash
composer test:coverage
```

## Dodawanie nowych testów

1. Utwórz nowy plik w katalogu `tests/` z sufiksem `Test.php`
2. Rozszerz klasę `PHPUnit\Framework\TestCase`
3. Dodaj metody testowe z prefiksem `test_`
4. Uruchom testy aby zweryfikować

Przykład:

```php
<?php
namespace JGMap\Tests;

use PHPUnit\Framework\TestCase;

class MyNewTest extends TestCase
{
    public function test_something()
    {
        $this->assertTrue(true);
    }
}
```

## Debugowanie testów

### Verbose output

```bash
vendor/bin/phpunit --verbose
```

### Stop on failure

```bash
vendor/bin/phpunit --stop-on-failure
```

### Debug pojedynczego testu

```bash
vendor/bin/phpunit --filter test_name --debug
```

## Uwagi

- Testy są uruchamiane w izolowanym środowisku bez WordPress
- Funkcje WordPress są mockowane w `bootstrap.php`
- Dla pełnej integracji z WordPress należy użyć WP-CLI i wp-env
