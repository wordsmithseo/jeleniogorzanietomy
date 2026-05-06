<?php
/**
 * Exposes private SEO helper methods from JG_Interactive_Map for unit testing.
 * Extracted inline so tests run without WordPress.
 */
class Testable_SEO_Helpers {

    public static function get_stable_hours_for_description( $opening_hours ) {
        if ( empty( $opening_hours ) ) {
            return '';
        }
        $day_hours = array();
        foreach ( explode( "\n", trim( $opening_hours ) ) as $line ) {
            $line = trim( $line );
            if ( preg_match( '/^(Mo|Tu|We|Th|Fr|Sa|Su)\s+(\d{2}:\d{2})-(\d{2}:\d{2})$/', $line, $m ) ) {
                $day_hours[ $m[1] ] = $m[2] . '–' . $m[3];
            }
        }
        if ( empty( $day_hours ) ) {
            return '';
        }
        $weekdays  = array( 'Mo', 'Tu', 'We', 'Th', 'Fr' );
        $weekend   = array( 'Sa', 'Su' );
        $all_7     = array( 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su' );
        $wday_vals = array();
        foreach ( $weekdays as $d ) {
            if ( isset( $day_hours[ $d ] ) ) {
                $wday_vals[] = $day_hours[ $d ];
            }
        }
        $wend_vals = array();
        foreach ( $weekend as $d ) {
            if ( isset( $day_hours[ $d ] ) ) {
                $wend_vals[] = $day_hours[ $d ];
            }
        }
        $has_all_7   = count( array_diff( $all_7, array_keys( $day_hours ) ) ) === 0;
        $wday_unique = array_unique( $wday_vals );
        $wend_unique = array_unique( $wend_vals );

        if ( $has_all_7 && count( array_unique( array_values( $day_hours ) ) ) === 1 ) {
            $h = reset( $day_hours );
            if ( $h === '00:00–24:00' || $h === '00:00–23:59' ) {
                return 'całą dobę';
            }
            return 'codziennie ' . $h;
        }
        if ( count( $wday_vals ) === 5 && count( $wend_vals ) === 0 && count( $wday_unique ) === 1 ) {
            return 'pn–pt ' . $wday_unique[0];
        }
        if ( count( $wday_vals ) === 5 && count( $wend_vals ) === 2 && count( $wday_unique ) === 1 && count( $wend_unique ) === 1 ) {
            if ( $wday_unique[0] === $wend_unique[0] ) {
                return 'codziennie ' . $wday_unique[0];
            }
            return 'pn–pt ' . $wday_unique[0] . ', sb–nd ' . $wend_unique[0];
        }
        $mon_sat   = array( 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa' );
        $msat_vals = array();
        foreach ( $mon_sat as $d ) {
            if ( isset( $day_hours[ $d ] ) ) {
                $msat_vals[] = $day_hours[ $d ];
            }
        }
        if ( count( $msat_vals ) === 6 && ! isset( $day_hours['Su'] ) && count( array_unique( $msat_vals ) ) === 1 ) {
            return 'pn–sb ' . $msat_vals[0];
        }
        return '';
    }

    public static function pl_votes( $n ) {
        $n      = (int) $n;
        $mod10  = $n % 10;
        $mod100 = $n % 100;
        if ( $n === 1 ) {
            return 'głos';
        }
        if ( $mod10 >= 2 && $mod10 <= 4 && ( $mod100 < 10 || $mod100 >= 20 ) ) {
            return 'głosy';
        }
        return 'głosów';
    }
}
