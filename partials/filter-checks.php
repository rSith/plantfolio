<?php
// A list of tick boxes in the Community filter sidebar: an optional icon, the option's name and how many results it
// has, "Indoor plants … 5" (COM-03). Used for Species, Category, Light and Watering.
// Expects $name — the field name (sent as name[] so PHP collects every ticked box into one array) — and $items, each
// ['value' (or 'id'), 'name', 'count', 'checked', optional 'icon']. The fields join the page's one GET form through
// form="community-form", because the sidebar sits outside it.
?>
<?php foreach ($items as $item): ?>
  <label class="check filter-check<?= $item['count'] === 0 && !$item['checked'] ? ' filter-check-empty' : '' ?>">
    <input type="checkbox" name="<?= e($name) ?>[]" value="<?= e($item['value'] ?? $item['id']) ?>" form="community-form"<?= $item['checked'] ? ' checked' : '' ?>>
    <?php if (!empty($item['icon'])): ?>
      <span class="filter-check-icon"><?= icon($item['icon']) ?></span>
    <?php endif; ?>
    <span class="filter-check-name"><?= e($item['name']) ?></span>
    <span class="filter-check-count"><?= e($item['count']) ?></span>
  </label>
<?php endforeach; ?>
