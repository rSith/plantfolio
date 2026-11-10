<?php
// Plant stack card: two or more plants of the same species in the same collection, shown as one card in My Garden
// (prototype on feature/plant-stacks, CORE-14). The card is kept simple: photo, ×N, name and collection.
// It opens a popup that lists every plant; tapping a plant's row shows its actions (Open, Move, List for exchange,
// Delete), the same four as a single card's ⋯ menu. "Choose plants" at the bottom adds more of this species.
// Expects $plants — two or more plants rows (see $mock_plants), in the order My Garden sorted them.
// Expects $id — a unique id for the popups, because a page can hold several stacks.
// Expects $free_plants — the member's plants of this species that are in no collection yet (for Choose plants).

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

<?php // The popup: one simple row per plant. scroll-quiet: a long stack scrolls with no visible scrollbar. ?>
<dialog class="modal" id="<?= e($id) ?>" aria-labelledby="<?= e($id) ?>-title">
  <div class="modal-head">
    <h2 class="modal-title" id="<?= e($id) ?>-title"><?= e($first['common_name']) ?> · <?= e($count) ?> plants</h2>
    <form method="dialog">
      <button class="icon-btn" aria-label="Close"><?= icon('x') ?></button>
    </form>
  </div>
  <div class="modal-body scroll-quiet">
    <?php // Each row is a <details>: a tap opens the plant's actions under the row, pushing the rows below down, so
          // nothing is ever cut off at the popup's edge. The shared name makes it an accordion: one open at a time. ?>
    <ul class="stack-rows">
      <?php foreach ($plants as $number => $plant): ?>
        <?php $plant_id = (int) $plant['id']; ?>
        <li>
          <details class="stack-row" name="<?= e($id) ?>-rows">
            <summary class="stack-row-summary">
              <?php // 01. 02. 03.: sprintf('%02d') pads the number to two digits ?>
              <span class="stack-row-number" aria-hidden="true"><?= e(sprintf('%02d.', $number + 1)) ?></span>
              <img class="thumb" src="<?= e(photo_url($plant['photo'])) ?>" alt="">
              <strong class="stack-row-name"><?= e($plant['nickname']) ?></strong>
              <?php // The chevron points down while closed and turns up (^) when the row is open (garden.css) ?>
              <span class="stack-row-chevron" aria-hidden="true"><?= icon('chevron-down') ?></span>
            </summary>
            <?php // The same four actions as a single plant's ⋯ menu (partials/plant-menu.php), for this one plant ?>
            <div class="stack-row-actions">
              <a class="btn btn-ghost btn-sm collection-action" href="<?= e(url('plant.php?id=' . $plant_id)) ?>">
                <?= icon('arrow-right') ?> <span class="hover-bold" data-label="Open">Open</span>
              </a>
              <a class="btn btn-ghost btn-sm collection-action" href="<?= e(url('plant-form.php?id=' . $plant_id . '#collection')) ?>">
                <?= icon('folder-input') ?> <span class="hover-bold" data-label="Move">Move</span>
              </a>
              <a class="btn btn-ghost btn-sm collection-action" href="<?= e(url('listing-form.php?plant=' . $plant_id)) ?>">
                <?= icon('repeat') ?> <span class="hover-bold" data-label="List for exchange">List for exchange</span>
              </a>
              <?php // Opens the confirmation that names the plant (garden.php), on top of this popup. Red: it deletes. ?>
              <button type="button" class="btn btn-ghost btn-sm collection-action collection-action-danger" data-dialog="delete-plant-<?= e($plant_id) ?>" aria-haspopup="dialog">
                <?= icon('trash-2') ?> <span class="hover-bold" data-label="Delete">Delete</span>
              </button>
            </div>
          </details>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php // The one action for the whole stack, styled like Choose plants in the collection box: it opens a second popup
        // with this species' plants that are in no collection yet. Ticked ones join this collection, so the stack. ?>
  <?php // Only a stack inside a collection: plants in no collection have no collection to join ?>
  <?php if ($first['collection_id'] !== null): ?>
    <div class="modal-foot">
      <button type="button" class="btn btn-ghost btn-sm collection-action" data-dialog="<?= e($id) ?>-choose" aria-haspopup="dialog">
        <?= icon('folder-input') ?> <span class="hover-bold" data-label="Choose plants">Choose plants</span>
      </button>
    </div>
  <?php endif; ?>
</dialog>

<?php // Choose plants for this stack: the same picker as the collection's, limited to this species ?>
<?php if ($first['collection_id'] !== null): ?>
<dialog class="modal modal-wide" id="<?= e($id) ?>-choose" aria-labelledby="<?= e($id) ?>-choose-title">
  <form class="modal-form" method="post" action="<?= e(url('garden.php' . ($first['collection_id'] !== null ? '?collection=' . $first['collection_id'] : ''))) ?>">
    <?php // Stage B: a hidden CSRF token goes here, in every POST form ?>
    <input type="hidden" name="form" value="add_plants">
    <input type="hidden" name="collection" value="<?= e($first['collection_id']) ?>">

    <div class="modal-head">
      <h2 class="modal-title" id="<?= e($id) ?>-choose-title">Choose plants · <?= e($first['common_name']) ?></h2>
      <button class="icon-btn" formmethod="dialog" formnovalidate aria-label="Close"><?= icon('x') ?></button>
    </div>

    <div class="modal-body scroll-quiet">
      <fieldset class="form-fieldset">
        <legend class="visually-hidden">Plants to add</legend>
        <?php if (!$free_plants): ?>
          <p class="collection-none-free"><?= icon('info') ?> Every <?= e(mb_strtolower($first['common_name'])) ?> you have is already in a collection. Add a new one with Add plant.</p>
        <?php else: ?>
          <?php partial('plant-picker', ['plants' => $free_plants, 'checked' => [], 'id' => $id . '-picker']); ?>
        <?php endif; ?>
      </fieldset>
    </div>

    <div class="modal-foot">
      <button class="btn btn-secondary" formmethod="dialog" formnovalidate>Cancel</button>
      <button class="btn btn-primary" type="submit"<?= $free_plants ? '' : ' disabled' ?>><?= icon('folder-input') ?> Add to stack</button>
    </div>
  </form>
</dialog>
<?php endif; ?>
