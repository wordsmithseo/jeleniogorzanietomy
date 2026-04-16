<?php
/**
 * Testy jednostkowe dla JG_Map_Levels_Achievements:
 *   - xp_for_level()
 *   - calculate_level()
 *   - count_valid_words()
 */

declare(strict_types=1);

namespace JGMap\Tests\Unit;

use PHPUnit\Framework\TestCase;

class LevelsAchievementsTest extends TestCase {

    // -------------------------------------------------------------------------
    // xp_for_level()
    // -------------------------------------------------------------------------

    /** @test */
    public function level_1_requires_0_xp(): void {
        $this->assertSame(0, \JG_Map_Levels_Achievements::xp_for_level(1));
    }

    /** @test */
    public function level_2_requires_400_xp(): void {
        // formuła: level^2 * 100 = 2*2*100 = 400
        $this->assertSame(400, \JG_Map_Levels_Achievements::xp_for_level(2));
    }

    /** @test */
    public function level_3_requires_900_xp(): void {
        $this->assertSame(900, \JG_Map_Levels_Achievements::xp_for_level(3));
    }

    /** @test */
    public function level_10_requires_10000_xp(): void {
        $this->assertSame(10000, \JG_Map_Levels_Achievements::xp_for_level(10));
    }

    /** @test */
    public function level_zero_or_below_returns_0(): void {
        $this->assertSame(0, \JG_Map_Levels_Achievements::xp_for_level(0));
        $this->assertSame(0, \JG_Map_Levels_Achievements::xp_for_level(-5));
    }

    // -------------------------------------------------------------------------
    // calculate_level()
    // -------------------------------------------------------------------------

    /** @test */
    public function zero_xp_gives_level_1(): void {
        $this->assertSame(1, \JG_Map_Levels_Achievements::calculate_level(0));
    }

    /** @test */
    public function xp_just_below_level2_threshold_gives_level_1(): void {
        // Próg poziomu 2 = 400 XP; 399 to nadal poziom 1
        $this->assertSame(1, \JG_Map_Levels_Achievements::calculate_level(399));
    }

    /** @test */
    public function xp_at_level2_threshold_gives_level_2(): void {
        $this->assertSame(2, \JG_Map_Levels_Achievements::calculate_level(400));
    }

    /** @test */
    public function xp_just_above_level2_threshold_gives_level_2(): void {
        $this->assertSame(2, \JG_Map_Levels_Achievements::calculate_level(401));
    }

    /** @test */
    public function xp_just_below_level3_threshold_gives_level_2(): void {
        // Próg poziomu 3 = 900 XP; 899 to nadal poziom 2
        $this->assertSame(2, \JG_Map_Levels_Achievements::calculate_level(899));
    }

    /** @test */
    public function xp_at_level3_threshold_gives_level_3(): void {
        $this->assertSame(3, \JG_Map_Levels_Achievements::calculate_level(900));
    }

    /** @test */
    public function large_xp_returns_correct_high_level(): void {
        // Poziom 10 = 10000 XP; sprawdzamy że 10000 → 10 a nie 11
        $this->assertSame(10, \JG_Map_Levels_Achievements::calculate_level(10000));
        // Próg poziomu 11 = 11^2*100 = 12100; 10001 to nadal poziom 10
        $this->assertSame(10, \JG_Map_Levels_Achievements::calculate_level(10001));
        $this->assertSame(11, \JG_Map_Levels_Achievements::calculate_level(12100));
    }

    /** @test */
    public function level_always_increases_with_more_xp(): void {
        $prev_level = \JG_Map_Levels_Achievements::calculate_level(0);
        foreach ([400, 900, 1600, 2500, 3600, 4900, 6400, 8100, 10000] as $xp) {
            $level = \JG_Map_Levels_Achievements::calculate_level($xp);
            $this->assertGreaterThanOrEqual($prev_level, $level, "Poziom powinien rosnąć dla XP=$xp");
            $prev_level = $level;
        }
    }

    // -------------------------------------------------------------------------
    // count_valid_words()
    // -------------------------------------------------------------------------

    /** @test */
    public function empty_string_returns_0(): void {
        $this->assertSame(0, \JG_Map_Levels_Achievements::count_valid_words(''));
    }

    /** @test */
    public function normal_polish_words_counted_correctly(): void {
        // "Jelenia Góra centrum" → 3 prawdziwe słowa
        $this->assertSame(3, \JG_Map_Levels_Achievements::count_valid_words('Jelenia Góra centrum'));
    }

    /** @test */
    public function single_chars_and_punctuation_not_counted(): void {
        // "A i w" → same jednoliretowe słowa, żadne nie liczy się
        $this->assertSame(0, \JG_Map_Levels_Achievements::count_valid_words('A i w'));
    }

    /** @test */
    public function gibberish_repeated_letter_not_counted(): void {
        // "aaaaaaa" → >70% ten sam znak, odrzucone
        $this->assertSame(0, \JG_Map_Levels_Achievements::count_valid_words('aaaaaaa'));
    }

    /** @test */
    public function html_tags_stripped_before_counting(): void {
        // HTML jest strippowany, więc efektywnie "Piękny widok"
        $this->assertSame(2, \JG_Map_Levels_Achievements::count_valid_words('<p>Piękny <b>widok</b></p>'));
    }

    /** @test */
    public function mixed_valid_and_invalid_words(): void {
        // "kawiarnia xzqw miejsce" → 'kawiarnia' ok, 'xzqw' (brak samogłoski) odrzucone, 'miejsce' ok
        $result = \JG_Map_Levels_Achievements::count_valid_words('kawiarnia xzqw miejsce');
        $this->assertSame(2, $result);
    }

    /** @test */
    public function only_consonants_word_rejected(): void {
        // 'brzm' - same spółgłoski bez samogłoski - odrzucone
        $this->assertSame(0, \JG_Map_Levels_Achievements::count_valid_words('brzm'));
    }

    /** @test */
    public function long_valid_sentence_counted(): void {
        $text = 'To jest bardzo piękne miejsce w sercu Jeleniej Góry';
        // to(2-litery ok), jest, bardzo, piękne, miejsce, sercu, Jeleniej, Góry = 9
        // "w" = 1 litera → odrzucone, "To" = ok (2 litery, samogłoska)
        $result = \JG_Map_Levels_Achievements::count_valid_words($text);
        $this->assertGreaterThanOrEqual(8, $result);
    }
}
