<?php
/**
 * Minimal test runner for pure-PHP SEO helpers (no PHPUnit/WP needed).
 */
require_once __DIR__ . '/helpers/class-testable-seo-helpers.php';

$pass = 0;
$fail = 0;

function assert_same( $expected, $actual, string $label ): void {
    global $pass, $fail;
    if ( $expected === $actual ) {
        echo "  \033[32m✓\033[0m {$label}\n";
        $pass++;
    } else {
        echo "  \033[31m✗\033[0m {$label}\n";
        echo "    expected: " . json_encode( $expected ) . "\n";
        echo "    actual:   " . json_encode( $actual ) . "\n";
        $fail++;
    }
}

// ── Helper to build opening-hours strings ───────────────────────────────────
function oh( array $days ): string {
    return implode( "\n", $days );
}

$all7_same  = oh( [ 'Mo 09:00-22:00', 'Tu 09:00-22:00', 'We 09:00-22:00', 'Th 09:00-22:00', 'Fr 09:00-22:00', 'Sa 09:00-22:00', 'Su 09:00-22:00' ] );
$all7_24h   = oh( [ 'Mo 00:00-24:00', 'Tu 00:00-24:00', 'We 00:00-24:00', 'Th 00:00-24:00', 'Fr 00:00-24:00', 'Sa 00:00-24:00', 'Su 00:00-24:00' ] );
$all7_2359  = oh( [ 'Mo 00:00-23:59', 'Tu 00:00-23:59', 'We 00:00-23:59', 'Th 00:00-23:59', 'Fr 00:00-23:59', 'Sa 00:00-23:59', 'Su 00:00-23:59' ] );
$wd_only    = oh( [ 'Mo 09:00-17:00', 'Tu 09:00-17:00', 'We 09:00-17:00', 'Th 09:00-17:00', 'Fr 09:00-17:00' ] );
$wd_diff    = oh( [ 'Mo 08:00-17:00', 'Tu 09:00-17:00', 'We 09:00-17:00', 'Th 09:00-17:00', 'Fr 09:00-17:00' ] );
$we_same    = oh( [ 'Mo 10:00-20:00', 'Tu 10:00-20:00', 'We 10:00-20:00', 'Th 10:00-20:00', 'Fr 10:00-20:00', 'Sa 10:00-20:00', 'Su 10:00-20:00' ] );
$we_diff    = oh( [ 'Mo 09:00-17:00', 'Tu 09:00-17:00', 'We 09:00-17:00', 'Th 09:00-17:00', 'Fr 09:00-17:00', 'Sa 10:00-22:00', 'Su 10:00-22:00' ] );
$mon_sat    = oh( [ 'Mo 08:00-18:00', 'Tu 08:00-18:00', 'We 08:00-18:00', 'Th 08:00-18:00', 'Fr 08:00-18:00', 'Sa 08:00-18:00' ] );
$complex    = oh( [ 'Mo 09:00-17:00', 'Tu 10:00-18:00', 'We 09:00-17:00', 'Th 09:00-17:00', 'Fr 09:00-15:00' ] );

echo "\nget_stable_hours_for_description\n";
assert_same( '',                                    Testable_SEO_Helpers::get_stable_hours_for_description( '' ),           'empty string' );
assert_same( '',                                    Testable_SEO_Helpers::get_stable_hours_for_description( "  \n  " ),     'whitespace only' );
assert_same( '',                                    Testable_SEO_Helpers::get_stable_hours_for_description( 'Pn 9:00-17' ), 'invalid format' );
assert_same( 'codziennie 09:00–22:00',              Testable_SEO_Helpers::get_stable_hours_for_description( $all7_same ),   'all 7 days same' );
assert_same( 'całą dobę',                           Testable_SEO_Helpers::get_stable_hours_for_description( $all7_24h ),    'all 7 days 00:00-24:00' );
assert_same( 'całą dobę',                           Testable_SEO_Helpers::get_stable_hours_for_description( $all7_2359 ),   'all 7 days 00:00-23:59' );
assert_same( 'pn–pt 09:00–17:00',                  Testable_SEO_Helpers::get_stable_hours_for_description( $wd_only ),     'weekdays only uniform' );
assert_same( '',                                    Testable_SEO_Helpers::get_stable_hours_for_description( $wd_diff ),     'weekdays non-uniform' );
assert_same( 'codziennie 10:00–20:00',              Testable_SEO_Helpers::get_stable_hours_for_description( $we_same ),     'wd+we same hours' );
assert_same( 'pn–pt 09:00–17:00, sb–nd 10:00–22:00', Testable_SEO_Helpers::get_stable_hours_for_description( $we_diff ), 'wd+we different hours' );
assert_same( 'pn–sb 08:00–18:00',                  Testable_SEO_Helpers::get_stable_hours_for_description( $mon_sat ),     'mon-sat uniform' );
assert_same( '',                                    Testable_SEO_Helpers::get_stable_hours_for_description( $complex ),     'complex pattern' );
assert_same( '',                                    Testable_SEO_Helpers::get_stable_hours_for_description( 'Mo 09:00-17:00' ), 'single day only' );

echo "\npl_votes\n";
$cases = [
    0 => 'głosów', 1 => 'głos',   2 => 'głosy',  3 => 'głosy',
    4 => 'głosy',  5 => 'głosów', 11 => 'głosów', 12 => 'głosów',
    13 => 'głosów', 14 => 'głosów', 21 => 'głosów', 22 => 'głosy',
    23 => 'głosy', 24 => 'głosy', 25 => 'głosów', 100 => 'głosów',
    102 => 'głosy', 112 => 'głosów',
];
foreach ( $cases as $n => $expected ) {
    assert_same( $expected, Testable_SEO_Helpers::pl_votes( $n ), "pl_votes({$n})" );
}

// ── Summary ──────────────────────────────────────────────────────────────────
echo "\n" . str_repeat( '─', 40 ) . "\n";
$total = $pass + $fail;
if ( $fail === 0 ) {
    echo "\033[32mAll {$total} tests passed.\033[0m\n\n";
    exit( 0 );
} else {
    echo "\033[31m{$fail} of {$total} tests FAILED.\033[0m\n\n";
    exit( 1 );
}
