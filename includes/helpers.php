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

/**
 * Read one setting from config/config.php, e.g. config('base_url').
 * A static variable keeps its value between calls, so the file is only loaded the first time.
 */
function config($key)
{
    static $config = null;

    if ($config === null) {
        $config = require __DIR__ . '/../config/config.php';
    }

    return $config[$key] ?? null;
}

// XAMPP ships with another country's time zone (Europe/Berlin), which would make "due today"
// wrong for several hours every evening. The fallback covers a config.php copied before this setting existed.
date_default_timezone_set(config('timezone') ?? 'Asia/Colombo');

/**
 * Build a full address from a path inside public/, e.g. url('assets/css/variables.css').
 * Every link and file address goes through here, so moving the site means changing base_url only.
 */
function url($path = '')
{
    return rtrim(config('base_url'), '/') . '/' . ltrim($path, '/');
}

/**
 * Return a Lucide icon as inline SVG, e.g. icon('droplet'). Print it without e(): it is our own file, not user text.
 * Inline SVG (instead of <img>) lets the icon take the colour of the text around it.
 */
function icon($name)
{
    $file = __DIR__ . '/../public/assets/img/icons/' . $name . '.svg';

    // Letters, digits and hyphens only, so a name can never point at a file outside the icons folder.
    if (!preg_match('/^[a-z0-9-]+$/', $name) || !is_file($file)) {
        trigger_error('Icon not found: ' . $name, E_USER_WARNING);
        return '';
    }

    $svg = file_get_contents($file);
    $svg = preg_replace('/<!--.*?-->\s*/s', '', $svg);   // the licence comment stays in the file, not in every page

    // Decorative by default: the text next to an icon carries the meaning for screen readers.
    return preg_replace('/class="[^"]*"/', 'class="icon" aria-hidden="true"', $svg, 1);
}
