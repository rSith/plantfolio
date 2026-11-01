<?php
// Plant Identifier (P07, wireframe p14): answers "What is this plant?" from one photo and hands the result straight
// into Add Plant (ML-12, UI half). Members only.
// The page has five states, each with its own address in stage F:
//   identify.php                → no photo yet: the upload box, the photo tips and what the result will look like
//   identify.php?analysing      → S2: the photo is being analysed (p24)
//   identify.php?prediction=5   → a result: the top match, care chips, Add to my garden and the alternatives (p14)
//   identify.php?prediction=6   → S3: the model is not sure, so no answer is given (p24)
//   identify.php?offline        → the ML service did not answer: go on by hand (the site never breaks because of ML)
// Stage F: sending a photo opens result 5. Stage M: PHP saves and resizes the photo, sends it to Flask with cURL
// (includes/ml-client.php), stores the answer in ml_predictions and opens identify.php?prediction= of the new row.

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mock-data.php';

// Stage F stand-in for require_login(): a visitor (?guest) is sent to Log in, exactly as stage B will do.
if (!$current_user) {
    header('Location: ' . url('login.php'));
    exit;
}

// ── Sending a photo (Post/Redirect/Get) ─────────────────
// The upload box and the Retake / Upload new buttons each have their own file field. Stage B, first: the CSRF token,
// then the photo's real type with finfo (JPG/PNG) and its size (5 MB), as on the plant form.

$photo_error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sent = false;
    foreach (['photo', 'photo_camera', 'photo_upload'] as $field) {
        // No upload error means a file arrived. A hand-made photo[] makes 'error' a list: not a photo.
        if (($_FILES[$field]['error'] ?? null) === UPLOAD_ERR_OK) {
            $sent = true;
        }
    }
    if ($sent) {
        header('Location: ' . url('identify.php?prediction=5'));
        exit;
    }
    $photo_error = 'Choose a photo of your plant first.';
}

// ── Which state ─────────────────────────────────────────
// ?prediction= must be one of the member's own results: another member's photo is none of their business.

$prediction_param = get_text('prediction');
$result = ctype_digit($prediction_param) ? ($mock_identify_results[(int) $prediction_param] ?? null) : null;
if ($result && $result['user_id'] !== $current_user['id']) {
    $result = null;
}

$is_analysing = !$result && isset($_GET['analysing']);
$is_offline   = !$result && !$is_analysing && isset($_GET['offline']);
// The analysing and offline previews need a photo to show: the one of result 5
$photo = $result ?? (($is_analysing || $is_offline) ? $mock_identify_results[5] : null);

$species_by_name = array_column($mock_species, null, 'scientific_name');

if ($result) {
    $top       = $result['predictions'][0];
    $top_level = confidence_level($top['confidence']);
    $is_unsure = $top_level['key'] === 'low';   // below 60%: shown as S3, never as an answer
    $guide     = $species_by_name[$top['label']] ?? null;   // the model may name a species the guide does not have yet
    $others    = array_slice($result['predictions'], 1);
}

/** "Water about once a week" from a species' interval: 7 days reads as a week, 14 as two weeks. */
function water_words($days)
{
    if ($days === 7) {
        return 'Water about once a week';
    }
    if ($days % 7 === 0) {
        return 'Water about every ' . ($days / 7) . ' weeks';
    }
    return 'Water about every ' . $days . ' days';
}

// Add to my garden (p14, element 6): Add Plant with this result's photo, species and reminders already filled in
$add_url = $result ? url('plant-form.php?prediction=' . $result['id']) : url('plant-form.php');

$page_title = 'Identify a plant';
$page_css   = 'tools.css';

include __DIR__ . '/../includes/header.php';
?>
<div class="container page">
  <div class="tool-head">
    <div>
      <p class="label tool-eyebrow"><?= icon('sparkles') ?> Plant tools</p>
      <h1 class="tool-title">Identify a plant</h1>
      <p class="tool-intro">Upload a clear photo of the whole plant or one leaf. We'll suggest the most likely species.</p>
    </div>
    <?php partial('tool-switch', ['active' => 'identify']); ?>
  </div>

  <div class="tool-grid<?= $photo ? ' has-result' : '' ?>">
    <div class="tool-side">
      <?php if (!$photo): ?>
        <?php // No photo yet: the upload box (p14, element 2 before a photo). multipart/form-data carries the file. ?>
        <form class="form-card tool-upload" method="post" action="<?= e(url('identify.php')) ?>" enctype="multipart/form-data">
          <?php // Stage B: a hidden CSRF token goes here, in every POST form ?>
          <h2 class="visually-hidden">Your photo</h2>
          <label class="dropzone" for="tool-photo">
            <span class="dropzone-icon"><?= icon('image-up') ?></span>
            <span class="dropzone-title">Add a photo of your plant</span>
            <span class="dropzone-text">The whole plant or one leaf, from your files or your phone camera</span>
            <span class="btn btn-secondary btn-sm"><?= icon('camera') ?> Choose photo</span>
          </label>
          <input class="visually-hidden" type="file" id="tool-photo" name="photo" accept="image/jpeg,image/png" required
                 aria-describedby="tool-photo-hint<?= $photo_error ? ' tool-photo-error' : '' ?>"<?= $photo_error ? ' aria-invalid="true"' : '' ?>>
          <p class="field-hint photo-hint" id="tool-photo-hint"><?= icon('info') ?> JPG or PNG, up to 5 MB.</p>
          <?php if ($photo_error): ?>
            <p class="field-error" id="tool-photo-error" role="alert"><?= e($photo_error) ?></p>
          <?php endif; ?>
          <button class="btn btn-primary tool-submit" type="submit"><?= icon('sparkles') ?> Identify plant</button>
        </form>
      <?php else: ?>
        <?php // Photo preview (p14, element 2): the photo with Retake (the phone camera) and Upload new ?>
        <section class="form-card tool-photo<?= $is_analysing ? ' is-analysing' : '' ?>" aria-labelledby="photo-title">
          <h2 class="visually-hidden" id="photo-title">Your photo</h2>
          <div class="tool-photo-frame">
            <img src="<?= e(photo_url($photo['image_path'])) ?>" alt="Your photo, <?= e($photo['upload_name']) ?>">
            <?php if ($is_analysing): ?><span class="tool-photo-scan" aria-hidden="true"></span><?php endif; ?>
          </div>
          <?php // Two file fields: capture="environment" opens the rear camera at once; the other offers the photo
                // library too. Choosing a photo shows "Identify this photo" (tools.css, :has(), no JavaScript).
                // novalidate lets the form go with only one of the two filled in; the server checks that one is.
                // While analysing, every button is disabled, so the photo is not sent twice (p24, S2). ?>
          <form class="tool-photo-foot" method="post" action="<?= e(url('identify.php')) ?>" enctype="multipart/form-data" novalidate>
            <span class="tool-photo-name"><?= e($photo['upload_name']) ?> · <?= e(number_format($photo['upload_size'] / 1048576, 1)) ?> MB</span>
            <span class="tool-photo-actions">
              <?php if ($is_analysing): ?>
                <button type="button" class="btn btn-secondary btn-sm" disabled><?= icon('camera') ?> Retake</button>
                <button type="button" class="btn btn-secondary btn-sm" disabled><?= icon('upload') ?> Upload new</button>
              <?php else: ?>
                <label class="btn btn-secondary btn-sm" for="photo-camera"><?= icon('camera') ?> Retake</label>
                <input class="visually-hidden" type="file" id="photo-camera" name="photo_camera" accept="image/jpeg,image/png" capture="environment" required>
                <label class="btn btn-secondary btn-sm" for="photo-upload"><?= icon('upload') ?> Upload new</label>
                <input class="visually-hidden" type="file" id="photo-upload" name="photo_upload" accept="image/jpeg,image/png" required>
                <button class="btn btn-primary btn-sm tool-photo-send" type="submit"><?= icon('sparkles') ?> Identify this photo</button>
              <?php endif; ?>
            </span>
          </form>
        </section>
      <?php endif; ?>

      <?php // Photo tips (p14, element 3): four quick rules that improve accuracy before the member even tries ?>
      <section class="form-card tool-tips" aria-labelledby="tips-title">
        <h2 class="form-card-title tool-card-title" id="tips-title">For the best result</h2>
        <ul class="tips-list">
          <li class="tip-do"><?= icon('check') ?> One plant in the frame</li>
          <li class="tip-do"><?= icon('check') ?> Natural daylight</li>
          <li class="tip-do"><?= icon('check') ?> Leaves in focus</li>
          <li class="tip-dont"><?= icon('x') ?> <span><span class="visually-hidden">Avoid </span>Dark or blurry photos</span></li>
        </ul>
      </section>
    </div>

    <div class="tool-main">
      <?php if ($is_analysing): ?>
        <?php partial('analysing-state', ['title' => 'Analysing leaves…', 'cancel_url' => url('identify.php')]); ?>

      <?php elseif ($is_offline): ?>
        <?php // Flask is offline or took longer than 10 seconds: the page says so honestly and offers the manual way ?>
        <section class="form-card tool-offline" aria-labelledby="offline-title">
          <span class="badge badge-neutral"><?= icon('clock') ?> Not available right now</span>
          <h2 class="not-sure-title" id="offline-title">We couldn't reach the Plant Identifier</h2>
          <p class="not-sure-text">Your photo is fine. Add the plant and choose the species yourself, or try again in a few minutes.</p>
          <div class="not-sure-actions">
            <a class="btn btn-primary" href="<?= e(url('plant-form.php')) ?>"><?= icon('plus') ?> Add the plant yourself</a>
            <a class="btn btn-secondary" href="<?= e(url('identify.php')) ?>"><?= icon('refresh-cw') ?> Try again</a>
          </div>
        </section>

      <?php elseif ($result && $is_unsure): ?>
        <?php // S3: below 60% the model's guesses are possibilities, not an answer. The member chooses. ?>
        <?php partial('not-sure', [
            'confidence' => $top['confidence'],
            'title'      => "We couldn't identify this confidently",
            'text'       => 'Try a closer photo of one leaf in daylight, or pick the species yourself.',
            'actions'    => [
                ['label' => 'Retake photo',    'url' => url('identify.php'), 'icon' => 'camera'],
                ['label' => 'Choose manually', 'url' => url('plant-form.php?prediction=' . $result['id']) . '#species', 'icon' => 'pencil'],
            ],
        ]); ?>

      <?php elseif ($result): ?>
        <?php // Top match (p14, element 4): name, scientific name, reference photo and a labelled confidence bar ?>
        <section class="form-card tool-result" aria-labelledby="match-title">
          <div class="tool-result-head">
            <p class="label">Top match</p>
            <span class="badge badge-confidence-<?= e($top_level['key']) ?>"><?= icon('check') ?> <?= e($top_level['label']) ?> confidence</span>
          </div>

          <div class="match">
            <?php if ($guide): ?>
              <img class="match-photo" src="<?= e(photo_url($guide['photo'])) ?>" alt="">
            <?php endif; ?>
            <div>
              <?php // A species the guide does not know yet is named by its scientific name only ?>
              <h2 class="match-name" id="match-title"><?= e($guide['common_name'] ?? $top['label']) ?></h2>
              <?php if ($guide): ?>
                <p class="scientific-name match-scientific"><?= e($top['label']) ?></p>
              <?php endif; ?>
            </div>
          </div>

          <?php partial('confidence-meter', ['prediction' => $top]); ?>
          <p class="confidence-note"><?= $top_level['key'] === 'high' ? 'High means 80% or more.' : 'Medium means 60 to 79%: check the name against your plant.' ?></p>

          <?php if ($guide): ?>
            <?php // Care and safety chips (p14, element 5), from the species guide ?>
            <ul class="care-chips" aria-label="Care at a glance">
              <li class="care-chip"><?= icon('sun') ?> <?= e($guide['light']) ?> light</li>
              <li class="care-chip"><?= icon('droplet') ?> <?= e(water_words($guide['water_interval_days'])) ?></li>
              <?php if ($guide['pet_safe']): ?>
                <li class="care-chip"><?= icon('paw-print') ?> Safe for cats and dogs</li>
              <?php else: ?>
                <li class="care-chip care-chip-warning"><?= icon('triangle-alert') ?> Toxic to cats and dogs</li>
              <?php endif; ?>
            </ul>
          <?php endif; ?>

          <div class="tool-actions">
            <?php // Nothing is saved until Save on the plant form: the AI suggests, the member decides ?>
            <a class="btn btn-primary" href="<?= e($add_url) ?>"><?= icon('plus') ?> Add to my garden</a>
            <?php if ($guide): ?>
              <?php // "Read care guide" opens the species guide right here: <details> needs no JavaScript ?>
              <details class="care-guide">
                <summary class="btn btn-secondary"><?= icon('book-open') ?> Read care guide</summary>
                <dl class="care-guide-list">
                  <div><dt>Light</dt><dd><?= e($guide['light']) ?></dd></div>
                  <div><dt>Water</dt><dd><?= e($guide['water']) ?></dd></div>
                  <div><dt>Humidity</dt><dd><?= e($guide['humidity']) ?></dd></div>
                  <div><dt>Pets</dt><dd><?= $guide['pet_safe'] ? 'Safe for cats and dogs' : 'Toxic to cats and dogs' ?></dd></div>
                </dl>
                <p class="care-guide-notes"><?= e($guide['notes']) ?></p>
              </details>
            <?php endif; ?>
          </div>
        </section>

      <?php else: ?>
        <?php // Before a photo: what the result will look like, and what the confidence words mean ?>
        <section class="form-card tool-waiting" aria-labelledby="waiting-title">
          <h2 class="tool-card-title tool-waiting-title" id="waiting-title"><?= icon('sparkles') ?> Your result appears here</h2>
          <p class="text-small">PlantFolio suggests the most likely species and says how sure it is. You always decide.</p>
          <ul class="confidence-legend">
            <li><span class="legend-dot legend-high" aria-hidden="true"></span><span><strong>High</strong> · 80% or more</span></li>
            <li><span class="legend-dot legend-medium" aria-hidden="true"></span><span><strong>Medium</strong> · 60 to 79%</span></li>
            <li><span class="legend-dot legend-low" aria-hidden="true"></span><span><strong>Not sure</strong> · below 60%, so you choose the species</span></li>
          </ul>
        </section>
      <?php endif; ?>

      <?php if ($result): ?>
        <?php // Alternatives and honesty note (p14, element 7). Below 60% the list is all there is, under a plainer title. ?>
        <section class="form-card tool-alternatives" aria-labelledby="others-title">
          <h2 class="form-card-title tool-card-title" id="others-title"><?= $is_unsure ? 'Could it be one of these?' : 'Other possibilities' ?></h2>
          <ul class="alt-list">
            <?php foreach ($is_unsure ? $result['predictions'] : $others as $option): ?>
              <?php
              $known   = $species_by_name[$option['label']] ?? null;
              $percent = round($option['confidence'] * 100);
              ?>
              <li class="alt">
                <span class="alt-icon"><?= icon('leaf') ?></span>
                <div class="alt-body">
                  <p class="alt-name">
                    <span class="scientific-name"><?= e($option['label']) ?></span>
                    <?php if ($known): ?><span class="alt-common">· <?= e($known['common_name']) ?></span><?php endif; ?>
                  </p>
                  <div class="confidence-row">
                    <div class="meter" aria-hidden="true"><span class="meter-fill" style="width: <?= e($percent) ?>%"></span></div>
                    <span class="confidence-value"><?= e($percent) ?>%</span>
                  </div>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
          <p class="tool-honesty"><?= icon('info') ?> <span>PlantFolio suggests, you decide. When confidence is below 60% we show "Not sure" and ask you to choose the species yourself.</span></p>
        </section>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
