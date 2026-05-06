<?php
/**
 * PHPUnit bootstrap — loads only what's needed for unit-testable pure functions.
 * Does NOT load WordPress or the full plugin.
 */
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/helpers/class-testable-seo-helpers.php';
