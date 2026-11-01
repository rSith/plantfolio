<?php
// The ⋯ menu of one plant in My Garden: Edit, Move to collection, List for exchange, Delete (CORE-14, wireframe p11, UX notes).
// Expects $plant — a plants row with id and nickname. Used on the plant card and in the list view's table rows.
// A <details> menu opens without JavaScript. Menus share a name, so opening one closes any other.

$plant_id = (int) $plant['id'];
?>
<details class="menu plant-menu" name="plant-menu">
  <summary class="icon-btn plant-menu-btn" aria-label="Actions for <?= e($plant['nickname']) ?>"><?= icon('ellipsis') ?></summary>
  <div class="menu-panel menu-panel-right">
    <a class="menu-item" href="<?= e(url('plant-form.php?id=' . $plant_id)) ?>"><?= icon('pencil') ?> Edit</a>
    <?php // The collection is a field of the edit form; #collection jumps straight to it ?>
    <a class="menu-item" href="<?= e(url('plant-form.php?id=' . $plant_id . '#collection')) ?>"><?= icon('folder-input') ?> Move to collection</a>
    <a class="menu-item" href="<?= e(url('listing-form.php?plant=' . $plant_id)) ?>"><?= icon('repeat') ?> List for exchange</a>
    <?php // Stage F: does nothing yet. Week 5 opens the confirmation that names the plant (S6); stage B deletes (CORE-20). ?>
    <button type="button" class="menu-item menu-item-danger"><?= icon('trash-2') ?> Delete</button>
  </div>
</details>
