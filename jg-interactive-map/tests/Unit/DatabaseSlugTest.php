<?php
/**
 * Testy jednostkowe dla JG_Map_Database::generate_slug() i remove_diacritics().
 */

declare(strict_types=1);

namespace JGMap\Tests\Unit;

use PHPUnit\Framework\TestCase;

class DatabaseSlugTest extends TestCase {

    // -------------------------------------------------------------------------
    // generate_slug()
    // -------------------------------------------------------------------------

    /** @test */
    public function basic_ascii_title_returns_lowercase_slug(): void {
        $this->assertSame('kawiarnia-pod-lipami', \JG_Map_Database::generate_slug('Kawiarnia Pod Lipami'));
    }

    /** @test */
    public function polish_characters_are_transliterated(): void {
        $this->assertSame('zrodlo-zycia', \JG_Map_Database::generate_slug('Źródło Życia'));
    }

    /** @test */
    public function all_lowercase_polish_letters_transliterated(): void {
        // ą→a  ć→c  ę→e  ł→l  ń→n  ó→o  ś→s  ź→z  ż→z
        $input    = 'ą ć ę ł ń ó ś ź ż';
        $expected = 'a-c-e-l-n-o-s-z-z';
        $this->assertSame($expected, \JG_Map_Database::generate_slug($input));
    }

    /** @test */
    public function all_uppercase_polish_letters_transliterated(): void {
        // Uppercase → transliterated → lowercased
        $input    = 'Ą Ć Ę Ł Ń Ó Ś Ź Ż';
        $expected = 'a-c-e-l-n-o-s-z-z';
        $this->assertSame($expected, \JG_Map_Database::generate_slug($input));
    }

    /** @test */
    public function special_characters_become_hyphens(): void {
        $this->assertSame('bar-grill', \JG_Map_Database::generate_slug('Bar & Grill!'));
    }

    /** @test */
    public function multiple_spaces_collapse_to_single_hyphen(): void {
        $this->assertSame('park-miejski', \JG_Map_Database::generate_slug('Park   Miejski'));
    }

    /** @test */
    public function leading_and_trailing_hyphens_are_stripped(): void {
        $this->assertSame('park', \JG_Map_Database::generate_slug('...Park...'));
    }

    /** @test */
    public function numbers_are_preserved_in_slug(): void {
        $this->assertSame('ulica-1-maja', \JG_Map_Database::generate_slug('Ulica 1 Maja'));
    }

    /** @test */
    public function slug_is_truncated_to_200_characters(): void {
        $long_title = str_repeat('a', 250);
        $slug = \JG_Map_Database::generate_slug($long_title);
        $this->assertSame(200, strlen($slug));
    }

    /** @test */
    public function empty_string_returns_empty_slug(): void {
        $this->assertSame('', \JG_Map_Database::generate_slug(''));
    }

    /** @test */
    public function only_special_chars_returns_empty_slug(): void {
        $this->assertSame('', \JG_Map_Database::generate_slug('!@#$%^&*()'));
    }

    /** @test */
    public function mixed_languages_slug(): void {
        // "Café Müller" → "cafe-muller"
        $this->assertSame('cafe-muller', \JG_Map_Database::generate_slug('Café Müller'));
    }

    /** @test */
    public function real_jelenia_gora_point_name(): void {
        $this->assertSame('zamek-ksiaz', \JG_Map_Database::generate_slug('Zamek Książ'));
    }

    // -------------------------------------------------------------------------
    // remove_diacritics()
    // -------------------------------------------------------------------------

    /** @test */
    public function remove_diacritics_lowercase_polish(): void {
        $input    = 'ą ć ę ł ń ó ś ź ż';
        $expected = 'a c e l n o s z z';
        $this->assertSame($expected, \JG_Map_Database::remove_diacritics($input));
    }

    /** @test */
    public function remove_diacritics_uppercase_polish(): void {
        $input    = 'Ą Ć Ę Ł Ń Ó Ś Ź Ż';
        $expected = 'A C E L N O S Z Z';
        $this->assertSame($expected, \JG_Map_Database::remove_diacritics($input));
    }

    /** @test */
    public function remove_diacritics_leaves_ascii_unchanged(): void {
        $this->assertSame('Hello World 123', \JG_Map_Database::remove_diacritics('Hello World 123'));
    }

    /** @test */
    public function remove_diacritics_mixed_text(): void {
        $this->assertSame('Jelenia Gora', \JG_Map_Database::remove_diacritics('Jelenia Góra'));
    }
}
