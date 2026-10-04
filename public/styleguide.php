<?php
// Style guide — dev-only gallery of every shared component, compared against wireframes p6–p7 (MOD-01, week 1).

$config = require __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mock-data.php';

$page_title = 'Style guide';

// Token name => [label, hex, use]. The hex is only printed as text; the swatch itself is coloured by the token.
$palette = [
    '--color-forest'     => ['Forest', '#2E4A2B', 'Headings, logo, selected states'],
    '--color-leaf'       => ['Leaf', '#4A7043', 'Primary buttons, links, bars'],
    '--color-sage'       => ['Sage', '#E4ECDC', 'Tags, tints, highlighted areas'],
    '--color-cream'      => ['Cream', '#F7F4EC', 'Page background'],
    '--color-terracotta' => ['Terracotta', '#B5552F', 'Accent, "due today"'],
    '--color-ink'        => ['Ink', '#2B2B28', 'Body text'],
    '--color-muted'      => ['Muted', '#6E6C62', 'Secondary text'],
    '--color-line'       => ['Line', '#E6E0D2', 'Borders and dividers'],
];

// Database value => [word shown to the user, meaning]. Pages will print plant health the same way.
$health_statuses = [
    'thriving'        => ['Thriving', 'Growing well'],
    'stable'          => ['Stable', 'No change, no action needed'],
    'needs_attention' => ['Needs attention', 'Act soon: care overdue or early warning'],
    'sick'            => ['Sick', 'Problem found, see care plan'],
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($page_title) ?> · PlantFolio</title>
  <!-- Fonts and stylesheets: these links move into includes/header.php in CORE-06 -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,600;1,400&family=Poppins:wght@400;500;600&display=swap">
  <link rel="stylesheet" href="<?= e($config['base_url']) ?>/assets/css/variables.css">
  <link rel="stylesheet" href="<?= e($config['base_url']) ?>/assets/css/components.css">
  <style>
    /* Layout for this gallery page only (sg- = style guide). The real page frame is layout.css, CORE-06. */
    .sg-page { max-width: var(--content-width); margin: 0 auto; padding: var(--space-24) var(--space-16) var(--space-48); }
    .sg-intro { margin-bottom: var(--space-32); color: var(--color-muted); }
    .sg-card { background: var(--color-white); border: 1px solid var(--color-line); border-radius: var(--radius-card);
               padding: var(--space-24) var(--space-16); margin-bottom: var(--space-24); }
    .sg-card > .label { margin-bottom: var(--space-16); }
    .sg-row { display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-12); margin-bottom: var(--space-16); }
    .sg-row:last-child { margin-bottom: 0; }
    .sg-row-spaced { margin-top: var(--space-16); }
    .sg-swatches { display: grid; grid-template-columns: repeat(2, 1fr); gap: var(--space-12); }
    .sg-swatch { border: 1px solid var(--color-line); border-radius: var(--radius-input); overflow: hidden; }
    .sg-swatch-colour { height: 56px; border-bottom: 1px solid var(--color-line); }
    .sg-swatch-text { padding: var(--space-8) var(--space-12); }
    .sg-swatch-text strong { display: block; }
    .sg-code { font-family: Consolas, monospace; font-size: var(--text-label); color: var(--color-muted); }
    .sg-type-row { display: grid; gap: var(--space-4); padding: var(--space-12) 0; border-top: 1px solid var(--color-line); }
    .sg-type-row:first-of-type { border-top: 0; padding-top: 0; }
    .sg-type-row > * { margin: 0; }
    .sg-status-row { display: grid; gap: var(--space-4); padding: var(--space-8) 0; border-top: 1px solid var(--color-line); }
    .sg-status-row > * { justify-self: start; }
    @media (min-width: 768px) {
      .sg-page { padding: var(--space-48) var(--gutter); }
      .sg-card { padding: var(--space-24); }
      .sg-swatches { grid-template-columns: repeat(4, 1fr); }
      .sg-type-row { grid-template-columns: 210px 1fr; align-items: baseline; gap: var(--space-24); }
      .sg-status-row { grid-template-columns: 210px 1fr auto; align-items: center; gap: var(--space-24); }
    }
  </style>
</head>
<body>
  <main class="sg-page">
    <h1>Style guide</h1>
    <p class="sg-intro">Every shared component, built once and reused on all pages. Compare with wireframes p6 and p7.</p>

    <section class="sg-card" aria-labelledby="sg-colours">
      <h2 class="label" id="sg-colours">Brand palette</h2>
      <div class="sg-swatches">
        <?php foreach ($palette as $token => [$name, $hex, $use]): ?>
          <div class="sg-swatch">
            <div class="sg-swatch-colour" style="background: var(<?= e($token) ?>)"></div>
            <div class="sg-swatch-text">
              <strong><?= e($name) ?></strong>
              <span class="sg-code"><?= e($hex) ?></span>
              <div class="text-small"><?= e($use) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="sg-card" aria-labelledby="sg-type">
      <h2 class="label" id="sg-type">Type scale</h2>
      <div class="sg-type-row">
        <span class="text-small">Display · Lora 600 · 46</span>
        <p class="display">Where your plants tell their story</p>
      </div>
      <div class="sg-type-row">
        <span class="text-small">Heading 1 · Lora 600 · 32</span>
        <h1>My Garden</h1>
      </div>
      <div class="sg-type-row">
        <span class="text-small">Heading 2 · Lora 600 · 24</span>
        <h2>Early blight <span class="scientific-name text-small">Alternaria solani</span></h2>
      </div>
      <div class="sg-type-row">
        <span class="text-small">Heading 3 · Poppins 600 · 18</span>
        <h3>Today's care</h3>
      </div>
      <div class="sg-type-row">
        <span class="text-small">Body · Poppins 400 · 15</span>
        <p>Give every plant its own profile and never miss a watering.</p>
      </div>
      <div class="sg-type-row">
        <span class="text-small">Small · Poppins 400 · 13</span>
        <p class="text-small">Updated 2 days ago · Living room</p>
      </div>
      <div class="sg-type-row">
        <span class="text-small">Label · Poppins 600 · 12 caps</span>
        <p class="label">Top match</p>
      </div>
    </section>

    <section class="sg-card" aria-labelledby="sg-buttons">
      <h2 class="label" id="sg-buttons">Buttons</h2>
      <div class="sg-row">
        <button type="button" class="btn btn-primary">Primary</button>
        <button type="button" class="btn btn-secondary">Secondary</button>
        <button type="button" class="btn btn-ghost">Ghost</button>
      </div>
      <div class="sg-row">
        <button type="button" class="btn btn-accent">Accent</button>
        <button type="button" class="btn btn-danger">Danger</button>
        <button type="button" class="btn btn-primary" disabled>Disabled</button>
      </div>
      <div class="sg-row">
        <button type="button" class="btn btn-primary btn-sm">Small</button>
        <button type="button" class="btn btn-primary">Medium</button>
        <button type="button" class="btn btn-primary btn-lg">Large</button>
      </div>
      <p class="text-small">One primary button per area. Labels are verbs.</p>
    </section>

    <section class="sg-card" aria-labelledby="sg-badges">
      <h2 class="label" id="sg-badges">Health status and badges</h2>
      <?php foreach ($health_statuses as $status => [$word, $meaning]): ?>
        <div class="sg-status-row">
          <span class="badge badge-<?= e(str_replace('_', '-', $status)) ?>"><?= e($word) ?></span>
          <span><?= e($meaning) ?></span>
          <span class="sg-code"><?= e($status) ?></span>
        </div>
      <?php endforeach; ?>
      <div class="sg-row sg-row-spaced">
        <span class="badge badge-tag">Care tag</span>
        <span class="badge badge-due">Due today</span>
        <span class="badge badge-warning">Warning</span>
        <span class="badge badge-neutral">Neutral</span>
      </div>
      <p class="text-small">Status is always shown with its word, never colour alone.</p>
    </section>
  </main>
</body>
</html>
