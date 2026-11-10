<?php
// Plant stack card: two or more plants of the same species in the same collection, shown as one card in My Garden
// (prototype on feature/plant-stacks, CORE-14). Each plant keeps its own health and reminders: the card counts the
// health words, shows the soonest care, and opens a popup that lists every plant with a link to its own profile.
// Expects $plants — two or more plants rows (see $mock_plants), in the order My Garden sorted them.
// Expects $id — a unique id for the popup, because a page can hold several stacks.

$first = $plants[0];
$count = count($plants);

// Health words, worst first, with how many plants have each: "1 Sick", "2 Thriving"
$health_counts = array_count_values(array_column($plants, 'health_status'));
$health_order  = ['sick', 'needs_attention', 'stable', 'thriving'];

// The soonest care of all of them: the one that needs doing first is the one worth showing
$soonest = null;
foreach ($plants as $plant) {
    if ($plant['next_due'] !== null && ($soonest === null || $plant['next_due'] < $soonest['next_due'])) {
        $soonest = $plant;
    }
}
$due = $soonest ? care_hint($soonest) : null;
?>
<article class="card plant-card plant-stack">
  <div class="card-media">
    <img src="<?= e(photo_url($first['photo'])) ?>" alt="" loading="lazy">
    <span class="badge plant-stack-count"><?= e('×' . $count) ?><span class="visually-hidden"> plants</span></span>
  </div>
  <div class="card-body">
    <h3 class="plant-card-name">
      <?php // A button, not a link: it opens the popup on this page. Its ::after stretches over the whole card. ?>
      <button type="button" class="card-link plant-stack-open" data-dialog="<?= e($id) ?>" aria-haspopup="dialog">
        <?= e($first['common_name']) ?><span class="visually-hidden">, <?= e($count) ?> plants</span>
      </button>
    </h3>
    <p class="scientific-name"><?= e($first['scientific_name']) ?></p>
    <?php // Word and colour together, never colour alone (design system) ?>
    <p class="plant-stack-health">
      <?php foreach ($health_order as $status): ?>
        <?php if (!empty($health_counts[$status])): ?>
          <span class="badge badge-<?= e(str_replace('_', '-', $status)) ?>"><?= e($health_counts[$status] . ' ' . health_label($status)) ?></span>
        <?php endif; ?>
      <?php endforeach; ?>
    </p>
    <div class="plant-card-meta">
      <?php if ($due): ?>
        <span class="care-hint care-hint-<?= e($due['state']) ?>"><?= icon(task_icon($soonest['task'])) ?> <?= e($due['text']) ?></span>
      <?php endif; ?>
      <?php if ($first['collection_name'] !== null): ?>
        <span class="plant-card-collection"><?= e($first['collection_name']) ?></span>
      <?php endif; ?>
    </div>
  </div>
</article>

<?php // The popup: every plant of the stack on its own row, with its own health and next care ?>
<dialog class="modal" id="<?= e($id) ?>" aria-labelledby="<?= e($id) ?>-title">
  <div class="modal-head">
    <h2 class="modal-title" id="<?= e($id) ?>-title"><?= e($first['common_name']) ?> · <?= e($count) ?> plants</h2>
    <form method="dialog">
      <button class="icon-btn" aria-label="Close"><?= icon('x') ?></button>
    </form>
  </div>
  <div class="modal-body">
    <p class="field-hint stack-hint">Each plant has its own health, story and reminders. Open one to update it.</p>
    <ul class="stack-rows">
      <?php foreach ($plants as $number => $plant): ?>
        <?php $plant_due = care_hint($plant); ?>
        <li class="stack-row">
          <span class="stack-row-number" aria-hidden="true"><?= e('#' . ($number + 1)) ?></span>
          <img class="thumb" src="<?= e(photo_url($plant['photo'])) ?>" alt="">
          <span class="stack-row-text">
            <a href="<?= e(url('plant.php?id=' . $plant['id'])) ?>"><strong><?= e($plant['nickname']) ?></strong></a>
            <span class="stack-row-care">
              <?php if ($plant_due): ?>
                <span class="care-hint care-hint-<?= e($plant_due['state']) ?>"><?= icon(task_icon($plant['task'])) ?> <?= e($plant_due['text']) ?></span>
              <?php else: ?>
                No reminder
              <?php endif; ?>
            </span>
          </span>
          <?php partial('status-badge', ['status' => $plant['health_status']]); ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</dialog>
