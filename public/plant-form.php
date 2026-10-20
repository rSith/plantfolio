<?php
// Add or Edit Plant (P06, wireframe p13): a new plant in under a minute, photo first (CORE-16, UI half).
// plant-form.php adds a plant; plant-form.php?id=42 edits Monty with the same form, filled in (p13, UX notes).
// Stage F preview: plant-form.php?ai shows the form after a photo was analysed (p13 as drawn, ML-13 UI half),
// ?ai=unsure the same when the model is not sure (state S3).
// Members only. A full page, not a modal: the form is long, and its own address lets a link open it directly.
// Stage F draws the form, and Save only moves on to the next page without saving. Stage B checks the form,
// saves the photo (CORE-17) and the plants, care_reminders and first health_logs rows, then opens the plant's profile.

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mock-data.php';

// Stage F stand-in for require_login(): a visitor (?guest) is sent to Log in, exactly as stage B will do.
if (!$current_user) {
    header('Location: ' . url('login.php'));
    exit;
}

// Stage B, on POST: check the CSRF token; validate every field again on the server (the browser's checks can be
// switched off); check the photo with finfo (JPG/PNG, 5 MB) and resize it to 1600 px; save; redirect to plant.php?id=.

// ── Add or edit ─────────────────────────────────────────
// With ?id= the form edits that plant. Only its owner may: anyone else gets "not found", like on the plant's
// profile, so the form never reveals a private plant. Stage B checks ownership again when saving.

$plant    = null;
$plant_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($plant_id !== null) {
    foreach ($mock_plants as $row) {
        if ($row['id'] === $plant_id && $row['user_id'] === $current_user['id']) {
            $plant = $row;
            break;
        }
    }
    if (!$plant) {
        http_response_code(404);
        $page_title = 'Plant not found';
        include __DIR__ . '/../includes/header.php';
        ?>
        <div class="container page">
          <section class="panel">
            <?php partial('empty-state', [
                'heading' => 'h1',   // the whole page: its one main heading
                'title'   => 'Plant not found',
                'text'    => 'This plant does not exist, or it is not yours to edit.',
                'actions' => [['label' => 'Back to My Garden', 'href' => 'garden.php', 'icon' => 'leaf']],
            ]); ?>
          </section>
        </div>
        <?php
        include __DIR__ . '/../includes/footer.php';
        exit;
    }
}
$is_edit = $plant !== null;

// Stage F: Save moves on to the page stage B will open, without saving anything, so a demo can continue:
// a new plant goes back to My Garden (it has no profile yet), an edited plant to its profile. It runs after the
// ownership check above, so sending the form for someone else's plant still answers "not found".
// Stage B replaces this with the real save, then redirects to plant.php?id= of the saved plant.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Location: ' . ($is_edit ? url('plant.php?id=' . $plant['id']) : url('garden.php')));
    exit;
}

// ── The values the form starts with ─────────────────────
// Every field reads from $values, so the add form (defaults) and the edit form (the plant's row)
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

if ($is_edit) {
    $values = [
        'nickname'      => $plant['nickname'],
        'species'       => $plant['scientific_name'],
        'collection_id' => $plant['collection_id'],
        'acquired_on'   => $plant['acquired_on'],
        'health_status' => $plant['health_status'],
        'is_public'     => $plant['is_public'],
        'photo'         => $plant['photo'],
    ];

    // The plant's own reminders. A task it has no reminder for starts switched off, with the species' interval.
    $species = $mock_species[$plant['species_id']] ?? null;
    foreach (array_keys($reminder_values) as $task) {
        $reminder_values[$task]['enabled'] = false;
        if ($species) {
            $reminder_values[$task]['interval_days'] = $species[$task . '_interval_days'];
        }
    }
    foreach ($mock_plant_reminders as $reminder) {
        if ($reminder['plant_id'] === $plant['id']) {
            $reminder_values[$reminder['task']] = [
                'enabled'       => true,
                'interval_days' => $reminder['interval_days'],
                'start'         => $reminder['next_due'],   // "starting" = the next time it is due
            ];
        }
    }
    $reminder_source = 'The current schedule for ' . $plant['nickname'] . '. Change it anytime.';
}

// ── The AI suggestion (add mode only) ───────────────────
// Stage F: ?ai pretends a photo was uploaded and analysed, using the mock Flask answer. Stage M: the upload is
// sent to Flask (includes/ml-client.php), and if Flask is offline this whole block is skipped: the form simply
// stays manual, so adding a plant never depends on the ML service.
// ?pick=1 or 2 applies an alternative, ?pick=none clears the species. They are links, so they work without
// JavaScript; week 7 can apply a suggestion without reloading. Nothing is saved until Save plant (p13, UX notes).

$ai = null;
if (!$is_edit && isset($_GET['ai'])) {
    $ai_mode = get_text('ai') === 'unsure' ? 'unsure' : 'sure';
    $ai      = $ai_mode === 'unsure' ? $mock_species_result_unsure : $mock_species_result;

    $top        = $ai['predictions'][0];
    $top_level  = confidence_level($top['confidence']);
    $is_unsure  = $top_level['key'] === 'low';   // below 60%: never shown as an answer (S3)

    // Which suggestion fills the Species field: the top one if the model is sure, or the one the member picked
    // Whitelist: "none" or the number of a suggestion. (int) 'abc' would be 0, so the text itself is compared.
    $allowed = array_merge(['none'], array_map('strval', array_keys($ai['predictions'])));
    $pick    = get_text('pick');   // '' when it is missing, a list (pick[]=1) or not real text: the default below
    if (!in_array($pick, $allowed, true)) {
        $pick = $is_unsure ? 'none' : '0';
    }
    $applied = $pick === 'none' ? null : (int) $pick;

    // Species we know get their common name and their care guide; the model may also name species we have not
    $species_by_name = array_column($mock_species, null, 'scientific_name');

    $values['photo'] = $ai['image_path'];
    if ($applied !== null) {
        $chosen            = $ai['predictions'][$applied]['label'];
        $values['species'] = $chosen;

        if (isset($species_by_name[$chosen])) {
            $guide = $species_by_name[$chosen];
            $reminder_values['water']['interval_days']     = $guide['water_interval_days'];
            $reminder_values['fertilise']['interval_days'] = $guide['fertilise_interval_days'];
            $reminder_values['fertilise']['start']         = date('Y-m-d', strtotime('+' . $guide['fertilise_interval_days'] . ' days'));
            $reminder_source = 'Suggested from the ' . $guide['common_name'] . ' care guide. Change them anytime.';
        }
    }

    // Links that keep the preview: plant-form.php?ai&pick=1
    $ai_url = function ($pick) use ($ai_mode) {
        return url('plant-form.php?ai' . ($ai_mode === 'unsure' ? '=unsure' : '') . '&pick=' . $pick);
    };
}

$plant_url   = $is_edit ? url('plant.php?id=' . $plant['id']) : null;
$form_action = url('plant-form.php' . ($is_edit ? '?id=' . $plant['id'] : ''));

$health_options = ['thriving', 'stable', 'needs_attention', 'sick'];

$page_title      = $is_edit ? 'Edit ' . $plant['nickname'] : 'Add a plant';
$page_css        = 'plant-form.css';
$hide_bottom_bar = true;   // the sticky save bar takes the bottom of a phone screen instead (p23)

include __DIR__ . '/../includes/header.php';
?>
<div class="container page">
  <?php if ($is_edit): ?>
    <a class="back-link" href="<?= e($plant_url) ?>"><?= icon('arrow-left') ?> Back to <?= e($plant['nickname']) ?></a>
    <h1 class="form-title"><?= e($page_title) ?></h1>
    <p class="form-intro">Change the details, photo, collection or care schedule.</p>
  <?php else: ?>
    <a class="back-link" href="<?= e(url('garden.php')) ?>"><?= icon('arrow-left') ?> Back to My Garden</a>
    <h1 class="form-title"><?= e($page_title) ?></h1>
    <p class="form-intro">Start with a photo. We'll suggest the species and a care schedule.</p>
  <?php endif; ?>

  <?php // multipart/form-data: the only form encoding that can carry a file ?>
  <form class="plant-form" method="post" action="<?= e($form_action) ?>" enctype="multipart/form-data">
    <div class="plant-form-grid">
      <div class="plant-form-side">
        <?php // Photo upload (p13, element 1). The <label> is the drop area: a click anywhere on it opens the file
              // picker, because clicking a label activates its input. capture="environment" offers the rear camera. ?>
        <section class="form-card" id="photo" aria-labelledby="photo-title">
          <h2 class="visually-hidden" id="photo-title">Photo</h2>
          <?php if ($values['photo']): ?>
            <?php // A photo is already there: show it, with Change photo as another label of the same file input ?>
            <div class="photo-current">
              <img src="<?= e(photo_url($values['photo'])) ?>" alt="Current photo of <?= e($values['nickname']) ?>">
              <label class="btn btn-secondary btn-sm photo-change" for="plant-photo"><?= icon('camera') ?> Change photo</label>
            </div>
          <?php else: ?>
          <label class="dropzone" for="plant-photo">
            <span class="dropzone-icon"><?= icon('image-up') ?></span>
            <span class="dropzone-title">Add a photo of your plant</span>
            <span class="dropzone-text">Drag it here, browse, or use your phone camera</span>
            <span class="btn btn-secondary btn-sm"><?= icon('camera') ?> Choose photo</span>
          </label>
          <?php endif; ?>
          <input class="visually-hidden" type="file" id="plant-photo" name="photo"
                 accept="image/jpeg,image/png" capture="environment" aria-describedby="photo-hint">
          <?php if ($ai): ?>
            <p class="field-hint photo-hint" id="photo-hint"><?= icon('image') ?> <?= e($ai['upload_name']) ?> · <?= e(number_format($ai['upload_size'] / 1048576, 1)) ?> MB · uploaded</p>
          <?php else: ?>
            <p class="field-hint photo-hint" id="photo-hint"><?= icon('info') ?> JPG or PNG, up to 5 MB.</p>
          <?php endif; ?>
        </section>

        <?php if ($ai): ?>
          <?php // AI suggestion (p13, element 2): the top species with its confidence, two alternatives with Use,
                // and the manual way out (element 3). The member decides; the AI only suggests. ?>
          <section class="form-card ai-card" aria-labelledby="ai-title">
            <h2 class="ai-title" id="ai-title"><?= icon('sparkles') ?> Suggested species</h2>

            <?php if ($is_unsure): ?>
              <?php // S3: below 60% the result is not presented as an answer, only as possibilities ?>
              <p class="alert alert-warning ai-unsure"><?= icon('info') ?> <span><strong>Not sure.</strong> The photo did not match any species clearly. Choose the species yourself, or try a sharper photo in daylight.</span></p>
            <?php endif; ?>

            <ul class="ai-list">
              <?php foreach ($ai['predictions'] as $number => $prediction): ?>
                <?php
                $known     = $species_by_name[$prediction['label']] ?? null;
                $is_top    = $number === 0 && !$is_unsure;
                $is_chosen = $number === $applied;
                $percent   = round($prediction['confidence'] * 100);
                ?>
                <li class="ai-option<?= $is_top ? ' ai-option-top' : '' ?>">
                  <div class="ai-option-head">
                    <span>
                      <span class="scientific-name ai-option-name"><?= e($prediction['label']) ?></span>
                      <?php if ($known): ?><span class="text-small"><?= e($known['common_name']) ?></span><?php endif; ?>
                    </span>
                    <?php if ($is_chosen): ?>
                      <span class="btn btn-primary btn-sm ai-applied"><?= icon('check') ?> Applied</span>
                    <?php else: ?>
                      <a class="btn btn-ghost btn-sm" href="<?= e($ai_url($number)) ?>">Use<span class="visually-hidden"> <?= e($prediction['label']) ?></span></a>
                    <?php endif; ?>
                  </div>
                  <?php if ($is_top): ?>
                    <?php partial('confidence-meter', ['prediction' => $prediction]); ?>
                  <?php else: ?>
                    <?php // Alternatives only show their number: the confidence word belongs to the top result ?>
                    <div class="confidence-row ai-option-meter">
                      <div class="meter" aria-hidden="true"><span class="meter-fill" style="width: <?= e($percent) ?>%"></span></div>
                      <span class="confidence-value"><?= e($percent) ?>%</span>
                    </div>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>

            <a class="ai-manual" href="<?= e($ai_url('none')) ?>#species">None of these? Choose the species manually</a>
          </section>
        <?php elseif (!$is_edit): ?>
          <?php // Until a photo is analysed, the suggestion card explains what will happen (p13, elements 2–3) ?>
          <section class="form-card ai-card ai-card-waiting" aria-labelledby="ai-title">
            <h2 class="ai-title" id="ai-title"><?= icon('sparkles') ?> Suggested species</h2>
            <p class="text-small">Add a photo and PlantFolio suggests the species, with how sure it is. You can always type the species yourself.</p>
          </section>
        <?php endif; ?>
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
            <div class="species-label">
              <label class="field-label" for="species">Species <span class="field-optional">(optional)</span></label>
              <?php if ($ai && $applied !== null): ?>
                <?php // Filled-by-AI tag (p13, element 4): says which field the AI filled; Clear empties it for typing ?>
                <span class="badge badge-tag ai-tag"><?= icon('sparkles') ?> Filled by AI</span>
              <?php endif; ?>
            </div>
            <div class="species-box">
              <input class="input species-input" type="text" id="species" name="species" value="<?= e($values['species']) ?>"
                     list="species-list" placeholder="Start typing, e.g. Monstera" maxlength="120" autocomplete="off">
              <?php if ($ai && $applied !== null): ?>
                <?php // The × inside the field (p13, element 4) clears the AI's choice so the member can type their own ?>
                <a class="ai-clear" href="<?= e($ai_url('none')) ?>#species" aria-label="Clear the species the AI filled in"><?= icon('x') ?></a>
              <?php endif; ?>
            </div>
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

        <?php if (!$is_edit): ?>
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
        <?php else: ?>
          <?php // After the first day, health changes belong in the story, with a date: that is what Log update is for ?>
          <p class="alert form-alert"><?= icon('info') ?> <span>To change the health status, use <a href="<?= e($plant_url) ?>">Log update</a> on the plant's page, so the change is dated in its story.</span></p>
        <?php endif; ?>

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
      <?php if ($is_edit): ?>
        <?php // Stage F: does nothing yet. Week 5 opens the confirmation that names the plant (S6); stage B deletes (CORE-20). ?>
        <?php // On a phone only the bin is shown, so the button's full name lives in aria-label ?>
        <button type="button" class="btn btn-ghost save-bar-delete" aria-label="Delete plant"><?= icon('trash-2') ?> <span class="save-bar-delete-label">Delete plant</span></button>
      <?php else: ?>
        <p class="save-bar-note"><?= icon('info') ?> You can change everything later.</p>
      <?php endif; ?>
      <div class="save-bar-actions">
        <a class="btn btn-ghost" href="<?= e($is_edit ? $plant_url : url('garden.php')) ?>">Cancel</a>
        <button class="btn btn-primary btn-lg" type="submit"><?= $is_edit ? 'Save changes' : 'Save plant' ?></button>
      </div>
    </div>
  </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
