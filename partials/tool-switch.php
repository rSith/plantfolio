<?php
// The Identify · Health check switch at the top of both Plant Tools (ML-12, ML-14, wireframes p14 and p15, element 1).
// Expects $active — 'identify' or 'health'. Two links drawn like the segmented control, the current page pressed:
// the tools are two pages, so switching is a page change that needs no JavaScript (like the Sign up · Log in switch).

$tool_switch = [
    'identify' => ['label' => 'Identify',     'href' => 'identify.php',     'icon' => 'sparkles'],
    'health'   => ['label' => 'Health check', 'href' => 'health-check.php', 'icon' => 'activity'],
];
?>
<nav class="tool-switch" aria-label="Plant tools">
  <?php foreach ($tool_switch as $key => $tool): ?>
    <a class="tool-switch-link<?= $key === $active ? ' is-active' : '' ?>" href="<?= e(url($tool['href'])) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>><?= icon($tool['icon']) ?> <?= e($tool['label']) ?></a>
  <?php endforeach; ?>
</nav>
