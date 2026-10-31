<?php
// Plant Health Check (P08, wireframe p15): answers "What is wrong with my plant?" with the disease-detection model, then
// turns the result into clear, practical next steps (ML-14, UI half). Members only.
// The page has six states, each with its own address in stage F:
//   health-check.php               → no photo yet; ?plant=47 links the check to Tommy (the Plant Profile's button)
//   health-check.php?analysing     → S2: the leaf is being analysed (p24)
//   health-check.php?prediction=7  → a diagnosis: what we see, what to do now, save and follow up (p15)
//   health-check.php?prediction=8  → S3: the model is not sure, so no diagnosis is given (p24)
//   health-check.php?prediction=9  → S3: a plant the model does not know (Monty), so it does not guess (p24)
//   health-check.php?offline       → the ML service did not answer: log the update by hand
// &plant= changes the plant a result is attached to (&plant=none: none). Stage M: PHP saves the photo, sends it to
// Flask with cURL, stores the answer in ml_predictions and opens health-check.php?prediction= of the new row.

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mock-data.php';

// Stage F stand-in for require_login(): a visitor (?guest) is sent to Log in, exactly as stage B will do.
if (!$current_user) {
    header('Location: ' . url('login.php'));
    exit;
}

$my_plants = array_column($mock_plants, null, 'id');

// ── Which result ────────────────────────────────────────
// ?prediction= must be one of the member's own results, as on the Plant Identifier.

$prediction_param = get_text('prediction');
$result = ctype_digit($prediction_param) ? ($mock_health_results[(int) $prediction_param] ?? null) : null;
if ($result && $result['user_id'] !== $current_user['id']) {
    $result = null;
}

// ── Which plant (p15, element 1) ────────────────────────
// ?plant= wins (the plant menu, the Plant Profile's button); otherwise the plant the result is attached to.
// Only the member's own plants can be linked; 'none' means "Not from my garden".

/** The member's plant for a 'plant' value from the address or a form, or null for 'none', nothing or a wrong id. */
function chosen_plant($value, $my_plants)
{
    return ctype_digit($value) ? ($my_plants[(int) $value] ?? null) : null;
}

$plant_param = $_SERVER['REQUEST_METHOD'] === 'POST' ? post_text('plant') : get_text('plant');
$plant = $plant_param !== '' ? chosen_plant($plant_param, $my_plants)
                             : ($result && $result['plant_id'] !== null ? ($my_plants[$result['plant_id']] ?? null) : null);

// ── Check a leaf · Save · Re-check (Post/Redirect/Get) ──
// Stage B, before anything else: the CSRF token. Then, for a photo: finfo (JPG/PNG) and 5 MB, as on the plant form.

$photo_error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action      = post_text('action');
    $plant_value = $plant ? $plant['id'] : 'none';

    if ($action === 'check') {
        // A file arrived when PHP reports no upload error for it; a hand-made photo[] makes 'error' a list
        if (($_FILES['photo']['error'] ?? null) === UPLOAD_ERR_OK) {
            // Stage F: every leaf is the early blight result, attached to the plant that was chosen
            header('Location: ' . query_url('health-check.php', ['prediction' => 7, 'plant' => $plant_value]));
            exit;
        }
        $photo_error = 'Choose a photo of the leaf first.';
    } elseif ($result && $plant && in_array($action, ['save', 'recheck'], true)) {
        if ($action === 'save') {
            // "Save to Tommy's story" (p15, element 6). Stage B: INSERT INTO health_logs (plant_id, status, note, photo,
            // source) VALUES (?, ?, ?, ?, 'ml'), UPDATE plants SET health_status, and ml_predictions.plant_id = ?.
            // Stage F saves nothing and opens the story, as Save on the plant form opens the profile.
            header('Location: ' . url('plant.php?id=' . $plant['id']));
            exit;
        }
        // "Remind me to re-check in 7 days". Stage B: a care_reminders row (a proposed task 'recheck', DES-07).
        header('Location: ' . query_url('health-check.php', ['prediction' => $result['id'], 'plant' => $plant['id'], 'reminder' => 1]));
        exit;
    }
}

// ── Which state ─────────────────────────────────────────

$is_analysing = !$result && isset($_GET['analysing']);
$is_offline   = !$result && !$is_analysing && isset($_GET['offline']);
// The analysing and offline previews need a photo to show: the one of result 7
$photo = $result ?? (($is_analysing || $is_offline) ? $mock_health_results[7] : null);

// The model only knows the crops it was trained on. A linked plant of another species is never guessed at (p24, S3).
// Without a linked plant the page cannot tell, so it trusts the model's own confidence.
$plant_species = $plant ? ($mock_species[$plant['species_id']] ?? null) : null;
$is_supported  = !$plant_species || in_array($plant_species['common_name'], $mock_supported_crops, true);

if ($result) {
    $top          = $result['predictions'][0];
    $top_level    = confidence_level($top['confidence']);
    $is_unsure    = $top_level['key'] === 'low';
    $guide        = $mock_disease_guide[$top['label']] ?? null;
    $is_healthy   = str_ends_with(strtolower($top['label']), 'healthy');
    // "Tomato leaf": the crop from the guide, or the first part of the class name ("Pepper,_bell" gives "Pepper, bell")
    $crop         = $guide['crop'] ?? str_replace('_', ' ', explode('___', $top['label'])[0]);
    $others_words = implode(' · ', array_map(
        fn ($option) => prediction_name(['type' => 'disease', 'predicted_label' => $option['label']]) . ' ' . round($option['confidence'] * 100) . '%',
        array_slice($result['predictions'], 1)
    ));
}

// "Reminder set" after the redirect above, only when the plant and result really match
$reminder_set = $result && $plant && get_text('reminder') === '1';

// The plant menu (p15, element 1) and the forms keep the result and change only the plant
$plant_url = fn ($value) => query_url('health-check.php', ['prediction' => $result['id'] ?? null, 'plant' => $value]);

/** "Tommy · Tomato, Balcony": a plant as the plant link names it. */
function plant_label($plant)
{
    return $plant['nickname'] . ' · ' . $plant['common_name'] . ($plant['collection_name'] ? ', ' . $plant['collection_name'] : '');
}

$more_count  = count($mock_supported_crops) - 6;   // p15: "tomato, potato, pepper, apple, grape, corn and 8 more crops"
$story_url   = $plant ? url('plant.php?id=' . $plant['id']) : url('garden.php');
$retake_url  = query_url('health-check.php', ['plant' => $plant['id'] ?? '']);

$page_title = 'Plant health check';
$page_css   = 'tools.css';

include __DIR__ . '/../includes/header.php';
?>
<div class="container page">
  <div class="tool-head">
    <div>
      <p class="label tool-eyebrow"><?= icon('activity') ?> Plant tools</p>
      <h1 class="tool-title">Plant health check</h1>
      <p class="tool-intro">Upload a photo of an affected leaf. We'll check it for common diseases and suggest what to do.</p>
    </div>
    <?php partial('tool-switch', ['active' => 'health']); ?>
  </div>

  <div class="tool-grid<?= $photo ? ' has-result' : '' ?>">
    <div class="tool-side">
      <?php if (!$photo): ?>
        <?php // No photo yet: the leaf photo and the plant it belongs to, in one form ?>
        <form class="form-card tool-upload" method="post" action="<?= e(url('health-check.php')) ?>" enctype="multipart/form-data">
          <?php // Stage B: a hidden CSRF token goes here, in every POST form ?>
          <input type="hidden" name="action" value="check">
          <h2 class="visually-hidden">Your photo</h2>
          <label class="dropzone" for="tool-photo">
            <span class="dropzone-icon"><?= icon('image-up') ?></span>
            <span class="dropzone-title">Add a photo of an affected leaf</span>
            <span class="dropzone-text">One leaf, close up and sharp, in daylight</span>
            <span class="btn btn-secondary btn-sm"><?= icon('camera') ?> Choose photo</span>
          </label>
          <input class="visually-hidden" type="file" id="tool-photo" name="photo" accept="image/jpeg,image/png" required
                 aria-describedby="tool-photo-hint<?= $photo_error ? ' tool-photo-error' : '' ?>"<?= $photo_error ? ' aria-invalid="true"' : '' ?>>
          <p class="field-hint photo-hint" id="tool-photo-hint"><?= icon('info') ?> JPG or PNG, up to 5 MB.</p>
          <?php if ($photo_error): ?>
            <p class="field-error" id="tool-photo-error" role="alert"><?= e($photo_error) ?></p>
          <?php endif; ?>

          <?php // Plant link (p15, element 1): optional, so the result can join that plant's story ?>
          <div class="field tool-plant-field">
            <label class="field-label" for="check-plant">Checking a plant from your garden <span class="field-optional">(optional)</span></label>
            <select class="input" id="check-plant" name="plant" aria-describedby="check-plant-hint">
              <option value="none">Not from my garden</option>
              <?php foreach ($my_plants as $option): ?>
                <option value="<?= e($option['id']) ?>"<?= $plant && $plant['id'] === $option['id'] ? ' selected' : '' ?>><?= e(plant_label($option)) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="field-hint" id="check-plant-hint">The result can then be saved to its story.</span>
          </div>

          <button class="btn btn-primary tool-submit" type="submit"><?= icon('activity') ?> Check leaf</button>
        </form>
      <?php else: ?>
        <?php // Leaf photo (p15, element 2): the photo with its time, and a quick way to try another photo ?>
        <section class="form-card tool-photo<?= $is_analysing ? ' is-analysing' : '' ?>" aria-labelledby="photo-title">
          <h2 class="visually-hidden" id="photo-title">Your photo</h2>
          <div class="tool-photo-frame">
            <img src="<?= e(photo_url($photo['image_path'])) ?>" alt="Your photo of the leaf, <?= e($photo['upload_name']) ?>">
            <?php if ($is_analysing): ?><span class="tool-photo-scan" aria-hidden="true"></span><?php endif; ?>
          </div>
          <?php // One file field; "Check this photo" appears once a photo is chosen (tools.css, :has(), no JavaScript) ?>
          <form class="tool-photo-foot tool-photo-foot-stacked" method="post" action="<?= e(url('health-check.php')) ?>" enctype="multipart/form-data">
            <input type="hidden" name="action" value="check">
            <input type="hidden" name="plant" value="<?= e($plant['id'] ?? 'none') ?>">
            <span class="tool-photo-name">Uploaded <?= e(date('j M, H:i', strtotime($photo['created_at']))) ?> · <?= e($photo['upload_name']) ?></span>
            <?php if ($is_analysing): ?>
              <button type="button" class="btn btn-secondary btn-sm" disabled><?= icon('refresh-cw') ?> Check another photo</button>
            <?php else: ?>
              <label class="btn btn-secondary btn-sm" for="another-photo"><?= icon('refresh-cw') ?> Check another photo</label>
              <input class="visually-hidden" type="file" id="another-photo" name="photo" accept="image/jpeg,image/png" required>
              <button class="btn btn-primary btn-sm tool-photo-send" type="submit"><?= icon('activity') ?> Check this photo</button>
            <?php endif; ?>
          </form>
        </section>
      <?php endif; ?>

      <?php // Scope statement (p15, element 7): which plants the model knows, so members know when not to trust it.
            // It opens by itself when the plant is not one of them. ?>
      <section class="form-card tool-scope" id="scope" aria-labelledby="scope-title">
        <h2 class="tool-card-title" id="scope-title">What this tool can check</h2>
        <p class="tool-scope-text">Leaf diseases on tomato, potato, pepper, apple, grape, corn and <?= e($more_count) ?> more crops. Houseplant support is planned.</p>
        <details class="tool-scope-more"<?= $is_supported ? '' : ' open' ?>>
          <summary>See supported plants</summary>
          <ul class="crop-list">
            <?php foreach ($mock_supported_crops as $crop_name): ?>
              <li><?= e($crop_name) ?></li>
            <?php endforeach; ?>
          </ul>
        </details>
      </section>
    </div>

    <div class="tool-main">
      <?php if ($result): ?>
        <?php // Plant link (p15, element 1) for a result: a menu of the member's plants; choosing one re-attaches the check ?>
        <div class="tool-plant">
          <span class="tool-plant-label" id="plant-link-label">Checking a plant from your garden</span>
          <details class="menu tool-plant-menu">
            <summary class="tool-plant-toggle" aria-describedby="plant-link-label">
              <?php if ($plant): ?>
                <img class="tool-plant-thumb" src="<?= e(photo_url($plant['photo'])) ?>" alt="">
                <span><strong><?= e($plant['nickname']) ?></strong> <span class="tool-plant-meta">· <?= e($plant['common_name']) ?><?= $plant['collection_name'] ? ', ' . e($plant['collection_name']) : '' ?></span></span>
              <?php else: ?>
                <?= icon('leaf') ?> <span>Not from my garden</span>
              <?php endif; ?>
              <span class="menu-chevron"><?= icon('chevron-down') ?></span>
            </summary>
            <div class="menu-panel menu-panel-right">
              <?php foreach ($my_plants as $option): ?>
                <a class="menu-item" href="<?= e($plant_url($option['id'])) ?>"<?= $plant && $plant['id'] === $option['id'] ? ' aria-current="true"' : '' ?>><?= e(plant_label($option)) ?></a>
              <?php endforeach; ?>
              <hr class="menu-divider">
              <a class="menu-item" href="<?= e($plant_url('none')) ?>"<?= $plant ? '' : ' aria-current="true"' ?>>Not from my garden</a>
            </div>
          </details>
        </div>
      <?php endif; ?>

      <?php if ($is_analysing): ?>
        <?php partial('analysing-state', ['title' => 'Analysing the leaf…', 'cancel_url' => $retake_url]); ?>

      <?php elseif ($is_offline): ?>
        <?php // Flask is offline or took longer than 10 seconds: say so, and offer the manual way ?>
        <section class="form-card tool-offline" aria-labelledby="offline-title">
          <span class="badge badge-neutral"><?= icon('clock') ?> Not available right now</span>
          <h2 class="not-sure-title" id="offline-title">We couldn't reach the Health Check</h2>
          <p class="not-sure-text">Your photo is fine. Log what you see in the plant's story yourself, or try again in a few minutes.</p>
          <div class="not-sure-actions">
            <a class="btn btn-primary" href="<?= e($story_url) ?>"><?= icon('pencil') ?> Log an update yourself</a>
            <a class="btn btn-secondary" href="<?= e($retake_url) ?>"><?= icon('refresh-cw') ?> Try again</a>
          </div>
        </section>

      <?php elseif ($result && !$is_supported): ?>
        <?php // S3 for a plant the model does not know: whatever Flask answered is not shown at all (p24) ?>
        <?php partial('not-sure', [
            'title'   => "We can't check " . $plant['nickname'] . ' yet',
            'text'    => 'The Health Check knows leaf diseases of ' . count($mock_supported_crops) . ' food crops. ' . $plant_species['common_name']
                         . ' is not one of them, so it does not guess. Houseplant support is planned.',
            'actions' => [
                ['label' => 'Log an update yourself', 'url' => $story_url, 'icon' => 'pencil'],
                ['label' => 'See supported plants',   'url' => '#scope',   'icon' => 'info'],
            ],
        ]); ?>

      <?php elseif ($result && $is_unsure): ?>
        <?php // S3: below 60% no disease is named. The member is told how to get a clearer answer. ?>
        <?php partial('not-sure', [
            'confidence' => $top['confidence'],
            'title'      => "We couldn't check this leaf confidently",
            'text'       => 'Try a sharp photo of one affected leaf in daylight. If the plant gets worse, ask a local agriculture officer.',
            'actions'    => [
                ['label' => 'Retake photo',           'url' => $retake_url, 'icon' => 'camera'],
                ['label' => 'Log an update yourself', 'url' => $story_url,  'icon' => 'pencil'],
            ],
        ]); ?>

      <?php elseif ($result): ?>
        <?php // Diagnosis (p15, element 3): status badge, disease name, cause, confidence bar and runners-up.
              // Soft red for a disease, never alarming words (p15, UX notes). ?>
        <section class="form-card tool-result" aria-labelledby="diagnosis-title">
          <?php if ($reminder_set): ?>
            <p class="alert alert-success tool-alert" role="status"><?= icon('check') ?> <span>Reminder set: re-check <?= e($plant['nickname']) ?> on <?= e(format_day(date('Y-m-d', strtotime('+7 days')))) ?>.</span></p>
          <?php endif; ?>

          <div class="diagnosis-tags">
            <?php if ($is_healthy): ?>
              <span class="badge badge-thriving">Looks healthy</span>
            <?php elseif ($top_level['key'] === 'high'): ?>
              <span class="badge badge-sick">Likely disease</span>
            <?php else: ?>
              <span class="badge badge-needs-attention">Possible disease</span>
            <?php endif; ?>
            <span class="badge badge-neutral"><?= e($crop) ?> leaf</span>
          </div>

          <h2 class="match-name" id="diagnosis-title"><?= e(prediction_name(['type' => 'disease', 'predicted_label' => $top['label']])) ?></h2>
          <?php if ($guide): ?>
            <p class="scientific-name diagnosis-cause"><?= e($guide['cause']) ?></p>
          <?php endif; ?>

          <div class="diagnosis-confidence">
            <?php partial('confidence-meter', ['prediction' => $top]); ?>
            <?php if ($others_words !== ''): ?>
              <p class="confidence-note">Other possibilities: <?= e($others_words) ?></p>
            <?php endif; ?>
          </div>

          <?php if ($guide): ?>
            <?php // Plain explanation (p15, element 4). The model classifies the whole photo; it does not find the spots,
                  // so no boxes are drawn on the photo (p15, UX notes). ?>
            <h3 class="diagnosis-heading">What we see</h3>
            <p class="diagnosis-text"><?= e($guide['symptoms']) ?></p>

            <?php // Action plan (p15, element 5): four numbered steps to follow today ?>
            <h3 class="diagnosis-heading">What to do now</h3>
            <ol class="action-plan">
              <?php foreach ($guide['steps'] as $step): ?>
                <li><?= e($step) ?></li>
              <?php endforeach; ?>
            </ol>
          <?php endif; ?>

          <p class="alert alert-warning diagnosis-warning"><?= icon('triangle-alert') ?> <span>This is a suggestion, not a diagnosis. If more than a third of the plant is affected, get expert advice.</span></p>

          <?php // Save and follow up (p15, element 6): only with a linked plant, because a story belongs to a plant ?>
          <?php if ($plant): ?>
            <div class="tool-actions diagnosis-actions">
              <form method="post" action="<?= e($plant_url($plant['id'])) ?>">
                <?php // Stage B: a hidden CSRF token goes here, in every POST form ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="plant" value="<?= e($plant['id']) ?>">
                <button class="btn btn-primary" type="submit"><?= icon('check') ?> Save to <?= e($plant['nickname']) ?>'s story</button>
              </form>
              <form method="post" action="<?= e($plant_url($plant['id'])) ?>">
                <input type="hidden" name="action" value="recheck">
                <input type="hidden" name="plant" value="<?= e($plant['id']) ?>">
                <button class="btn btn-secondary" type="submit"><?= icon('calendar') ?> Remind me to re-check in 7 days</button>
              </form>
            </div>
          <?php else: ?>
            <p class="tool-honesty"><?= icon('info') ?> <span>Choose a plant from your garden above to save this result to its story.</span></p>
          <?php endif; ?>
        </section>

      <?php else: ?>
        <?php // Before a photo: what the result will look like ?>
        <section class="form-card tool-waiting" aria-labelledby="waiting-title">
          <h2 class="tool-card-title tool-waiting-title" id="waiting-title"><?= icon('activity') ?> Your diagnosis appears here</h2>
          <p class="text-small">PlantFolio checks the leaf for the diseases it knows, says how sure it is, and suggests what to do today. Nothing is saved until you choose to.</p>
          <ul class="confidence-legend">
            <li><span class="legend-dot legend-high" aria-hidden="true"></span><span><strong>High</strong> · 80% or more</span></li>
            <li><span class="legend-dot legend-medium" aria-hidden="true"></span><span><strong>Medium</strong> · 60 to 79%</span></li>
            <li><span class="legend-dot legend-low" aria-hidden="true"></span><span><strong>Not sure</strong> · below 60%, so no disease is named</span></li>
          </ul>
        </section>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
