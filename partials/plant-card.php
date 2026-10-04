<?php
// Plant card: photo, health badge, nickname, species, next-care hint and collection (CORE-05, wireframe p11).
// Expects $plant — a plants row joined with its species, collection and soonest reminder (see $mock_plants).

$due  = null;
$hint = '';

// A plant may have no reminder yet, so the hint is optional.
if ($plant['next_due']) {
    $due = care_due($plant['next_due']);
    // On a card, a task due today reads "Water today" rather than "Due today" (p11).
    $hint = $due['state'] === 'due' ? task_label($plant['task']) . ' today' : $due['text'];
}
?>
<article class="card plant-card">
  <div class="card-media">
    <img src="<?= e(photo_url($plant['photo'])) ?>" alt="Photo of <?= e($plant['nickname']) ?>" loading="lazy">
    <?php partial('status-badge', ['status' => $plant['health_status']]); ?>
  </div>
  <div class="card-body">
    <h3 class="plant-card-name">
      <a class="card-link" href="<?= e(url('plant.php?id=' . $plant['id'])) ?>"><?= e($plant['nickname']) ?></a>
    </h3>
    <p class="scientific-name"><?= e($plant['scientific_name']) ?></p>
    <div class="plant-card-meta">
      <?php if ($due): ?>
        <span class="care-hint care-hint-<?= e($due['state']) ?>"><?= icon(task_icon($plant['task'])) ?> <?= e($hint) ?></span>
      <?php endif; ?>
      <span class="plant-card-collection"><?= e($plant['collection_name']) ?></span>
    </div>
  </div>
</article>
