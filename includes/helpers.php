<?php
// Shared helper functions used by every page (MOD-01 foundation, week 1).

/**
 * Escape a value before printing it into HTML.
 * Turns < > & and quotes into harmless codes, so text typed by a user can never run as HTML or JavaScript (XSS).
 * Use it on everything that is printed — mock data included.
 */
function e($value)
{
    // The (string) cast lets numbers and null pass through without a PHP warning.
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
