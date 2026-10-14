<?php
// Plant card: photo, health badge, nickname, species, next-care hint and collection (CORE-05, wireframe p11).
// Expects $plant — a plants row joined with its species, collection and soonest reminder (see $mock_plants).
// Optional $menu = true adds the owner's ⋯ menu (partials/plant-menu.php); only My Garden passes it.

$due = care_hint($plant);   // null when the plant has no reminder yet
?>
<article class="card plant-card">
  <div class="card-media">
    <img src="<?= e(photo_url($plant['photo'])) ?>" alt="Photo of <?= e($plant['nickname']) ?>" loading="lazy">
    <?php partial('status-badge', ['status' => $plant['health_status']]); ?>
    <?php if (!empty($menu)): ?>
      <?php partial('plant-menu', ['plant' => $plant]); ?>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <h3 class="plant-card-name">
      <a class="card-link" href="<?= e(url('plant.php?id=' . $plant['id'])) ?>"><?= e($plant['nickname']) ?></a>
    </h3>
    <p class="scientific-name"><?= e($plant['scientific_name']) ?></p>
    <div class="plant-card-meta">
      <?php if ($due): ?>
        <span class="care-hint care-hint-<?= e($due['state']) ?>"><?= icon(task_icon($plant['task'])) ?> <?= e($due['text']) ?></span>
      <?php endif; ?>
      <span class="plant-card-collection"><?= e($plant['collection_name']) ?></span>
    </div>
  </div>
</article>
