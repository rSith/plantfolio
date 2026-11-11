<?php
// Plant Profile (P05, wireframe p12): one page per plant that tells its story — photos, identity, care needs,
// reminders and a dated health timeline (CORE-18, UI half). Simplified on 2026-11-11: each fact is shown once,
// so the care tiles and the Reminders and Photos tabs were removed. plant.php?id=42 shows Monty.
// Logged in · public read: anyone may open a public plant and sees it read-only, with an owner card and a like
// button (CORE-21, UI half); a private plant is "not found" to everyone but its owner.

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mock-data.php';
require_once __DIR__ . '/../includes/care-guide.php';
require_once __DIR__ . '/../includes/reminder-form.php';   // Add a reminder popup: checks, saving, toast

// ── Which plant ─────────────────────────────────────────
// Stage B: SELECT plants.*, plant_species.*, collections.name … WHERE plants.id = ? (one prepared query).

// The mock rows are split in two (Nimali's plants and other members'); the plants table holds them all.
$plant_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$plant    = null;
foreach (array_merge($mock_plants, $mock_community_plants) as $row) {
    if ($row['id'] === $plant_id) {
        $plant = $row;
        break;
    }
}

$is_owner = $plant && $current_user && $plant['user_id'] === $current_user['id'];

// A private plant answers exactly like a missing one, so nobody can even learn that it exists (privacy rule).
if (!$plant || (!$plant['is_public'] && !$is_owner)) {
    http_response_code(404);   // tells browsers and search engines that the page does not exist
    $page_title = 'Plant not found';
    include __DIR__ . '/../includes/header.php';
    ?>
    <div class="container page">
      <section class="panel">
        <?php partial('empty-state', [
            'heading' => 'h1',   // the whole page: its one main heading
            'title'   => 'Plant not found',
            'text'    => 'This plant does not exist, or its owner keeps it private.',
            'actions' => $current_user
                ? [['label' => 'Back to My Garden', 'href' => 'garden.php', 'icon' => 'leaf']]
                : [['label' => 'Explore plants', 'href' => 'explore.php', 'icon' => 'search']],
        ]); ?>
      </section>
    </div>
    <?php
    include __DIR__ . '/../includes/footer.php';
    exit;
}

// ── Reminders (owner only): Add a reminder, Mark done, Undo ──
// The Dashboard's popup and Mark done, for this plant only: the plant is a hidden field, and reminder_form_save() accepts no other
// plant id (ownership check). Stage F keeps it in the session; stage B saves it (CORE-22).
$reminder_values = reminder_defaults($plant['id']);
$reminder_errors = [];
$page_url        = url('plant.php?id=' . $plant['id']);
if ($is_owner && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post_text('form') === 'reminder') {
        [$reminder_values, $reminder_errors] = reminder_form_save([$plant['id'] => $plant]);
        if (!$reminder_errors) {
            header('Location: ' . $page_url);   // POST, then redirect: a reload cannot add it twice
            exit;
        }
    } elseif (post_text('form') === 'done') {
        // Mark done, as in the Dashboard's Today's care: any of this plant's reminders may be marked here
        $plant_rows = [];
        foreach ($mock_plant_reminders as $row) {
            if ($row['plant_id'] === $plant['id']) {
                $plant_rows[] = $row + ['nickname' => $plant['nickname'], 'photo' => $plant['photo'], 'common_name' => $plant['common_name']];
            }
        }
        reminder_mark_done(post_text('reminder'), $plant_rows);
        header('Location: ' . $page_url);
        exit;
    } elseif (post_text('form') === 'undo_reminder') {
        reminder_undo();
        header('Location: ' . $page_url);
        exit;
    }
}
$toast = $is_owner ? reminder_toast_take() : null;

// ── Everything shown about it ───────────────────────────
// Each list is one table filtered by plant_id: WHERE plant_id = ? in stage B.

$reminders = array_values(array_filter($mock_plant_reminders, fn ($r) => $r['plant_id'] === $plant['id']));
usort($reminders, fn ($a, $b) => $a['next_due'] <=> $b['next_due']);   // soonest first
// The joined columns the care rows print (partials/care-row.php)
$reminders = array_map(fn ($r) => $r + ['nickname' => $plant['nickname'], 'photo' => $plant['photo'], 'common_name' => $plant['common_name']], $reminders);
$due_count = count(array_filter($reminders, fn ($r) => $r['next_due'] <= date('Y-m-d')));   // due today or overdue

$story  = array_values(array_filter($mock_health_logs, fn ($log) => $log['plant_id'] === $plant['id']));
usort($story, fn ($a, $b) => $b['logged_at'] <=> $a['logged_at']);   // newest first (stage B: ORDER BY logged_at DESC)
$photos = array_values(array_filter($mock_plant_photos, fn ($photo) => $photo['plant_id'] === $plant['id']));
if (!$photos) {
    // No gallery rows yet: the profile photo is the only one
    $photos = [['id' => 0, 'plant_id' => $plant['id'], 'path' => $plant['photo'], 'created_at' => $plant['acquired_on']]];
}
// The thumbnail of the photo the large frame shows first, so it can be marked as chosen
$shown_photo = array_search($plant['photo'], array_column($photos, 'path'), true);
$like_count = $mock_like_counts[$plant['id']] ?? 0;

// The owner, joined from users (public columns only). Visitors see an owner card instead of the edit controls.
$owner     = $mock_users[$plant['user_id']];
$owner_url = url('user.php?u=' . urlencode($owner['username']));

// No tabs any more (Ranshitha's sketch, 2026-11-11): the care guide sits under the name, so the Story is the only
// long section left and needs no tab. An old link with ?tab=care or ?tab=photos simply shows the page.

// AI care guide (under the name): one saved guide per species. Stage B: care_guide_get() reads the care_guides row;
// when there is none, the card shows Generating and care-guide.js asks api/care-guide.php for it.
$guide_species = care_guide_species($plant);
$guide_row     = $guide_species === null ? null : ($mock_care_guides[normalise_species($guide_species)] ?? null);
$guide         = $guide_row ? json_decode($guide_row['guide_json'], true) : null;
if ($guide_species === null) {
    $guide_state = 'no-species';
} else {
    $guide_state = $guide ? 'ready' : 'generating';
}
// Stage F previews of the other states, like identify.php?offline: &guide=generating, offline, invalid or no-species
$guide_preview = get_text('guide');
if (in_array($guide_preview, ['generating', 'offline', 'invalid', 'no-species'], true)) {
    $guide_state = $guide_preview;
}

$collection_url = url('garden.php?collection=' . $plant['collection_id']);
$page_title     = $plant['nickname'];
$page_css       = 'plant.css';
$page_js        = 'plant.js';   // the Add photo and Add to story popups, Done on reminders (UI only in stage F)

// The navigation follows the breadcrumb: your own plant is part of My Garden, anyone else's is part of Explore
$nav_area = $is_owner ? 'garden' : 'explore';

include __DIR__ . '/../includes/header.php';
?>
<div class="container page">
  <?php // The owner sees where the plant lives in their garden; anyone else sees whose plant it is ?>
  <nav aria-label="Breadcrumb">
    <ol class="breadcrumb plant-breadcrumb">
      <?php if ($is_owner): ?>
        <li><a href="<?= e(url('garden.php')) ?>">My Garden</a></li>
        <?php // A plant in no collection sits straight under My Garden ?>
        <?php if ($plant['collection_id'] !== null): ?>
          <li><a href="<?= e($collection_url) ?>"><?= e($plant['collection_name']) ?></a></li>
        <?php endif; ?>
      <?php else: ?>
        <li><a href="<?= e(url('explore.php')) ?>">Explore</a></li>
        <li><a href="<?= e($owner_url) ?>">@<?= e($owner['username']) ?></a></li>
      <?php endif; ?>
      <li><span aria-current="page"><?= e($plant['nickname']) ?></span></li>
    </ol>
  </nav>

  <div class="plant-top">
    <?php // Photo gallery (p12, element 2): the profile photo large, then every photo as a thumbnail, newest first.
          // A thumbnail is a button that shows its photo in the large frame (app.js, data-gallery). The thumbnails
          // scroll sideways when there are more than fit; the Add tile stays in view on the right. ?>
    <div class="plant-gallery" data-gallery>
      <img class="plant-photo" src="<?= e(photo_url($plant['photo'])) ?>" alt="Photo of <?= e($plant['nickname']) ?>, a <?= e($plant['common_name']) ?>" data-gallery-main>
      <div class="plant-thumbs">
        <div class="plant-thumb-strip" data-thumb-strip>
          <?php foreach ($photos as $number => $photo): ?>
            <button type="button" class="plant-thumb" data-photo-src="<?= e(photo_url($photo['path'])) ?>" data-photo-alt="Photo of <?= e($plant['nickname']) ?>, <?= e(date('j M Y', strtotime($photo['created_at']))) ?>" aria-pressed="<?= $number === $shown_photo ? 'true' : 'false' ?>">
              <img src="<?= e(photo_url($photo['path'])) ?>" alt="Show photo from <?= e(date('j M Y', strtotime($photo['created_at']))) ?>" width="80" height="80" loading="lazy">
            </button>
          <?php endforeach; ?>
        </div>
        <?php if ($is_owner): ?>
          <?php // Opens the Add a photo popup (app.js); without JavaScript, &photo=1 opens it when the page loads ?>
          <a class="plant-thumb plant-thumb-add" href="<?= e(url('plant.php?id=' . $plant['id'] . '&photo=1')) ?>" data-dialog="add-photo" aria-haspopup="dialog"><?= icon('camera') ?> Add</a>
        <?php endif; ?>
      </div>
    </div>

    <?php // Right column (phone: straight after the photo): who it is, then how to care for it ?>
    <div class="plant-info">
      <?php // Identity (p12, element 3), with Edit, Health check and List for exchange stacked beside it.
            // Phone: the name first, then the buttons in one wrapping row. No status badge here: each Story entry
            // shows the status it was given. ?>
      <div class="plant-head">
        <div class="plant-identity">
          <h1 class="plant-name"><?= e($plant['nickname']) ?></h1>
          <p class="plant-species"><span class="scientific-name"><?= e($plant['scientific_name']) ?></span> · <?= e($plant['common_name']) ?></p>
          <p class="plant-meta">Updated <?= e(mb_strtolower(time_ago($plant['last_logged_at']))) ?></p>
        </div>
        <?php // The page's actions, stacked on the right and as tall as the name block (Ranshitha's choice) ?>
        <div class="plant-head-stack">
          <?php if ($is_owner): ?>
            <a class="btn btn-primary btn-sm" href="<?= e(url('plant-form.php?id=' . $plant['id'])) ?>"><?= icon('pencil') ?> Edit</a>
            <a class="btn btn-secondary btn-sm" href="<?= e(url('health-check.php?plant=' . $plant['id'])) ?>"><?= icon('activity') ?> Health check</a>
            <a class="btn btn-secondary btn-sm" href="<?= e(url('listing-form.php?plant=' . $plant['id'])) ?>"><?= icon('repeat') ?> List for exchange</a>
          <?php elseif ($current_user): ?>
            <?php // Read-only view (p12, UX notes): a like button instead of the edit controls.
                  // Stage F: does nothing yet. Stage B saves the like (COM-15, a Could task). ?>
            <button type="button" class="btn btn-secondary btn-sm" aria-pressed="false"><?= icon('heart') ?> Like · <?= e($like_count) ?></button>
          <?php else: ?>
            <a class="btn btn-secondary btn-sm" href="<?= e(url('login.php')) ?>"><?= icon('heart') ?> Log in to like · <?= e($like_count) ?></a>
          <?php endif; ?>
        </div>
      </div>

      <?php // How to care for it ?>
      <?php partial('care-guide', [
          'plant'     => $plant,
          'is_owner'  => $is_owner,
          'state'     => $guide_state,
          'guide'     => $guide,
          'guide_row' => $guide_row,
          'species'   => $guide_species,
      ]); ?>
    </div>

    <?php // Left column under the photo on a desktop; after the care guide on a phone ?>
    <div class="plant-aside">
      <?php // Quick facts: the small facts that used to be spread over the meta line and the Reminders card ?>
      <section class="panel plant-panel" aria-labelledby="facts-title">
        <h2 class="panel-title" id="facts-title">Quick facts</h2>
        <dl class="quick-facts">
          <?php if ($is_owner): ?>
            <?php // Only the owner sees the collection: a visitor's breadcrumb shows the owner, not their collections ?>
            <div>
              <dt><?= icon('layout-grid') ?> Collection</dt>
              <dd>
                <?php if ($plant['collection_id'] !== null): ?>
                  <a href="<?= e($collection_url) ?>"><?= e($plant['collection_name']) ?></a>
                <?php else: ?>
                  No collection
                <?php endif; ?>
              </dd>
            </div>
          <?php endif; ?>
          <div>
            <dt><?= icon('calendar') ?> <?= $is_owner ? 'With you since' : 'Growing since' ?></dt>
            <dd><?= e(date('M Y', strtotime($plant['acquired_on']))) ?></dd>
          </div>
          <?php if ($is_owner): ?>
            <div>
              <dt><?= icon($plant['is_public'] ? 'eye' : 'lock') ?> Visibility</dt>
              <dd><?= $plant['is_public'] ? 'Public · shown in Explore' : 'Private · only you can see it' ?></dd>
            </div>
          <?php endif; ?>
          <?php if ($plant['is_public']): ?>
            <div>
              <dt><?= icon('heart') ?> Likes</dt>
              <dd><?= e($like_count) ?> from the community</dd>
            </div>
          <?php endif; ?>
        </dl>
      </section>

      <?php if ($is_owner): ?>
        <?php // Reminders card (p12, element 7), built like the Dashboard's Today's care: a count of what is due, one care
              // row per reminder (partials/care-row.php, compact) with its due tag and Mark done; "+ Add" at the
              // top opens the Add a reminder popup. Mark done is a POST: the date moves on, the Story gets a care line, and a
              // toast with Undo appears (reminder_mark_done()). Stage F keeps it in the session. ?>
        <section class="panel plant-panel plant-reminders" aria-labelledby="reminders-title">
          <div class="panel-head">
            <h2 class="panel-title" id="reminders-title">Reminders
              <?php if ($due_count > 0): ?><span class="badge badge-neutral"><?= e($due_count) ?> due</span><?php endif; ?>
            </h2>
            <?php // Opens the Add a reminder popup (app.js); without JavaScript, &reminder=1 opens it when the page loads ?>
            <a class="panel-link" href="<?= e(url('plant.php?id=' . $plant['id'] . '&reminder=1')) ?>" data-dialog="add-reminder" aria-haspopup="dialog"><?= icon('plus') ?> Add</a>
          </div>
          <?php if ($reminders): ?>
            <div class="care-list">
              <?php foreach ($reminders as $reminder): ?>
                <?php partial('care-row', ['reminder' => $reminder, 'action' => $page_url, 'compact' => true]); ?>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="text-small plant-reminders-empty">No reminders yet. Add one, and it shows here and on your Dashboard.</p>
          <?php endif; ?>
        </section>
      <?php else: ?>
        <?php // Owner card (p12, UX notes): whose plant this is. No contact details: those belong to the exchange. ?>
        <section class="panel plant-panel owner-card" aria-labelledby="owner-title">
          <h2 class="label" id="owner-title">Grown by</h2>
          <div class="owner-card-member">
            <?php partial('avatar', ['member' => $owner]); ?>
            <span>
              <strong><?= e($owner['full_name']) ?></strong>
              <span class="text-small">@<?= e($owner['username']) ?> · <?= e($owner['location']) ?></span>
            </span>
          </div>
          <ul class="plant-facts">
            <li><?= icon('leaf') ?> <?= e($owner['plant_count']) ?> plants · member since <?= e(date('M Y', strtotime($owner['created_at']))) ?></li>
            <li><?php partial('rating', ['rating' => $owner]); ?> as a trader</li>
          </ul>
          <a class="btn btn-secondary owner-card-btn" href="<?= e($owner_url) ?>">View profile</a>
        </section>
      <?php endif; ?>
    </div>
  </div>

  <?php // Plant story (p12, element 6): full width under both columns, newest first. Each entry type looks different
        // (partials/story-entry.php). The timeline is at most 760px wide. ?>
  <section class="plant-story-section" aria-labelledby="story-title">
    <div class="story-head">
      <h2 class="plant-story-title" id="story-title">Story</h2>
      <?php if ($is_owner): ?>
        <?php // Opens the Add to story popup below (app.js) ?>
        <button type="button" class="story-add-link" data-dialog="add-story" aria-haspopup="dialog"><?= icon('plus') ?> Add to story</button>
      <?php endif; ?>
    </div>

    <p class="panel-empty" data-story-empty<?= $story ? ' hidden' : '' ?>><?= icon('info') ?> No story entries yet.</p>
    <ol class="story" data-story>
      <?php foreach ($story as $log): ?>
        <?php partial('story-entry', ['log' => $log]); ?>
      <?php endforeach; ?>
    </ol>
  </section>

  <?php if ($is_owner): ?>
    <?php // Add to <name>'s story popup (CORE-19, UI half). Moved here from Edit plant on 2026-11-11.
          // plant.js puts the new entry into the timeline without a reload. Stage F changes the page only;
          // stage B sends the form with fetch() and saves a health_logs row (and the photo, CORE-17).
          // plant.php?id=…&story=1 opens it at once (the link on Edit plant). ?>
    <dialog class="modal modal-wide" id="add-story" aria-labelledby="add-story-title"<?= get_text('story') === '1' ? ' data-open-on-load' : '' ?>
            data-plant-name="<?= e($plant['nickname']) ?>">
      <form class="modal-form" id="story-form" method="post" action="<?= e($page_url) ?>" enctype="multipart/form-data" novalidate>
        <div class="modal-head">
          <h2 class="modal-title" id="add-story-title">Add to <?= e($plant['nickname']) ?>’s story</h2>
          <button class="icon-btn" formmethod="dialog" formnovalidate aria-label="Close"><?= icon('x') ?></button>
        </div>

        <div class="modal-body story-form-body">
          <fieldset class="form-fieldset">
            <legend class="field-label">What happened?</legend>
            <div class="story-kinds">
              <?php foreach (['water' => 'Watered', 'fertilise' => 'Fertilised', 'prune' => 'Pruned', 'repot' => 'Repotted'] as $kind => $label): ?>
                <input class="story-kind-input" type="radio" id="story-kind-<?= e($kind) ?>" name="kind" value="<?= e($kind) ?>">
                <label class="chip story-kind" for="story-kind-<?= e($kind) ?>"><span class="task task-<?= e($kind) ?>"><?= icon(task_icon($kind)) ?></span> <?= e($label) ?></label>
              <?php endforeach; ?>
              <input class="story-kind-input" type="radio" id="story-kind-note" name="kind" value="note" checked>
              <label class="chip story-kind" for="story-kind-note"><?= icon('pencil') ?> Note</label>
              <input class="story-kind-input" type="radio" id="story-kind-milestone" name="kind" value="milestone">
              <label class="chip story-kind" for="story-kind-milestone"><?= icon('sprout') ?> Milestone</label>
            </div>
          </fieldset>

          <div class="field">
            <label class="field-label" for="story-note">What’s new? <span class="field-optional" data-note-optional hidden>(optional)</span></label>
            <textarea class="input" id="story-note" name="note" rows="3" maxlength="500" aria-describedby="story-note-error"></textarea>
            <p class="field-error" id="story-note-error" data-note-error hidden>Write what’s new: a note or milestone needs a few words.</p>
          </div>

          <div class="field">
            <span class="field-label" id="story-photo-label">Photo <span class="field-optional">(optional)</span></span>
            <div class="story-photo-field">
              <img class="story-photo-preview" alt="Chosen photo" data-photo-preview hidden>
              <label class="btn btn-secondary btn-sm" for="story-photo"><?= icon('camera') ?> <span data-photo-button>Add a photo</span></label>
              <input class="visually-hidden" type="file" id="story-photo" name="photo" accept="image/jpeg,image/png"
                     aria-labelledby="story-photo-label" aria-describedby="story-photo-hint">
            </div>
            <p class="field-hint" id="story-photo-hint" data-photo-hint>JPG or PNG, up to 5 MB.</p>
          </div>

          <div class="field-row">
            <div class="field">
              <label class="field-label" for="story-status">Status <span class="field-optional">(optional)</span></label>
              <select class="input" id="story-status" name="status">
                <option value="">No status</option>
                <?php foreach (['thriving', 'stable', 'needs_attention', 'sick'] as $option): ?>
                  <option value="<?= e($option) ?>"><?= e(health_label($option)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label class="field-label" for="story-date">Date</label>
              <input class="input" type="date" id="story-date" name="date" value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>" required>
            </div>
          </div>
        </div>

        <div class="modal-foot">
          <button class="btn btn-secondary" formmethod="dialog" formnovalidate>Cancel</button>
          <button class="btn btn-primary" type="submit"><?= icon('plus') ?> Add to story</button>
        </div>
      </form>
    </dialog>

    <?php // Add a photo popup (CORE-17, UI half): choose or drag a JPG/PNG of up to 5 MB, see it, add it.
          // plant.js shows it first in the thumbnails and in the large frame. Stage F changes the page only;
          // stage B uploads it (finfo check, random name, resized to 1600px) and saves a plant_photos row.
          // plant.php?id=…&photo=1 opens it at once (the Add link without JavaScript). ?>
    <dialog class="modal" id="add-photo" aria-labelledby="add-photo-title"<?= get_text('photo') === '1' ? ' data-open-on-load' : '' ?>
            data-plant-name="<?= e($plant['nickname']) ?>">
      <form class="modal-form" id="photo-form" method="post" action="<?= e($page_url) ?>" enctype="multipart/form-data" novalidate>
        <div class="modal-head">
          <h2 class="modal-title" id="add-photo-title">Add a photo of <?= e($plant['nickname']) ?></h2>
          <button class="icon-btn" formmethod="dialog" formnovalidate aria-label="Close"><?= icon('x') ?></button>
        </div>

        <div class="modal-body photo-form-body">
          <?php // The <label> is the drop area: a click anywhere on it opens the file picker ?>
          <label class="dropzone photo-drop" for="new-photo" data-photo-drop>
            <img class="photo-drop-preview" alt="The chosen photo" data-photo-preview hidden>
            <span class="photo-drop-empty" data-photo-empty>
              <span class="dropzone-icon"><?= icon('image-up') ?></span>
              <span class="dropzone-title">Choose a photo</span>
              <span class="dropzone-text">Drag it here, browse, or use your phone camera</span>
            </span>
          </label>
          <input class="visually-hidden" type="file" id="new-photo" name="photo" accept="image/jpeg,image/png"
                 capture="environment" aria-describedby="new-photo-hint">
          <p class="field-hint" id="new-photo-hint" data-photo-hint>JPG or PNG, up to 5 MB.</p>

          <div class="field">
            <label class="field-label" for="new-photo-date">Taken on</label>
            <input class="input" type="date" id="new-photo-date" name="date" value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>">
          </div>
        </div>

        <div class="modal-foot">
          <button class="btn btn-secondary" formmethod="dialog" formnovalidate>Cancel</button>
          <button class="btn btn-primary" type="submit"><?= icon('camera') ?> Add photo</button>
        </div>
      </form>
    </dialog>

    <?php // What plant.js copies for a new entry: a care line, or a card (note, milestone). Filled with textContent. ?>
    <template id="story-care-template">
      <li class="story-entry story-entry-care">
        <p class="story-care"><span class="story-care-task"></span> <span class="story-care-date"></span> <span class="story-care-note"></span></p>
      </li>
    </template>
    <template id="story-card-template">
      <li class="story-entry">
        <p class="story-date"></p>
        <div class="story-card">
          <span class="story-milestone-icon" hidden><?= icon('sprout') ?></span>
          <div class="story-body"><p class="story-note"></p></div>
        </div>
      </li>
    </template>
    <p class="visually-hidden" role="status" data-story-status></p>

    <?php partial('reminder-dialog', [
        'action' => $page_url,
        'values' => $reminder_values,
        'errors' => $reminder_errors,
        'plants' => null,
        'plant'  => $plant,
        'open'   => get_text('reminder') === '1',
    ]); ?>
    <?php if ($toast): ?>
      <?php partial('reminder-toast', ['toast' => $toast, 'action' => $page_url]); ?>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
