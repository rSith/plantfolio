<?php
// Add or Edit Plant (P06, wireframe p13): a new plant in under a minute, photo first (CORE-16, UI half).
// plant-form.php adds a plant; plant-form.php?id=42 edits Monty with the same form, filled in (p13, UX notes).
// Stage F preview: plant-form.php?ai shows the form after a photo was analysed (p13 as drawn, ML-13 UI half),
// ?ai=unsure the same when the model is not sure (state S3). plant-form.php?prediction=5 opens a result of the
// Plant Identifier (P07), its "Add to my garden".
// Members only. A full page, not a modal: the form is long, and its own address lets a link open it directly.
// Stage F draws the form, and Save only moves on to the next page without saving. Stage B checks the form,
// saves the photo (CORE-17) and the plants, care_reminders and first health_logs rows, then opens the plant's profile.

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mock-data.php';
require_once __DIR__ . '/../includes/reminder-form.php';   // Add a reminder popup (edit only)

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
// Add a reminder popup (edit only, the Dashboard's popup for this plant): checked and kept by reminder_form_save()
// in includes/reminder-form.php; the main form's Save never sends these fields (the popup is outside it).
$reminder_form   = $is_edit ? reminder_defaults($plant['id']) : null;
$reminder_errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_edit && post_text('form') === 'reminder') {
    [$reminder_form, $reminder_errors] = reminder_form_save([$plant['id'] => $plant]);
    if (!$reminder_errors) {
        header('Location: ' . url('plant-form.php?id=' . $plant['id']) . '#reminders');
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_edit && post_text('form') === 'undo_reminder') {
    reminder_undo();
    header('Location: ' . url('plant-form.php?id=' . $plant['id']) . '#reminders');
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_edit) {
    header('Location: ' . url('plant.php?id=' . $plant['id']));
    exit;
}
$toast = $is_edit ? reminder_toast_take() : null;

// ── Save a new plant, or several (prototype, feature/plant-stacks) ──
// Adding is kept in the session (mock_add_plants()), so the new plants really show in My Garden. With "How many?"
// above 1, every plant gets its own row, health and reminders, and My Garden shows them as one stack because they
// share species and collection. The photo upload waits for stage B (CORE-17): the species' drawing stands in.
// Stage B: CSRF check, the same checks, then INSERTs in one transaction.
$add_errors = [];   // field name => message, printed under that field
$add_posted = null; // the sent values, to fill the form again after an error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nickname   = preg_replace('/\s+/u', ' ', trim(post_text('nickname')));
    $species_in = trim(post_text('species'));
    $quantity   = post_text('quantity');
    $coll_in    = post_text('collection_id');
    $health     = post_text('health_status');
    $is_public  = post_text('is_public') === '0' ? 0 : 1;
    $acquired   = post_text('acquired_on');

    if ($nickname === '') {
        $add_errors['nickname'] = 'Give your plant a nickname.';
    } elseif (mb_strlen($nickname) > 55) {   // 60 in the database, with room for " 20"
        $add_errors['nickname'] = 'Use 55 characters or fewer.';
    }
    if (!ctype_digit($quantity) || (int) $quantity < 1 || (int) $quantity > 20) {
        $add_errors['quantity'] = 'Use a number from 1 to 20.';
    }
    // The collection must be one of the member's own, or none (ownership check)
    $collection = null;
    foreach ($mock_collections as $row) {
        if ($coll_in !== '' && ctype_digit($coll_in) && $row['id'] === (int) $coll_in) {
            $collection = $row;
        }
    }
    if ($coll_in !== '' && !$collection) {
        $add_errors['collection_id'] = 'Choose one of your collections.';
    }
    if (!in_array($health, ['thriving', 'stable', 'needs_attention', 'sick'], true)) {
        $health = 'thriving';
    }
    $acquired_date = DateTime::createFromFormat('!Y-m-d', $acquired);
    if (!$acquired_date || $acquired_date->format('Y-m-d') !== $acquired || $acquired > date('Y-m-d')) {
        $acquired = date('Y-m-d');
    }

    if (!$add_errors) {
        // A species from our list gives species_id and both names; a typed name we do not know stays custom
        // (species_id NULL, custom_species), and such plants are never stacked.
        $species = null;
        foreach ($mock_species as $row) {
            if ($species_in !== '' && (strcasecmp($row['scientific_name'], $species_in) === 0 || strcasecmp($row['common_name'], $species_in) === 0)) {
                $species = $row;
            }
        }

        // The card shows the soonest reminder that is switched on
        $task = null;
        $next_due = null;
        $interval = null;
        foreach (['water', 'fertilise'] as $reminder_task) {
            if (post_text($reminder_task . '_enabled') === '1') {
                $start = post_text($reminder_task . '_start');
                $start = DateTime::createFromFormat('!Y-m-d', $start) ? $start : date('Y-m-d');
                if ($next_due === null || $start < $next_due) {
                    $task     = $reminder_task;
                    $next_due = $start;
                    $days     = post_text($reminder_task . '_interval');
                    $interval = ctype_digit($days) ? max(1, min(365, (int) $days)) : 7;
                }
            }
        }

        $rows = [];
        for ($number = 1; $number <= (int) $quantity; $number++) {
            $rows[] = [
                'user_id'         => $current_user['id'],
                'collection_id'   => $collection['id'] ?? null,
                'species_id'      => $species['id'] ?? null,
                // "Spike", "Spike 2", "Spike 3": every plant has its own nickname (plants.nickname is NOT NULL)
                'nickname'        => $number === 1 ? $nickname : $nickname . ' ' . $number,
                'photo'           => $species['photo'] ?? 'mock/sergeant.svg',
                'acquired_on'     => $acquired,
                'health_status'   => $health,
                'is_public'       => $is_public,
                'scientific_name' => $species['scientific_name'] ?? ($species_in !== '' ? $species_in : 'Unknown species'),
                'common_name'     => $species['common_name'] ?? ($species_in !== '' ? $species_in : 'Plant'),
                'collection_name' => $collection['name'] ?? null,
                'task'            => $task,
                'interval_days'   => $interval,
                'next_due'        => $next_due,
                'last_logged_at'  => date('Y-m-d H:i:s'),
            ];
        }
        mock_add_plants($rows);

        $count = count($rows);
        $_SESSION['collection_toast'] = $count === 1
            ? 'Plant added: ' . $nickname
            : $count . ' ' . mb_strtolower($rows[0]['common_name']) . ' plants added';
        // Open the collection they joined, so the member sees the new card or stack straight away
        header('Location: ' . url('garden.php' . ($collection ? '?collection=' . $collection['id'] : '')));
        exit;
    }
    $add_posted = [
        'nickname' => $nickname, 'species' => $species_in, 'quantity' => $quantity,
        'collection_id' => $collection['id'] ?? null, 'acquired_on' => $acquired,
        'health_status' => $health, 'is_public' => $is_public,
    ];
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
        // The form has switches for water and fertilise only; reminders for other tasks are set on the Dashboard.
        // A water reminder on chosen weekdays has no interval_days, so it shows the species' interval instead.
        if ($reminder['plant_id'] === $plant['id'] && isset($reminder_values[$reminder['task']])) {
            $reminder_values[$reminder['task']] = [
                'enabled'       => true,
                'interval_days' => $reminder['interval_days'] ?? $reminder_values[$reminder['task']]['interval_days'],
                'start'         => $reminder['next_due'],   // "starting" = the next time it is due
            ];
        }
    }
    $reminder_source = 'The current schedule for ' . $plant['nickname'] . '. Change it anytime.';
}

// ── "Add another" from a plant stack (prototype, feature/plant-stacks) ──
// plant-form.php?like=64 adds a new plant like plant 64: same species, collection and visibility, and its species'
// care intervals. The nickname, date, health and photo stay empty or default: they belong to the new plant.
// Only the member's own plant can be copied; any other id is ignored and the form stays empty.
$like_id = filter_input(INPUT_GET, 'like', FILTER_VALIDATE_INT);
if (!$is_edit && $like_id !== null) {
    foreach ($mock_plants as $row) {
        if ($row['id'] === $like_id && $row['user_id'] === $current_user['id']) {
            $values['species']       = $row['scientific_name'];
            $values['collection_id'] = $row['collection_id'];
            $values['is_public']     = $row['is_public'];
            $species = $mock_species[$row['species_id']] ?? null;
            if ($species) {
                foreach (array_keys($reminder_values) as $task) {
                    $reminder_values[$task]['interval_days'] = $species[$task . '_interval_days'];
                }
                $reminder_source = 'From the care guide for ' . $species['common_name'] . '. Change them anytime.';
            }
        }
    }
}

// ── The AI suggestion (add mode only) ───────────────────
// Stage F: ?ai pretends a photo was uploaded and analysed, using the mock Flask answer. Stage M: the upload is
// sent to Flask (includes/ml-client.php), and if Flask is offline this whole block is skipped: the form simply
// stays manual, so adding a plant never depends on the ML service.
// ?pick=1 or 2 applies an alternative, ?pick=none clears the species. They are links, so they work without
// JavaScript; week 7 can apply a suggestion without reloading. Nothing is saved until Save plant (p13, UX notes).

// From the Plant Identifier (P07): ?prediction=5 is "Add to my garden" (p14, element 6). The form opens with that
// result's photo, its top 3 and the reminders of its species, just like ?ai. Only the member's own results count.

$ai               = null;
$prediction_param = get_text('prediction');
$from_identify    = ctype_digit($prediction_param) ? ($mock_identify_results[(int) $prediction_param] ?? null) : null;
if ($from_identify && $from_identify['user_id'] !== $current_user['id']) {
    $from_identify = null;
}

if (!$is_edit && ($from_identify || isset($_GET['ai']))) {
    if ($from_identify) {
        $ai      = $from_identify;
        $ai_base = 'plant-form.php?prediction=' . $from_identify['id'];
    } else {
        $ai_mode = get_text('ai') === 'unsure' ? 'unsure' : 'sure';
        $ai      = $ai_mode === 'unsure' ? $mock_species_result_unsure : $mock_species_result;
        $ai_base = 'plant-form.php?ai' . ($ai_mode === 'unsure' ? '=unsure' : '');
    }

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

    // Links that keep the preview: plant-form.php?ai&pick=1, plant-form.php?prediction=5&pick=1
    $ai_url = fn ($pick) => url($ai_base . '&pick=' . $pick);
}

$plant_url   = $is_edit ? url('plant.php?id=' . $plant['id']) : null;

// The plant's other reminders (mist, rotate, prune, repot, re-check), added with the popup: listed under the two
// switches, which only cover watering and feeding
$other_reminders = $is_edit
    ? array_values(array_filter($mock_plant_reminders, fn ($row) => $row['plant_id'] === $plant['id'] && !isset($reminder_values[$row['task']])))
    : [];


$form_action = url('plant-form.php' . ($is_edit ? '?id=' . $plant['id'] : ''));

// After an error, the form shows what the member sent, not the defaults
$quantity_value = '1';
if ($add_posted) {
    $quantity_value = $add_posted['quantity'];
    unset($add_posted['quantity']);
    $values = array_merge($values, $add_posted);
}

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
                   placeholder="Monty" maxlength="55" required<?= isset($add_errors['nickname']) ? ' aria-invalid="true" aria-describedby="nickname-error"' : '' ?>>
            <?php if (isset($add_errors['nickname'])): ?>
              <p class="field-error" id="nickname-error"><?= e($add_errors['nickname']) ?></p>
            <?php endif; ?>
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

        <?php // Prototype (feature/plant-stacks): several plants of one kind in one go. Only when adding: editing is
              // always one plant. Stage B would insert this many plants rows, the nickname numbered "Name 2", "Name 3"…;
              // in My Garden they then show as one stack card. Stage F: nothing is saved yet. ?>
        <?php if (!$is_edit): ?>
          <div class="field field-quantity">
            <label class="field-label" for="quantity">How many?</label>
            <input class="input input-quantity" type="number" id="quantity" name="quantity" value="<?= e($quantity_value) ?>" min="1" max="20"
                   inputmode="numeric" aria-describedby="quantity-hint<?= isset($add_errors['quantity']) ? ' quantity-error' : '' ?>"<?= isset($add_errors['quantity']) ? ' aria-invalid="true"' : '' ?>>
            <?php if (isset($add_errors['quantity'])): ?>
              <p class="field-error" id="quantity-error"><?= e($add_errors['quantity']) ?></p>
            <?php endif; ?>
            <p class="field-hint" id="quantity-hint">Got several of the same plant? They share one card in My Garden, and each keeps its own health and reminders. More than one get numbers after the nickname: Spike, Spike 2, Spike 3.</p>
          </div>
        <?php endif; ?>

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
            <?php if (isset($add_errors['collection_id'])): ?>
              <p class="field-error"><?= e($add_errors['collection_id']) ?></p>
            <?php endif; ?>
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
          <?php // After the first day, health changes belong in the story, with a date: the Add to story popup on the
                // plant's page (&story=1 opens it at once) ?>
          <p class="alert form-alert"><?= icon('info') ?> <span>Use <a href="<?= e(url('plant.php?id=' . $plant['id'] . '&story=1')) ?>">Add to <?= e($plant['nickname']) ?>’s story</a> on the plant’s page to record updates. They will appear in its Story.</span></p>
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
                <span class="reminder-task"><?= task_name($task) ?> every</span>
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

          <?php if ($is_edit): ?>
            <?php foreach ($other_reminders as $reminder): ?>
              <p class="reminder-other"><?= task_name($reminder['task']) ?> · <?= e(schedule_words($reminder)) ?></p>
            <?php endforeach; ?>
            <?php // Opens the Dashboard's Add a reminder popup, for this plant (app.js) ?>
            <button type="button" class="btn btn-secondary btn-sm reminder-add" data-dialog="add-reminder" aria-haspopup="dialog"><?= icon('plus') ?> Add a reminder</button>
          <?php endif; ?>
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

  <?php if ($is_edit): ?>
    <?php // Outside the main form: a form may not sit inside another ?>
    <?php partial('reminder-dialog', [
        'action' => url('plant-form.php?id=' . $plant['id']),
        'values' => $reminder_form,
        'errors' => $reminder_errors,
        'plants' => null,
        'plant'  => $plant,
    ]); ?>
    <?php if ($toast): ?>
      <?php partial('reminder-toast', ['toast' => $toast, 'action' => url('plant-form.php?id=' . $plant['id'])]); ?>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
