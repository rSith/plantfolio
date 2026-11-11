<?php
// The Distance group of the Community filter sidebar, on all three tabs: "Near" (a town from the towns list) and
// "How far" (a slider from 5 to 100 km in steps of 5; 100, the far end, means any distance) (COM-03, COM-07).
// Expects $near (the chosen town, '' when none), $distance ('5' … '100'), $town_names (A–Z) and $can_be_empty
// (true for a visitor, who has no town of their own: the list then starts on "Choose a town" and the slider is
// switched off until a town is chosen; a disabled field is not sent with the form).
// The fields join the page's one GET form with form="community-form". The slider's words change while it moves
// (app.js, data-range-words="distance"); the results change when it is let go (community.js).

$distance_words = $distance === '100' ? 'Any distance' : 'Within ' . $distance . ' km';
?>
<details class="filter-section" data-section="distance" open>
  <summary class="filter-section-head"><?= icon('map-pin') ?> <span>Distance</span> <span class="filter-section-chevron"><?= icon('chevron-down') ?></span></summary>
  <div class="filter-group">
    <label class="filter-field-label" for="filter-near">Near</label>
    <select class="input" id="filter-near" name="near" form="community-form" data-enables="filter-distance">
      <?php if ($can_be_empty): ?>
        <option value="">Choose a town</option>
      <?php endif; ?>
      <?php foreach ($town_names as $town): ?>
        <option value="<?= e($town) ?>"<?= $town === $near ? ' selected' : '' ?>><?= e($town) ?></option>
      <?php endforeach; ?>
    </select>

    <div class="filter-range">
      <div class="filter-range-head">
        <label class="filter-field-label" for="filter-distance">How far</label>
        <output class="filter-range-value" for="filter-distance" data-range-output><?= e($distance_words) ?></output>
      </div>
      <input class="filter-range-input" type="range" id="filter-distance" name="distance" min="5" max="100" step="5"
             value="<?= e($distance) ?>" aria-valuetext="<?= e($distance_words) ?>" data-range-words="distance"
             form="community-form"<?= $near === '' ? ' disabled' : '' ?>>
      <div class="filter-range-scale" aria-hidden="true"><span>5 km</span><span>50 km</span><span>Any</span></div>
    </div>
    <?php if ($near === ''): ?>
      <p class="filter-note">Choose a town to see how far away everything is.</p>
    <?php endif; ?>
  </div>
</details>
