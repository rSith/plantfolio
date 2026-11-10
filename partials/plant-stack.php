<?php
// Plant stack card: two or more plants of the same species in the same collection, shown as one card in My Garden
// (prototype on feature/plant-stacks, CORE-14). The card is kept simple (Ranshitha's choice): photo, ×N, name and
// collection. Each plant's own health and next care are in the popup it opens, with a link to each plant's profile.
// Expects $plants — two or more plants rows (see $mock_plants), in the order My Garden sorted them.
// Expects $id — a unique id for the popup, because a page can hold several stacks.

$first = $plants[0];
$count = count($plants);
?>
<article class="card plant-card plant-stack">
  <div class="card-media">
    <img src="<?= e(photo_url($first['photo'])) ?>" alt="" loading="lazy">
    <?php // The one place the count shows. aria-hidden: a screen reader hears it once, in the button's name below. ?>
    <span class="badge plant-stack-count" aria-hidden="true"><?= e('×' . $count) ?></span>
  </div>
  <div class="card-body">
    <h3 class="plant-card-name">
      <?php // A button, not a link: it opens the popup on this page. Its ::after stretches over the whole card. ?>
      <button type="button" class="card-link plant-stack-open" data-dialog="<?= e($id) ?>" aria-haspopup="dialog">
        <?= e($first['common_name']) ?><span class="visually-hidden">, <?= e($count) ?> plants</span>
      </button>
    </h3>
    <p class="scientific-name"><?= e($first['scientific_name']) ?></p>
    <div class="plant-card-meta">
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
    <p class="field-hint stack-hint">Each plant has its own health, story and reminders. Tap a plant for its actions.</p>
    <?php // Each row is a <details>: a tap opens the plant's actions under the row, pushing the rows below down.
          // A dropdown would be cut off at the bottom of the popup; a row that grows never is. The shared name
          // makes it an accordion: opening one row closes the others. No JavaScript needed. ?>
    <ul class="stack-rows">
      <?php foreach ($plants as $number => $plant): ?>
        <?php $plant_due = care_hint($plant); $plant_id = (int) $plant['id']; ?>
        <li>
          <details class="stack-row" name="<?= e($id) ?>-rows">
            <summary class="stack-row-summary">
              <span class="stack-row-number" aria-hidden="true"><?= e('#' . ($number + 1)) ?></span>
              <img class="thumb" src="<?= e(photo_url($plant['photo'])) ?>" alt="">
              <span class="stack-row-text">
                <strong><?= e($plant['nickname']) ?></strong>
                <span class="stack-row-care">
                  <?php if ($plant_due): ?>
                    <span class="care-hint care-hint-<?= e($plant_due['state']) ?>"><?= icon(task_icon($plant['task'])) ?> <?= e($plant_due['text']) ?></span>
                  <?php else: ?>
                    No reminder
                  <?php endif; ?>
                </span>
              </span>
              <?php partial('status-badge', ['status' => $plant['health_status']]); ?>
              <span class="stack-row-more" aria-hidden="true"><?= icon('ellipsis') ?></span>
            </summary>
            <?php // The same actions as a single plant's ⋯ menu (partials/plant-menu.php), plus Open, all for this one plant ?>
            <div class="stack-row-actions">
              <a class="btn btn-primary btn-sm" href="<?= e(url('plant.php?id=' . $plant_id)) ?>"><?= icon('arrow-right') ?> Open</a>
              <a class="btn btn-ghost btn-sm" href="<?= e(url('plant-form.php?id=' . $plant_id)) ?>"><?= icon('pencil') ?> Edit</a>
              <a class="btn btn-ghost btn-sm" href="<?= e(url('plant-form.php?id=' . $plant_id . '#collection')) ?>"><?= icon('folder-input') ?> Move</a>
              <a class="btn btn-ghost btn-sm" href="<?= e(url('listing-form.php?plant=' . $plant_id)) ?>"><?= icon('repeat') ?> List for exchange</a>
              <?php // Stage F: does nothing yet, like Delete on a single card. Week 5 confirms in a dialog that names the plant. ?>
              <button type="button" class="btn btn-ghost btn-sm stack-row-delete"><?= icon('trash-2') ?> Delete</button>
            </div>
          </details>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php // The one action for the whole stack: one more of the same kind, with species and collection filled in ?>
  <div class="modal-foot">
    <a class="btn btn-secondary" href="<?= e(url('plant-form.php?like=' . $first['id'])) ?>">
      <?= icon('plus') ?> Add another <?= e(mb_strtolower($first['common_name'])) ?>
    </a>
  </div>
</dialog>
