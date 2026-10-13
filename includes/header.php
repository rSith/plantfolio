<?php
// Page frame, top half: the <head>, the top bar with the navigation, and the opening <main> tag (CORE-06).
// A page sets $page_title and then includes this file; includes/footer.php closes what is opened here.
// Expects $current_user from the page: a users row, or null for a visitor (mock data now, the session in stage B).

$is_member = !empty($current_user);
$area      = current_area();

// 'area' is the name current_area() returns for the pages that belong to the link.
$nav_links = $is_member
    ? [
        ['label' => 'Dashboard', 'href' => 'dashboard.php', 'area' => 'dashboard'],
        ['label' => 'My Garden', 'href' => 'garden.php',    'area' => 'garden'],
        ['label' => 'Explore',   'href' => 'explore.php',   'area' => 'explore'],
        ['label' => 'Exchange',  'href' => 'exchange.php',  'area' => 'exchange'],
    ]
    : [
        ['label' => 'Explore',      'href' => 'explore.php',             'area' => 'explore'],
        ['label' => 'Exchange',     'href' => 'exchange.php',            'area' => 'exchange'],
        ['label' => 'How it works', 'href' => 'index.php#how-it-works',  'area' => null],
    ];

// The two AI tools share one menu, "Plant Tools" (members only).
$tool_links = [
    ['label' => 'Plant Identifier', 'href' => 'identify.php',     'icon' => 'sparkles'],
    ['label' => 'Health Check',     'href' => 'health-check.php', 'icon' => 'activity'],
];

if ($is_member) {
    $profile_url    = url('user.php?u=' . urlencode($current_user['username']));
    $interest_count = (int) ($current_user['new_interest_count'] ?? 0);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($page_title ?? 'Welcome') ?> · PlantFolio</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,600;1,400&family=Poppins:wght@400;500;600&display=swap">
  <link rel="stylesheet" href="<?= e(url('assets/css/variables.css')) ?>">
  <link rel="stylesheet" href="<?= e(url('assets/css/components.css')) ?>">
  <link rel="stylesheet" href="<?= e(url('assets/css/layout.css')) ?>">
</head>
<body<?= $is_member ? ' class="has-bottom-bar"' : '' ?>>
  <a class="skip-link" href="#main">Skip to content</a>

  <header class="site-header<?= $is_member ? ' site-header-member' : '' ?>">
    <div class="container site-header-inner">
      <a class="logo" href="<?= e(url($is_member ? 'dashboard.php' : '')) ?>" aria-label="PlantFolio home">
        <span class="logo-mark"><?= icon('leaf') ?></span>
        <span class="logo-text">PlantFolio</span>
      </a>

      <nav class="site-nav" aria-label="Main">
        <?php foreach ($nav_links as $link): ?>
          <a class="nav-link<?= $link['area'] === $area ? ' is-active' : '' ?>" href="<?= e(url($link['href'])) ?>"<?= $link['href'] === current_page() ? ' aria-current="page"' : '' ?>><?= e($link['label']) ?></a>
        <?php endforeach; ?>

        <?php if ($is_member): ?>
          <!-- <details> opens and closes by itself, so the menu works without JavaScript.
               Menus that share a name close each other: only one is open at a time. -->
          <details class="menu" name="site-menu">
            <summary class="nav-link<?= $area === 'tools' ? ' is-active' : '' ?>">
              <?= icon('sparkles') ?> Plant Tools <span class="menu-chevron"><?= icon('chevron-down') ?></span>
            </summary>
            <div class="menu-panel">
              <?php foreach ($tool_links as $link): ?>
                <a class="menu-item" href="<?= e(url($link['href'])) ?>"<?= $link['href'] === current_page() ? ' aria-current="page"' : '' ?>><?= icon($link['icon']) ?> <?= e($link['label']) ?></a>
              <?php endforeach; ?>
            </div>
          </details>
        <?php endif; ?>
      </nav>

      <div class="header-actions">
        <?php if ($is_member): ?>
          <a class="icon-btn" href="<?= e(url('explore.php')) ?>" aria-label="Search"><?= icon('search') ?></a>
          <a class="btn btn-primary btn-sm header-add" href="<?= e(url('plant-form.php')) ?>" aria-label="Add plant"><?= icon('plus') ?> <span class="header-add-label">Add plant</span></a>
          <a class="icon-btn" href="<?= e(url('my-exchanges.php')) ?>" aria-label="Notifications<?= $interest_count > 0 ? ': ' . e($interest_count) . ' new' : '' ?>">
            <?= icon('bell') ?>
            <?php if ($interest_count > 0): ?><span class="icon-btn-dot"></span><?php endif; ?>
          </a>
          <details class="menu" name="site-menu">
            <summary class="avatar-toggle" aria-label="Account menu"><?php partial('avatar', ['member' => $current_user]); ?></summary>
            <div class="menu-panel menu-panel-right">
              <div class="menu-head">
                <strong><?= e($current_user['full_name']) ?></strong>
                <span class="text-small">@<?= e($current_user['username']) ?></span>
              </div>
              <a class="menu-item" href="<?= e($profile_url) ?>"><?= icon('user') ?> My public profile</a>
              <a class="menu-item" href="<?= e(url('settings.php')) ?>"><?= icon('settings') ?> Settings</a>
              <a class="menu-item" href="<?= e(url('logout.php')) ?>"><?= icon('log-out') ?> Log out</a>
            </div>
          </details>
        <?php else: ?>
          <a class="btn btn-ghost btn-sm header-login" href="<?= e(url('login.php')) ?>">Log in</a>
          <a class="btn btn-primary btn-sm" href="<?= e(url('register.php')) ?>">Join free</a>
          <!-- Phone only: the links that do not fit in the bar -->
          <details class="menu header-menu-mobile" name="site-menu">
            <summary class="icon-btn" aria-label="Menu"><?= icon('menu') ?></summary>
            <div class="menu-panel menu-panel-right">
              <?php foreach ($nav_links as $link): ?>
                <a class="menu-item" href="<?= e(url($link['href'])) ?>"><?= e($link['label']) ?></a>
              <?php endforeach; ?>
              <hr class="menu-divider">
              <a class="menu-item" href="<?= e(url('login.php')) ?>">Log in</a>
            </div>
          </details>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <main id="main" class="site-main">
