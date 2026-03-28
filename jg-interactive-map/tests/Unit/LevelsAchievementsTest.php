<?php
/**
 * Testy jednostkowe – klasa JG_Map_Levels_Achievements.
 *
 * Testują logikę kalkulacji XP i poziomów.
 */

namespace JGMap\Tests\Unit;

use PHPUnit\Framework\TestCase;

class LevelsAchievementsTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists('JG_Map_Levels_Achievements')) {
            require_once dirname(__DIR__, 2) . '/includes/class-levels-achievements.php';
        }
    }

    // ─── xp_for_level() ──────────────────────────────────────────────

    public function test_xp_for_level_1_is_zero(): void
    {
        $this->assertSame(0, \JG_Map_Levels_Achievements::xp_for_level(1));
    }

    public function test_xp_for_level_0_is_zero(): void
    {
        $this->assertSame(0, \JG_Map_Levels_Achievements::xp_for_level(0));
    }

    public function test_xp_for_level_negative_is_zero(): void
    {
        $this->assertSame(0, \JG_Map_Levels_Achievements::xp_for_level(-5));
    }

    public function test_xp_for_level_2(): void
    {
        // level 2: (2*2)*100 = 400
        $this->assertSame(400, \JG_Map_Levels_Achievements::xp_for_level(2));
    }

    public function test_xp_for_level_5(): void
    {
        // level 5: (5*5)*100 = 2500
        $this->assertSame(2500, \JG_Map_Levels_Achievements::xp_for_level(5));
    }

    public function test_xp_for_level_10(): void
    {
        // level 10: (10*10)*100 = 10000
        $this->assertSame(10000, \JG_Map_Levels_Achievements::xp_for_level(10));
    }

    public function test_xp_requirements_increase_with_level(): void
    {
        $prev = 0;
        for ($level = 2; $level <= 20; $level++) {
            $xp = \JG_Map_Levels_Achievements::xp_for_level($level);
            $this->assertGreaterThan($prev, $xp, "XP dla poziomu $level powinno być większe niż dla poziomu " . ($level - 1));
            $prev = $xp;
        }
    }

    // ─── calculate_level() ───────────────────────────────────────────

    public function test_calculate_level_zero_xp(): void
    {
        $this->assertSame(1, \JG_Map_Levels_Achievements::calculate_level(0));
    }

    public function test_calculate_level_just_below_level_2(): void
    {
        // Level 2 requires 400 XP
        $this->assertSame(1, \JG_Map_Levels_Achievements::calculate_level(399));
    }

    public function test_calculate_level_exactly_level_2(): void
    {
        $this->assertSame(2, \JG_Map_Levels_Achievements::calculate_level(400));
    }

    public function test_calculate_level_between_levels(): void
    {
        // Level 3 requires 900 XP, level 2 requires 400
        $this->assertSame(2, \JG_Map_Levels_Achievements::calculate_level(500));
        $this->assertSame(2, \JG_Map_Levels_Achievements::calculate_level(899));
    }

    public function test_calculate_level_high_xp(): void
    {
        // Level 10 = 10000, Level 11 = 12100
        $this->assertSame(10, \JG_Map_Levels_Achievements::calculate_level(10000));
        $this->assertSame(10, \JG_Map_Levels_Achievements::calculate_level(12099));
        $this->assertSame(11, \JG_Map_Levels_Achievements::calculate_level(12100));
    }

    public function test_calculate_level_and_xp_for_level_are_consistent(): void
    {
        for ($level = 1; $level <= 50; $level++) {
            $xp = \JG_Map_Levels_Achievements::xp_for_level($level);
            $calculated = \JG_Map_Levels_Achievements::calculate_level($xp);
            $this->assertSame($level, $calculated, "calculate_level(xp_for_level($level)) powinno zwrócić $level");
        }
    }

    // ─── count_valid_words() ─────────────────────────────────────────

    public function test_count_valid_words_empty(): void
    {
        $this->assertSame(0, \JG_Map_Levels_Achievements::count_valid_words(''));
    }

    public function test_count_valid_words_null(): void
    {
        $this->assertSame(0, \JG_Map_Levels_Achievements::count_valid_words(null));
    }

    public function test_count_valid_words_simple_sentence(): void
    {
        $count = \JG_Map_Levels_Achievements::count_valid_words('To jest prosty test zdania');
        $this->assertGreaterThanOrEqual(4, $count);
    }

    public function test_count_valid_words_strips_html(): void
    {
        $count = \JG_Map_Levels_Achievements::count_valid_words('<p>Hello <strong>World</strong></p>');
        $this->assertSame(2, $count);
    }

    public function test_count_valid_words_filters_gibberish(): void
    {
        // Gibberish without vowels should be filtered
        $withGibberish = \JG_Map_Levels_Achievements::count_valid_words('Hello bcdghjk World');
        $withoutGibberish = \JG_Map_Levels_Achievements::count_valid_words('Hello World');
        // bcdghjk has no vowels, so should be filtered out
        $this->assertSame($withoutGibberish, $withGibberish);
    }
}
