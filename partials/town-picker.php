<?php
// Where someone is, entered on a form: their nearest town from the towns list, then how far from that town's centre
// (a slider, 0 to 30 km; 0 = in the town). Sign up and Settings (the member's own place, users.town_id and the
// users.km_from_town) and Create Listing (the pick-up place, exchange_listings.town_id and km_from_town).
// Never an address: a town and a rough distance are all the site keeps (privacy rule).
// Expects $town_names (A–Z), $town (the chosen town, '' for none) and $km (0–30, or null when not given), and
// optionally: $label ('Nearest town'), $required (false: the list starts on "Choose a town"), $hint, $error,
// $km_label ('How far from the town'). Field names: location (the town, as the mock rows and towns.name AS location
// call it) and km_from_town. The slider is switched off until a town is chosen (app.js, data-enables).

$label    = $label ?? 'Nearest town';
$km_label = $km_label ?? 'How far from the town';
$required = !empty($required);
$km       = $km ?? 0;
$km_words = $km === 0 ? 'In the town' : 'About ' . $km . ' km away';
$describe = trim((!empty($hint) ? 'location-hint' : '') . (!empty($error) ? ' location-error' : ''));
?>
<div class="field town-picker">
  <label class="field-label" for="location"><?= e($label) ?><?php if (!$required): ?> <span class="field-optional">(optional)</span><?php endif; ?></label>
  <div class="input-icon">
    <?= icon('map-pin') ?>
    <select class="input" id="location" name="location" data-enables="km-from-town"<?= $required ? ' required' : '' ?>
            <?= $describe !== '' ? 'aria-describedby="' . e($describe) . '"' : '' ?><?= !empty($error) ? ' aria-invalid="true"' : '' ?>>
      <option value="">Choose a town</option>
      <?php foreach ($town_names as $name): ?>
        <option value="<?= e($name) ?>"<?= $name === $town ? ' selected' : '' ?>><?= e($name) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php if (!empty($hint)): ?>
    <span class="field-hint" id="location-hint"><?= e($hint) ?></span>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <span class="field-error" id="location-error"><?= e($error) ?></span>
  <?php endif; ?>

  <div class="filter-range town-picker-km">
    <div class="filter-range-head">
      <label class="filter-field-label" for="km-from-town"><?= e($km_label) ?></label>
      <output class="filter-range-value" for="km-from-town" data-range-output><?= e($km_words) ?></output>
    </div>
    <input class="filter-range-input" type="range" id="km-from-town" name="km_from_town" min="0" max="30" step="1"
           value="<?= e($km) ?>" aria-valuetext="<?= e($km_words) ?>" data-range-words="from-town"<?= $town === '' ? ' disabled' : '' ?>>
    <div class="filter-range-scale" aria-hidden="true"><span>In the town</span><span>15 km</span><span>30 km</span></div>
  </div>
</div>
