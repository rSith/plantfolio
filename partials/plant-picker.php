<?php
// Plant picker: the member's plants as a checkbox list, for putting plants into a collection (CORE-13).
// Used twice on My Garden: in the New collection popup and in the Add plants popup.
// Expects $plants — the member's plants rows (see $mock_plants), already in the order to show.
// Expects $checked — the plant ids to tick again when the form comes back with an error.
// Expects $id — a prefix for this list's ids, because both popups are on the same page and an id must be unique.
// A plant can be in one collection only (plants.collection_id), so a plant that has one is disabled and says where it is.
?>
<p class="field-hint" id="<?= e($id) ?>-hint">A plant can be in one collection only. Plants that already have one can't be picked.</p>
<ul class="collection-pick-list">
  <?php foreach ($plants as $plant): ?>
    <?php $taken = $plant['collection_id'] !== null; ?>
    <li>
      <label class="collection-pick<?= $taken ? ' is-taken' : '' ?>">
        <input type="checkbox" name="plants[]" value="<?= e($plant['id']) ?>"
               <?= $taken ? 'disabled' : '' ?><?= in_array($plant['id'], $checked, true) ? ' checked' : '' ?>
               aria-describedby="<?= e($id) ?>-hint">
        <img class="thumb" src="<?= e(photo_url($plant['photo'])) ?>" alt="">
        <span class="collection-pick-text">
          <strong><?= e($plant['nickname']) ?></strong>
          <span class="collection-pick-meta"><?= e($plant['common_name']) ?></span>
        </span>
        <span class="collection-pick-where">
          <?php if ($taken): ?>
            In <?= e($plant['collection_name']) ?>
          <?php else: ?>
            No collection
          <?php endif; ?>
        </span>
      </label>
    </li>
  <?php endforeach; ?>
</ul>
