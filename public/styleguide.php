<?php
// Style guide — dev-only gallery of every shared component, compared against wireframes p6–p7 (MOD-01, week 1).

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mock-data.php';

$page_title = 'Style guide';
$page_css   = 'styleguide.css';   // this page's own layout rules, loaded by header.php

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

// Database value => what it means (wireframe p6). The word and colour come from partials/status-badge.php.
$health_statuses = [
    'thriving'        => 'Growing well',
    'stable'          => 'No change, no action needed',
    'needs_attention' => 'Act soon: care overdue or early warning',
    'sick'            => 'Problem found, see care plan',
];

// A member for the samples: the owner columns of a listing row (username, full_name, avatar, rating).
// Not $current_user, which is null when the page is opened with ?guest.
// Found by id (Nimali's listing, 7), so adding listings to the mock data never changes the samples.
$sample_member = $mock_listings[array_search(7, array_column($mock_listings, 'id'))];

// Members for the member card: users rows found by id. Amaya carries a match_count, as when Explore searches "monstera".
$sample_members = [
    $mock_users[2] + ['match_count' => 2],
    $mock_users[3],
    $mock_users[4],
];

include __DIR__ . '/../includes/header.php';
?>
<div class="container page">
  <h1>Style guide</h1>
  <p class="sg-intro">Every shared component, built once and reused on all pages. Compare with wireframes p6 and p7.
    The top navigation from p7 is the real header of this page; add <span class="sg-code">?guest</span> to the address to see the visitor version, and <span class="sg-code">?member</span> to switch back.</p>

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
    <div class="sg-row">
      <button type="button" class="btn btn-primary"><?= icon('plus') ?> Add plant</button>
      <button type="button" class="btn btn-secondary btn-sm"><?= icon('check') ?> Mark done</button>
    </div>
    <p class="text-small">One primary button per area. Labels are verbs.</p>
  </section>

  <section class="sg-card" aria-labelledby="sg-menus">
    <h2 class="label" id="sg-menus">Icon buttons and dropdown menu</h2>
    <div class="sg-row">
      <button type="button" class="icon-btn" aria-label="Search"><?= icon('search') ?></button>
      <button type="button" class="icon-btn" aria-label="Notifications: 4 new"><?= icon('bell') ?><span class="icon-btn-dot"></span></button>
      <details class="menu" name="sg-menu">
        <summary class="btn btn-secondary">Plant Tools <span class="menu-chevron"><?= icon('chevron-down') ?></span></summary>
        <div class="menu-panel">
          <a class="menu-item" href="#sg-menus"><?= icon('sparkles') ?> Plant Identifier</a>
          <a class="menu-item" href="#sg-menus"><?= icon('activity') ?> Health Check</a>
          <hr class="menu-divider">
          <a class="menu-item" href="#sg-menus"><?= icon('settings') ?> Settings</a>
        </div>
      </details>
    </div>
    <p class="text-small">A button that shows only an icon always has an <span class="sg-code">aria-label</span>. The menu is a <span class="sg-code">&lt;details&gt;</span> element, so it opens without JavaScript.</p>
  </section>

  <section class="sg-card" aria-labelledby="sg-badges">
    <h2 class="label" id="sg-badges">Health status and badges</h2>
    <?php foreach ($health_statuses as $status => $meaning): ?>
      <div class="sg-status-row">
        <?php partial('status-badge', ['status' => $status]); ?>
        <span><?= e($meaning) ?></span>
        <span class="sg-code"><?= e($status) ?></span>
      </div>
    <?php endforeach; ?>
    <div class="sg-row sg-row-spaced">
      <span class="badge badge-tag"><?= icon('sun') ?> Care tag</span>
      <span class="badge badge-due">Due today</span>
      <span class="badge badge-warning"><?= icon('triangle-alert') ?> Warning</span>
      <span class="badge badge-neutral">Neutral</span>
    </div>
    <p class="text-small">Status is always shown with its word, never colour alone.</p>
  </section>

  <section class="sg-card" aria-labelledby="sg-forms">
    <h2 class="label" id="sg-forms">Form controls</h2>
    <div class="sg-grid-2">
      <div>
        <div class="field">
          <label class="field-label" for="sg-search">Default</label>
          <input class="input" type="text" id="sg-search" placeholder="Monty">
        </div>
        <div class="field">
          <label class="field-label" for="sg-search-icon">With an icon</label>
          <div class="input-icon">
            <?= icon('search') ?>
            <input class="input" type="search" id="sg-search-icon" placeholder="Search your garden">
          </div>
        </div>
        <div class="field">
          <label class="field-label" for="sg-username">With a hint</label>
          <input class="input" type="text" id="sg-username" value="<?= e($sample_member['username']) ?>" aria-describedby="sg-username-hint">
          <span class="field-hint" id="sg-username-hint">Click a field to see the focus state.</span>
        </div>
        <div class="field">
          <label class="field-label" for="sg-taken">Error</label>
          <input class="input" type="text" id="sg-taken" value="nimali" aria-invalid="true" aria-describedby="sg-taken-error">
          <span class="field-error" id="sg-taken-error">That username is taken</span>
        </div>
      </div>
      <div>
        <div class="field">
          <label class="field-label" for="sg-collection">Select</label>
          <select class="input" id="sg-collection">
            <option>Living room</option>
            <option>Balcony</option>
            <option>Succulents</option>
          </select>
        </div>
        <div class="field">
          <label class="field-label" for="sg-bio">Text area</label>
          <textarea class="input" id="sg-bio">Balcony gardener growing aroids and far too many pothos.</textarea>
        </div>
        <div class="sg-row">
          <label class="check"><input type="checkbox" checked> Check</label>
          <label class="check"><input type="radio" name="sg-radio" checked> Radio</label>
          <label class="check"><input type="radio" name="sg-radio"> Radio</label>
          <label class="toggle"><input type="checkbox" role="switch" checked> Toggle</label>
        </div>
      </div>
    </div>
  </section>

  <section class="sg-card" aria-labelledby="sg-chips">
    <h2 class="label" id="sg-chips">Chips and segmented control</h2>
    <div class="sg-row">
      <a class="chip is-selected" href="#sg-chips" aria-current="true">All plants <span class="chip-count">18</span></a>
      <a class="chip" href="#sg-chips">Living room <span class="chip-count">7</span></a>
      <a class="chip" href="#sg-chips">Balcony <span class="chip-count">6</span></a>
      <a class="chip" href="#sg-chips"><?= icon('lock') ?> Succulents <span class="visually-hidden">(private)</span> <span class="chip-count">5</span></a>
      <button type="button" class="chip chip-add"><?= icon('plus') ?> New collection</button>
    </div>
    <div class="sg-grid-2">
      <fieldset class="segmented">
        <legend class="visually-hidden">Listing type</legend>
        <input type="radio" name="sg-type" id="sg-type-swap" value="swap" checked>
        <label for="sg-type-swap">Swap</label>
        <input type="radio" name="sg-type" id="sg-type-free" value="free">
        <label for="sg-type-free">Free</label>
        <input type="radio" name="sg-type" id="sg-type-either" value="either">
        <label for="sg-type-either">Either</label>
      </fieldset>
    </div>
    <p class="text-small sg-note">The selector is three radio buttons, so the arrow keys move between the choices.</p>
  </section>

  <section class="sg-card" aria-labelledby="sg-tabs">
    <h2 class="label" id="sg-tabs">Tabs, breadcrumb, avatars</h2>
    <nav class="tabs" aria-label="Plant sections">
      <a class="tab is-active" href="#sg-tabs" aria-current="page">Story</a>
      <a class="tab" href="#sg-tabs">Care guide</a>
      <a class="tab" href="#sg-tabs">Reminders</a>
      <a class="tab" href="#sg-tabs">Photos <span class="tab-count">12</span></a>
    </nav>
    <nav class="sg-row sg-row-spaced" aria-label="Breadcrumb">
      <ol class="breadcrumb">
        <li><a href="#sg-tabs">My Garden</a></li>
        <li><a href="#sg-tabs">Living room</a></li>
        <li aria-current="page">Monty</li>
      </ol>
    </nav>
    <div class="sg-row">
      <?php partial('avatar', ['member' => $sample_member, 'size' => 'sm']); ?>
      <?php partial('avatar', ['member' => $sample_member]); ?>
      <?php partial('avatar', ['member' => $sample_member, 'size' => 'lg']); ?>
      <span class="avatar avatar-tint-1" aria-hidden="true">NP</span>
      <span class="avatar avatar-tint-2" aria-hidden="true">DG</span>
      <span class="avatar avatar-tint-3" aria-hidden="true">AM</span>
      <span class="avatar avatar-tint-4" aria-hidden="true">RR</span>
      <span class="avatar-stack">
        <span class="avatar avatar-sm avatar-tint-2" aria-hidden="true">DG</span>
        <span class="avatar avatar-sm avatar-tint-3" aria-hidden="true">AM</span>
        <span class="avatar avatar-sm avatar-tint-4" aria-hidden="true">RR</span>
      </span>
    </div>
    <p class="text-small">Small, medium and large come from <span class="sg-code">partials/avatar.php</span>; a member's tint is chosen from their username.</p>
  </section>

  <section class="sg-card" aria-labelledby="sg-plant-cards">
    <h2 class="label" id="sg-plant-cards">Plant card</h2>
    <div class="sg-cards">
      <?php foreach ($mock_plants as $plant): ?>
        <?php partial('plant-card', ['plant' => $plant]); ?>
      <?php endforeach; ?>
    </div>
    <p class="text-small sg-note">The hint is terracotta when care is due today and red when it is overdue. Photos are placeholder drawings until real ones are added.</p>
  </section>

  <section class="sg-card" aria-labelledby="sg-listing-cards">
    <h2 class="label" id="sg-listing-cards">Listing card</h2>
    <div class="sg-cards sg-cards-wide">
      <?php // Open listings only: the card has no "closed" look, because closed listings are never shown ?>
      <?php foreach (array_filter($mock_listings, fn ($listing) => $listing['status'] === 'open') as $listing): ?>
        <?php partial('listing-card', ['listing' => $listing]); ?>
      <?php endforeach; ?>
    </div>
    <p class="text-small sg-note">Contact details never appear on a card. Cards link to pages that are built in weeks 3 and 4.</p>
  </section>

  <section class="sg-card" aria-labelledby="sg-member-cards">
    <h2 class="label" id="sg-member-cards">Member card</h2>
    <div class="sg-cards">
      <?php foreach ($sample_members as $member): ?>
        <?php partial('member-card', ['member' => $member]); ?>
      <?php endforeach; ?>
    </div>
    <div class="sg-cards sg-cards-wide sg-row-spaced">
      <?php foreach ($sample_members as $member): ?>
        <?php partial('member-card', ['member' => $member, 'compact' => true]); ?>
      <?php endforeach; ?>
    </div>
    <p class="text-small sg-note">Explore's People tab (upright from tablet width, avatar beside the text on a phone) and the compact card of its "Plant lovers matching" row. Each opens the member's Public Profile; email and phone are never shown.</p>
  </section>

  <section class="sg-card" aria-labelledby="sg-care">
    <h2 class="label" id="sg-care">Care row, confidence, rating</h2>
    <div class="sg-grid-2">
      <div>
        <?php foreach ($mock_reminders as $reminder): ?>
          <?php partial('care-row', ['reminder' => $reminder]); ?>
        <?php endforeach; ?>
      </div>
      <div>
        <?php foreach ([0.88, 0.64, 0.42] as $confidence): ?>
          <div class="sg-row">
            <?php partial('confidence-meter', ['prediction' => ['confidence' => $confidence]]); ?>
          </div>
        <?php endforeach; ?>
        <p class="text-small">High 80%+ (green) · Medium 60–79% (amber) · Not sure below 60% (grey)</p>
        <div class="sg-row">
          <?php partial('rating', ['rating' => $sample_member]); ?>
          <?php partial('stars', ['score' => 4]); ?>
          <?php partial('stars', ['score' => 5, 'size' => 'sm', 'show_score' => true]); ?>
        </div>
      </div>
    </div>
  </section>

  <section class="sg-card" aria-labelledby="sg-feedback">
    <h2 class="label" id="sg-feedback">Feedback</h2>
    <div class="sg-grid-2">
      <div>
        <div class="toast" role="status">
          <?= icon('check') ?>
          <span class="toast-message">Watered Monty · next on <?= e(format_day(mock_date(7))) ?></span>
          <button type="button" class="toast-action">Undo</button>
        </div>
        <p class="text-small sg-note">On a real page the toast sits at the bottom of the screen and hides by itself (week 5).</p>
      </div>
      <div>
        <div class="sg-row"><div class="alert alert-warning"><?= icon('triangle-alert') ?> Suggestion, not a diagnosis.</div></div>
        <div class="sg-row"><div class="alert"><?= icon('info') ?> You can change this later.</div></div>
        <div class="sg-row"><div class="alert alert-success"><?= icon('check') ?> Interest sent</div></div>
        <div class="sg-row"><div class="alert alert-danger"><?= icon('triangle-alert') ?> We could not save your changes. Try again.</div></div>
      </div>
    </div>
  </section>

  <section class="sg-card" aria-labelledby="sg-empty">
    <h2 class="label" id="sg-empty">Empty state (S1)</h2>
    <?php partial('empty-state', [
        'title'   => 'Your garden is waiting',
        'text'    => 'Add your first plant to start its story.',
        'actions' => [
            ['label' => 'Add a plant',           'href' => '#sg-empty', 'icon' => 'plus'],
            ['label' => 'Identify from a photo', 'href' => '#sg-empty', 'icon' => 'sparkles'],
        ],
    ]); ?>
  </section>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
