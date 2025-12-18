<?php
/**
 * Clear PHP OPcache
 * Access this file via browser, then DELETE it
 */

echo "<h1>PHP Cache Clear</h1>";

if (function_exists('opcache_reset')) {
    if (opcache_reset()) {
        echo "<p style='color:green'>✓ OPcache cleared successfully!</p>";
    } else {
        echo "<p style='color:red'>✗ Failed to clear OPcache</p>";
    }
} else {
    echo "<p style='color:orange'>⚠ OPcache is not enabled</p>";
}

if (function_exists('opcache_get_status')) {
    echo "<h2>OPcache Status:</h2>";
    echo "<pre>";
    print_r(opcache_get_status());
    echo "</pre>";
}

echo "<hr><p><strong>REMEMBER TO DELETE THIS FILE AFTER USE!</strong></p>";
