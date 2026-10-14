<?php
// Add or Edit Plant (P06, wireframe p13): a new plant in under a minute, photo first (CORE-16, UI half).
// Members only. A full page, not a modal: the form is long, and its own address lets a link open it directly.
// Stage F only draws the form; stage B checks it, saves the photo (CORE-17) and the plants, care_reminders and
// first health_logs rows, then opens the new plant's profile.

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mock-data.php';

// Stage F stand-in for require_login(): a visitor (?guest) is sent to Log in, exactly as stage B will do.
if (!$current_user) {
    header('Location: ' . url('login.php'));
    exit;
}

// Stage B, on POST: check the CSRF token; validate every field again on the server (the browser's checks can be
// switched off); check the photo with finfo (JPG/PNG, 5 MB) and resize it to 1600 px; save; redirect to plant.php?id=.

// ── The values the form starts with ─────────────────────
// Every field reads from $values, so the add form (defaults) and, later, the edit form (the plant's row)
// share one piece of HTML.

$values = [
    'nickname'      => '',
    'species'       => '',
    'collection_id' => $mock_collections[0]['id'] ?? null,
    'acquired_on'   => date('Y-m-d'),
    'health_status' => 'thriving',
    'is_public'     => 1,
    'photo'         => null,
];

// Care reminders (p13, element 6): on by default with the usual intervals; the species guide fills in its own
// once a species is chosen (the AI suggestion does it in week 7, JavaScript for a typed species in week 6).
$reminder_values = [
    'water'     => ['enabled' => true, 'interval_days' => 7,  'start' => date('Y-m-d')],
    'fertilise' => ['enabled' => true, 'interval_days' => 30, 'start' => date('Y-m-d', strtotime('+30 days'))],
];
$reminder_source = 'Pick a species to use its care guide. Change them anytime.';

$health_options = ['thriving', 'stable', 'needs_attention', 'sick'];

$page_title      = 'Add a plant';
$page_css        = 'plant-form.css';
$hide_bottom_bar = true;   // the sticky save bar takes the bottom of a phone screen instead (p23)

include __DIR__ . '/../includes/header.php';
?>
<div class="container page">
  <a class="back-link" href="<?= e(url('garden.php')) ?>"><?= icon('arrow-left') ?> Back to My Garden</a>
  <h1 class="form-title"><?= e($page_title) ?></h1>
  <p class="form-intro">Start with a photo. We'll suggest the species and a care schedule.</p>

  <?php // multipart/form-data: the only form encoding that can carry a file ?>
  <form class="plant-form" method="post" action="<?= e(url('plant-form.php')) ?>" enctype="multipart/form-data">
    <div class="plant-form-grid">
      <div class="plant-form-side">
        <?php // Photo upload (p13, element 1). The <label> is the drop area: a click anywhere on it opens the file
              // picker, because clicking a label activates its input. capture="environment" offers the rear camera. ?>
        <section class="form-card" id="photo" aria-labelledby="photo-title">
          <h2 class="visually-hidden" id="photo-title">Photo</h2>
          <label class="dropzone" for="plant-photo">
            <span class="dropzone-icon"><?= icon('image-up') ?></span>
            <span class="dropzone-title">Add a photo of your plant</span>
            <span class="dropzone-text">Drag it here, browse, or use your phone camera</span>
            <span class="btn btn-secondary btn-sm"><?= icon('camera') ?> Choose photo</span>
          </label>
          <input class="visually-hidden" type="file" id="plant-photo" name="photo"
                 accept="image/jpeg,image/png" capture="environment" aria-describedby="photo-hint">
          <p class="field-hint photo-hint" id="photo-hint"><?= icon('info') ?> JPG or PNG, up to 5 MB.</p>
        </section>

        <?php // Until a photo is analysed, the suggestion card explains what will happen (p13, elements 2–3) ?>
        <section class="form-card ai-card ai-card-waiting" aria-labelledby="ai-title">
          <h2 class="ai-title" id="ai-title"><?= icon('sparkles') ?> Suggested species</h2>
          <p class="text-small">Add a photo and PlantFolio suggests the species, with how sure it is. You can always type the species yourself.</p>
        </section>
      </div>

      <section class="form-card plant-form-main" aria-labelledby="details-title">
        <h2 class="form-card-title" id="details-title">Plant details</h2>

        <div class="field-row">
          <div class="field">
            <label class="field-label" for="nickname">Nickname</label>
            <input class="input" type="text" id="nickname" name="nickname" value="<?= e($values['nickname']) ?>"
                   placeholder="Monty" maxlength="60" required>
          </div>

          <?php // A text box with a list of known species (<datalist>): typing suggests matches, but any name is allowed.
                // Stage B looks the name up in plant_species and stores its species_id. ?>
          <div class="field">
            <label class="field-label" for="species">Species <span class="field-optional">(optional)</span></label>
            <input class="input species-input" type="text" id="species" name="species" value="<?= e($values['species']) ?>"
                   list="species-list" placeholder="Start typing, e.g. Monstera" maxlength="120" autocomplete="off">
            <datalist id="species-list">
              <?php foreach ($mock_species as $species): ?>
                <option value="<?= e($species['scientific_name']) ?>"><?= e($species['common_name']) ?></option>
              <?php endforeach; ?>
            </datalist>
          </div>
        </div>

        <div class="field-row">
          <div class="field">
            <label class="field-label" for="collection">Collection</label>
            <select class="input" id="collection" name="collection_id">
              <?php foreach ($mock_collections as $collection): ?>
                <option value="<?= e($collection['id']) ?>"<?= $collection['id'] === $values['collection_id'] ? ' selected' : '' ?>>
                  <?= e($collection['name']) ?><?= $collection['is_public'] ? '' : ' (private)' ?>
                </option>
              <?php endforeach; ?>
              <option value=""<?= $values['collection_id'] === null ? ' selected' : '' ?>>No collection</option>
            </select>
          </div>

          <div class="field">
            <label class="field-label" for="acquired-on">Date acquired</label>
            <input class="input" type="date" id="acquired-on" name="acquired_on" value="<?= e($values['acquired_on']) ?>"
                   max="<?= e(date('Y-m-d')) ?>">
          </div>
        </div>

        <?php // Current health (p13, element 5): four radio buttons drawn as one control; it becomes the first story entry ?>
        <fieldset class="field form-fieldset">
          <legend class="field-label">How is it doing right now?</legend>
          <div class="segmented health-segmented">
            <?php foreach ($health_options as $option): ?>
              <input type="radio" id="health-<?= e($option) ?>" name="health_status" value="<?= e($option) ?>"<?= $option === $values['health_status'] ? ' checked' : '' ?>>
              <label for="health-<?= e($option) ?>"><span class="health-dot health-dot-<?= e(str_replace('_', '-', $option)) ?>"></span><?= e(health_label($option)) ?></label>
            <?php endforeach; ?>
          </div>
        </fieldset>

        <?php // Care reminders (p13, element 6): a switch per task, how often, and from which date ?>
        <?php // The line above a section is on a <div>: a <legend> always sits on its fieldset's border, so a border
              // on the fieldset itself would run through the title ?>
        <div class="form-section" id="reminders">
        <fieldset class="form-fieldset">
          <legend class="form-section-title">Care reminders</legend>
          <p class="field-hint form-section-hint"><?= e($reminder_source) ?></p>

          <?php foreach ($reminder_values as $task => $reminder): ?>
            <div class="reminder-row">
              <label class="toggle">
                <input type="checkbox" name="<?= e($task) ?>_enabled" value="1"<?= $reminder['enabled'] ? ' checked' : '' ?>>
                <span class="reminder-task"><?= icon(task_icon($task)) ?> <?= e(task_label($task)) ?> every</span>
              </label>
              <span class="reminder-when">
                <label class="visually-hidden" for="<?= e($task) ?>-interval"><?= e(task_label($task)) ?> every how many days</label>
                <input class="input input-days" type="number" id="<?= e($task) ?>-interval" name="<?= e($task) ?>_interval"
                       value="<?= e($reminder['interval_days']) ?>" min="1" max="365" inputmode="numeric">
                <span>days, starting</span>
                <label class="visually-hidden" for="<?= e($task) ?>-start"><?= e(task_label($task)) ?> starting on</label>
                <input class="input input-date" type="date" id="<?= e($task) ?>-start" name="<?= e($task) ?>_start" value="<?= e($reminder['start']) ?>">
              </span>
            </div>
          <?php endforeach; ?>
        </fieldset>
        </div>

        <div class="form-section">
        <fieldset class="form-fieldset">
          <legend class="form-section-title">Who can see this plant?</legend>
          <div class="visibility-options">
            <label class="check">
              <input type="radio" name="is_public" value="1"<?= $values['is_public'] ? ' checked' : '' ?>>
              <span><strong>Public</strong> <span class="text-small">· shown in Explore</span></span>
            </label>
            <label class="check">
              <input type="radio" name="is_public" value="0"<?= $values['is_public'] ? '' : ' checked' ?>>
              <span><strong>Private</strong> <span class="text-small">· only you</span></span>
            </label>
          </div>
        </fieldset>
        </div>
      </section>
    </div>

    <?php // Sticky save bar (p13, element 7): stays on screen on a long form; Cancel leads back without saving ?>
    <div class="save-bar">
      <p class="save-bar-note"><?= icon('info') ?> You can change everything later.</p>
      <div class="save-bar-actions">
        <a class="btn btn-ghost" href="<?= e(url('garden.php')) ?>">Cancel</a>
        <button class="btn btn-primary btn-lg" type="submit">Save plant</button>
      </div>
    </div>
  </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
