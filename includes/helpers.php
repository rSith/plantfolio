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

/**
 * Render a reusable piece from partials/ and hand it named variables:
 * partial('plant-card', ['plant' => $plant]) runs partials/plant-card.php with $plant available inside it.
 * Because it runs inside this function, the partial's own variables never leak into the page.
 */
function partial($partial_name, array $partial_data = [])
{
    extract($partial_data);   // turns ['plant' => …] into the variable $plant

    include __DIR__ . '/../partials/' . $partial_name . '.php';
}

/**
 * Address of a stored photo. The data holds only a path such as "mock/monty.svg".
 * Stage F: photos live in public/assets/img/. Stage B points this one line at public/uploads/ instead.
 */
function photo_url($photo)
{
    return url('assets/img/' . $photo);
}

/**
 * First letters of the first and last name, for avatars without a photo: "Nimali Perera" gives "NP".
 * The mb_ functions count letters, not bytes, so Sinhala and Tamil names work too.
 */
function initials($full_name)
{
    $words = preg_split('/\s+/', trim($full_name));
    $first = mb_substr($words[0], 0, 1);
    $last  = count($words) > 1 ? mb_substr(end($words), 0, 1) : '';

    return mb_strtoupper($first . $last);
}

/**
 * Pick one of the four avatar colours (1–4) from the username.
 * crc32() turns the text into a number, so the same member gets the same colour on every page.
 */
function avatar_tint($username)
{
    return crc32($username) % 4 + 1;
}

/**
 * The word shown for a health_status value from the database, e.g. needs_attention gives "Needs attention".
 * Kept in one place so every page uses exactly the same four words.
 */
function health_label($status)
{
    $labels = [
        'thriving'        => 'Thriving',
        'stable'          => 'Stable',
        'needs_attention' => 'Needs attention',
        'sick'            => 'Sick',
    ];

    return $labels[$status] ?? 'Unknown';
}

/** The word for a care_reminders.task value: water gives "Water". */
function task_label($task)
{
    $labels = ['water' => 'Water', 'fertilise' => 'Fertilise'];

    return $labels[$task] ?? ucfirst($task);
}

/** The icon for a care task: a droplet for watering, a sprout for fertilising. */
function task_icon($task)
{
    return $task === 'fertilise' ? 'sprout' : 'droplet';
}

/** The words and icon for an exchange_listings.type value (swap, free or either). */
function listing_type($type)
{
    $types = [
        'swap'   => ['label' => 'Swap', 'icon' => 'repeat'],
        'free'   => ['label' => 'Free', 'icon' => 'gift'],
        'either' => ['label' => 'Swap or free', 'icon' => 'repeat'],
    ];

    return $types[$type] ?? $types['swap'];
}

/** Whole days from today to a date: 0 is today, 3 is in three days, -2 is two days ago. */
function days_until($date)
{
    $today  = new DateTime('today');
    $target = new DateTime(substr($date, 0, 10));   // keep the date, drop any time of day

    return (int) $today->diff($target)->format('%r%a');
}

/**
 * Describe when a reminder is due, from its next_due date.
 * Returns two things: 'state' (overdue, due or upcoming), which decides the colour — red for overdue,
 * terracotta for due today — and 'text', the words to show ("Overdue 2 days", "Due today", "In 3 days").
 */
function care_due($next_due)
{
    $days = days_until($next_due);

    if ($days < 0) {
        $late = abs($days);
        return ['state' => 'overdue', 'text' => 'Overdue ' . $late . ($late === 1 ? ' day' : ' days')];
    }
    if ($days === 0) {
        return ['state' => 'due', 'text' => 'Due today'];
    }
    if ($days === 1) {
        return ['state' => 'upcoming', 'text' => 'Tomorrow'];
    }

    return ['state' => 'upcoming', 'text' => 'In ' . $days . ' days'];
}

/** How long ago something happened, in the wireframes' wording: "Today", "2 days ago", "1 week ago". */
function time_ago($datetime)
{
    $days = -days_until($datetime);

    if ($days <= 0) {
        return 'Today';
    }
    if ($days === 1) {
        return 'Yesterday';
    }
    if ($days < 7) {
        return $days . ' days ago';
    }
    if ($days < 35) {
        $weeks = intdiv($days, 7);
        return $weeks . ($weeks === 1 ? ' week ago' : ' weeks ago');
    }

    $months = intdiv($days, 30);
    return $months . ($months === 1 ? ' month ago' : ' months ago');
}

/** A short day for messages, e.g. "7 Oct". */
function format_day($date)
{
    return date('j M', strtotime($date));
}

/**
 * The fixed confidence word for an ML result. The limits are a design system rule and must not change:
 * High from 80%, Medium from 60% to 79%, Not sure below 60%.
 * $confidence is between 0 and 1, as the ML service returns it. The word is decided from the rounded
 * percentage, so it always agrees with the number the user sees.
 * Returns 'key' (used in CSS class names) and 'label' (the word shown).
 */
function confidence_level($confidence)
{
    $percent = round($confidence * 100);

    if ($percent >= 80) {
        return ['key' => 'high', 'label' => 'High'];
    }
    if ($percent >= 60) {
        return ['key' => 'medium', 'label' => 'Medium'];
    }

    return ['key' => 'low', 'label' => 'Not sure'];
}
