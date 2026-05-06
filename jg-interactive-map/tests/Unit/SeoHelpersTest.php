<?php

namespace JGMap\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Testable_SEO_Helpers;

class SeoHelpersTest extends TestCase {

    // -------------------------------------------------------------------------
    // get_stable_hours_for_description
    // -------------------------------------------------------------------------

    /** @dataProvider stableHoursProvider */
    public function test_get_stable_hours( string $input, string $expected ): void {
        $this->assertSame( $expected, Testable_SEO_Helpers::get_stable_hours_for_description( $input ) );
    }

    public function stableHoursProvider(): array {
        $all_days_same = implode( "\n", array(
            'Mo 09:00-22:00', 'Tu 09:00-22:00', 'We 09:00-22:00',
            'Th 09:00-22:00', 'Fr 09:00-22:00', 'Sa 09:00-22:00', 'Su 09:00-22:00',
        ) );
        $all_days_24h = implode( "\n", array(
            'Mo 00:00-24:00', 'Tu 00:00-24:00', 'We 00:00-24:00',
            'Th 00:00-24:00', 'Fr 00:00-24:00', 'Sa 00:00-24:00', 'Su 00:00-24:00',
        ) );
        $all_days_2359 = implode( "\n", array(
            'Mo 00:00-23:59', 'Tu 00:00-23:59', 'We 00:00-23:59',
            'Th 00:00-23:59', 'Fr 00:00-23:59', 'Sa 00:00-23:59', 'Su 00:00-23:59',
        ) );
        $weekdays_only = "Mo 09:00-17:00\nTu 09:00-17:00\nWe 09:00-17:00\nTh 09:00-17:00\nFr 09:00-17:00";
        $weekdays_diff = "Mo 08:00-17:00\nTu 09:00-17:00\nWe 09:00-17:00\nTh 09:00-17:00\nFr 09:00-17:00";
        $wd_we_same    = implode( "\n", array(
            'Mo 10:00-20:00', 'Tu 10:00-20:00', 'We 10:00-20:00',
            'Th 10:00-20:00', 'Fr 10:00-20:00', 'Sa 10:00-20:00', 'Su 10:00-20:00',
        ) );
        $wd_we_diff    = implode( "\n", array(
            'Mo 09:00-17:00', 'Tu 09:00-17:00', 'We 09:00-17:00',
            'Th 09:00-17:00', 'Fr 09:00-17:00', 'Sa 10:00-22:00', 'Su 10:00-22:00',
        ) );
        $mon_sat       = implode( "\n", array(
            'Mo 08:00-18:00', 'Tu 08:00-18:00', 'We 08:00-18:00',
            'Th 08:00-18:00', 'Fr 08:00-18:00', 'Sa 08:00-18:00',
        ) );
        $complex       = implode( "\n", array(
            'Mo 09:00-17:00', 'Tu 10:00-18:00', 'We 09:00-17:00',
            'Th 09:00-17:00', 'Fr 09:00-15:00',
        ) );

        return array(
            'empty string'              => array( '',               '' ),
            'empty whitespace'          => array( "   \n  ",        '' ),
            'invalid format'            => array( 'Pn 9:00-17:00', '' ),
            'all 7 days same hours'     => array( $all_days_same,   'codziennie 09:00–22:00' ),
            'all 7 days 24h (24:00)'    => array( $all_days_24h,    'całą dobę' ),
            'all 7 days 24h (23:59)'    => array( $all_days_2359,   'całą dobę' ),
            'weekdays only uniform'     => array( $weekdays_only,   'pn–pt 09:00–17:00' ),
            'weekdays non-uniform'      => array( $weekdays_diff,   '' ),
            'wd+we same hours'          => array( $wd_we_same,      'codziennie 10:00–20:00' ),
            'wd+we different hours'     => array( $wd_we_diff,      'pn–pt 09:00–17:00, sb–nd 10:00–22:00' ),
            'mon-sat uniform'           => array( $mon_sat,         'pn–sb 08:00–18:00' ),
            'complex pattern'           => array( $complex,         '' ),
            'single day'                => array( 'Mo 09:00-17:00', '' ),
        );
    }

    // -------------------------------------------------------------------------
    // pl_votes
    // -------------------------------------------------------------------------

    /** @dataProvider plVotesProvider */
    public function test_pl_votes( int $n, string $expected ): void {
        $this->assertSame( $expected, Testable_SEO_Helpers::pl_votes( $n ) );
    }

    public function plVotesProvider(): array {
        return array(
            '0'   => array( 0,   'głosów' ),
            '1'   => array( 1,   'głos'   ),
            '2'   => array( 2,   'głosy'  ),
            '3'   => array( 3,   'głosy'  ),
            '4'   => array( 4,   'głosy'  ),
            '5'   => array( 5,   'głosów' ),
            '11'  => array( 11,  'głosów' ),
            '12'  => array( 12,  'głosów' ),
            '13'  => array( 13,  'głosów' ),
            '14'  => array( 14,  'głosów' ),
            '21'  => array( 21,  'głosów' ),
            '22'  => array( 22,  'głosy'  ),
            '23'  => array( 23,  'głosy'  ),
            '24'  => array( 24,  'głosy'  ),
            '25'  => array( 25,  'głosów' ),
            '100' => array( 100, 'głosów' ),
            '102' => array( 102, 'głosy'  ),
            '112' => array( 112, 'głosów' ),
        );
    }
}
