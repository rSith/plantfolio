<?php
// Page frame, bottom half: closes <main>, then prints the footer and the phone bottom bar (CORE-06).
// Included last by every page. It reuses $is_member, $area and $profile_url, which includes/header.php set.

// The three links are named on wireframe p4, but no page exists for them yet: '#' is a placeholder.
$footer_links = [
    ['label' => 'About',                'href' => '#'],
    ['label' => 'Community guidelines', 'href' => '#'],
    ['label' => 'Contact',              'href' => '#'],
];

// The phone bar, left and right of the round "+" button (wireframe p23).
if ($is_member) {
    $bar_links_left = [
        ['label' => 'Home',   'href' => url('dashboard.php'), 'icon' => 'house', 'area' => 'dashboard'],
        ['label' => 'Garden', 'href' => url('garden.php'),    'icon' => 'leaf',  'area' => 'garden'],
    ];
    $bar_links_right = [
        ['label' => 'Exchange', 'href' => url('exchange.php'), 'icon' => 'repeat', 'area' => 'exchange'],
        ['label' => 'Profile',  'href' => $profile_url,        'icon' => 'user',   'area' => 'profile'],
    ];
    $add_links = [
        ['label' => 'Add a plant',      'href' => 'plant-form.php',   'icon' => 'plus'],
        ['label' => 'Identify a plant', 'href' => 'identify.php',     'icon' => 'sparkles'],
        ['label' => 'Health check',     'href' => 'health-check.php', 'icon' => 'activity'],
    ];
}
?>
  </main>

  <footer class="site-footer">
    <div class="container site-footer-inner">
      <div class="footer-brand">
        <span class="logo">
          <span class="logo-mark"><?= icon('leaf') ?></span>
          <span>PlantFolio</span>
        </span>
        <p class="text-small">Where your plants tell their story.</p>
      </div>
      <nav class="footer-links" aria-label="Footer">
        <?php foreach ($footer_links as $link): ?>
          <a href="<?= e($link['href']) ?>"><?= e($link['label']) ?></a>
        <?php endforeach; ?>
      </nav>
      <p class="footer-note text-small">&copy; <?= e(date('Y')) ?> PlantFolio · Group coursework, Wayamba University of Sri Lanka</p>
    </div>
  </footer>

  <?php if ($is_member): ?>
    <!-- Phones only: layout.css hides this bar from 768px, where the top navigation takes over -->
    <nav class="bottom-bar" aria-label="Main">
      <?php foreach ($bar_links_left as $link): ?>
        <a class="bottom-bar-link<?= $link['area'] === $area ? ' is-active' : '' ?>" href="<?= e($link['href']) ?>"><?= icon($link['icon']) ?> <?= e($link['label']) ?></a>
      <?php endforeach; ?>

      <details class="menu bottom-bar-add" name="site-menu">
        <summary class="bottom-bar-plus" aria-label="Add or check a plant"><?= icon('plus') ?></summary>
        <div class="menu-panel menu-panel-sheet">
          <?php foreach ($add_links as $link): ?>
            <a class="menu-item" href="<?= e(url($link['href'])) ?>"><?= icon($link['icon']) ?> <?= e($link['label']) ?></a>
          <?php endforeach; ?>
        </div>
      </details>

      <?php foreach ($bar_links_right as $link): ?>
        <a class="bottom-bar-link<?= $link['area'] === $area ? ' is-active' : '' ?>" href="<?= e($link['href']) ?>"><?= icon($link['icon']) ?> <?= e($link['label']) ?></a>
      <?php endforeach; ?>
    </nav>
  <?php endif; ?>
</body>
</html>
