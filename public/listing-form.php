<?php
// Create Listing (P12, wireframe p19): turns a plant from the member's garden into an exchange listing in a few clicks,
// with the privacy choices made up front (COM-06, UI half).
// listing-form.php starts a new listing. ?plant=43 starts it from Goldie (the "List for exchange" links of My Garden and
// the Plant Profile already open this address); ?plant=none lists something that is not in the garden, with its own photo.
// listing-form.php?id=7 edits listing 7 with the same form, filled in, plus "Mark as exchanged" and "Close listing"
// (p19, UX notes). Members only, and only the owner may edit.
// Stage F checks the form like stage B will, then moves on without saving. Stage B saves the exchange_listings row
// (and the photo, CORE-17) and opens the new listing at P11 (p19, element 7).

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mock-data.php';

// Stage F stand-in for require_login(): a visitor (?guest) is sent to Log in, exactly as stage B will do.
if (!$current_user) {
    header('Location: ' . url('login.php'));
    exit;
}

// ── Add or edit ─────────────────────────────────────────
// With ?id= the form edits that listing. Only its owner may, and only while it is open: anyone else, or a closed
// listing, gets "not found", like the plant form. Stage B checks ownership again when saving.
// get_text() and ctype_digit() let only a plain number through, as on Listing Detail.

$listing  = null;
$id_param = get_text('id');
if ($id_param !== '') {
    if (ctype_digit($id_param)) {
        foreach ($mock_listings as $row) {
            if ($row['id'] === (int) $id_param && $row['user_id'] === $current_user['id'] && $row['status'] === 'open') {
                $listing = $row;
                break;
            }
        }
    }
    if (!$listing) {
        http_response_code(404);
        $page_title = 'Listing not found';
        include __DIR__ . '/../includes/header.php';
        ?>
        <div class="container page">
          <section class="panel">
            <?php partial('empty-state', [
                'heading' => 'h1',   // the whole page: its one main heading
                'title'   => 'Listing not found',
                'text'    => 'This listing does not exist, is not yours to edit, or is already closed.',
                'actions' => [['label' => 'Go to My exchanges', 'href' => 'my-exchanges.php?tab=listings', 'icon' => 'repeat']],
            ]); ?>
          </section>
        </div>
        <?php
        include __DIR__ . '/../includes/footer.php';
        exit;
    }
}
$is_edit = $listing !== null;

// ── Which plant (p19, element 1) ────────────────────────
// $choice is the member's answer to "1. Which plant?": the id of one of their plants, 'none' for "Not in my garden",
// or null while nothing is picked. The picker is a row of links (?plant=43), so it works without JavaScript and the
// preview can show the chosen plant at once; the form carries the choice on in a hidden field when it is sent.
// An id that is not one of the member's own plants counts as nothing picked: nobody can list someone else's plant.

$my_plants   = array_column($mock_plants, null, 'id');
$choice_text = $_SERVER['REQUEST_METHOD'] === 'POST' ? post_text('plant') : get_text('plant');

$choice = null;
if ($choice_text === 'none') {
    $choice = 'none';
} elseif (ctype_digit($choice_text) && isset($my_plants[(int) $choice_text])) {
    $choice = (int) $choice_text;
} elseif ($is_edit) {
    // Editing without a ?plant= of its own: the plant the listing already comes from
    $choice = isset($my_plants[$listing['plant_id']]) ? $listing['plant_id'] : 'none';
}
$plant = is_int($choice) ? $my_plants[$choice] : null;

// Plants that are already on one of the member's open listings get a "Listed" note, so the same plant is not listed
// twice by mistake (the listing being edited does not count)
$listed_ids = [];
foreach ($mock_listings as $row) {
    if ($row['user_id'] === $current_user['id'] && $row['status'] === 'open' && $row['plant_id'] !== null
        && (!$is_edit || $row['id'] !== $listing['id'])) {
        $listed_ids[] = $row['plant_id'];
    }
}

// Addresses that keep the add or edit mode: listing-form.php?id=7&plant=48
$form_url = fn (array $params) => query_url('listing-form.php', array_merge($is_edit ? ['id' => $listing['id']] : [], $params));
// #which-plant brings the browser back to the picker after a click, instead of the top of the page
$pick_url = fn ($value) => $form_url(['plant' => $value]) . '#which-plant';

// ── The values the form starts with ─────────────────────
// Every field reads from $values, so the new form (defaults), the edit form (the listing's row) and a form sent back
// with errors (what was typed) share one piece of HTML.

$types       = ['swap' => 'Swap', 'free' => 'Free', 'either' => 'Either'];
$has_phone   = !empty($current_user['phone']);   // a phone that was never given cannot be shared
$title_limit = 100;
$text_limit  = 500;   // p19: "142 / 500"

$values = [
    'title'           => '',
    'type'            => 'swap',
    'looking_for'     => '',
    'description'     => '',
    'species'         => '',
    'size'            => '',
    'plant_condition' => '',
    'location'        => $current_user['location'],            // the member's town, the usual pick-up place
    'approx_area'     => $current_user['show_listing_area'],   // the default chosen in Settings (p22)
    'share_email'     => 1,
    'share_phone'     => 0,   // my choice: the phone number is shared only when the member ticks it
];
$title_suggested = false;

if ($is_edit) {
    $values = [
        'title'           => $listing['title'],
        'type'            => $listing['type'],
        'looking_for'     => $listing['looking_for'],
        'description'     => $listing['description'],
        'species'         => $listing['plant_id'] === null ? $listing['scientific_name'] : '',
        'size'            => $listing['size'] ?? '',
        'plant_condition' => $listing['plant_condition'] ?? '',
        'location'        => $listing['location'],
        'approx_area'     => $listing['approx_area'],
        'share_email'     => $listing['share_email'],
        'share_phone'     => $listing['share_phone'],
    ];
} elseif ($plant) {
    // "Title is suggested from the plant name and species, and can be changed" (p19, UX notes)
    $values['title'] = $plant['common_name'] . ' from ' . $plant['nickname'];
    $title_suggested = true;
}

// ── Publish (Post/Redirect/Get) ─────────────────────────
// The browser checks the fields first (required, maxlength); these checks repeat them, because the browser's checks
// can be switched off. Required (p19, UX notes): a plant or a photo, a title, a type and at least one contact method;
// the town too, because every listing card shows it. An error is printed under its field, and what was typed stays.
// Stage B, before anything else: check the CSRF token. Then check the photo with finfo (JPG/PNG, 5 MB), resize it to
// 1600 px, save the row, and open listing.php?id= of the new listing.

$errors = [];   // field name => message, printed under that field

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // \r\n becomes \n before counting, as on Listing Detail: the textarea counts a line break as one character
    $values = [
        'title'           => trim(post_text('title')),
        'type'            => post_text('type'),
        'looking_for'     => trim(post_text('looking_for')),
        'description'     => trim(str_replace("\r\n", "\n", post_text('description'))),
        'species'         => trim(post_text('species')),
        'size'            => trim(post_text('size')),
        'plant_condition' => trim(post_text('plant_condition')),
        'location'        => trim(post_text('location')),
        'approx_area'     => post_text('approx_area') === '1' ? 1 : 0,
        'share_email'     => post_text('share_email') === '1' ? 1 : 0,
        'share_phone'     => $has_phone && post_text('share_phone') === '1' ? 1 : 0,
    ];

    // A photo arrived when PHP reports no upload error for it. A hand-made photo[] makes 'error' a list: not a photo.
    $photo_sent = ($_FILES['photo']['error'] ?? null) === UPLOAD_ERR_OK;
    // A listing that already has its own photo (edited, not from the garden) does not need a new one
    $has_own_photo = $is_edit && $listing['plant_id'] === null;

    if ($choice === null) {
        $errors['plant'] = 'Choose a plant from your garden, or "Not in my garden".';
    } elseif ($choice === 'none' && !$photo_sent && !$has_own_photo) {
        $errors['photo'] = 'Add a photo, so people can see what you offer.';
    }
    if ($values['title'] === '') {
        $errors['title'] = 'Give your listing a title.';
    } elseif (mb_strlen($values['title']) > $title_limit) {
        $errors['title'] = 'Keep the title to ' . $title_limit . ' characters.';
    }
    if (!array_key_exists($values['type'], $types)) {
        $errors['type'] = 'Choose Swap, Free or Either.';
    }
    if (mb_strlen($values['description']) > $text_limit) {
        $errors['description'] = 'Keep the description to ' . $text_limit . ' characters.';
    }
    if ($values['location'] === '') {
        $errors['location'] = 'Add the town where the plant can be picked up.';
    }
    if (!$values['share_email'] && !$values['share_phone']) {
        $errors['share'] = 'Choose at least one way for interested people to contact you.';
    }

    if (!$errors) {
        // Stage F: nothing is saved. An edited listing opens its detail page; a new one has no page yet, so the member
        // goes to their listings in My exchanges.
        header('Location: ' . ($is_edit ? url('listing.php?id=' . $listing['id']) : url('my-exchanges.php?tab=listings')));
        exit;
    }
}

// The aria-describedby and aria-invalid of a field: its hint, and its error when it has one
$describe = function ($name, $hint_id = null) use ($errors) {
    $ids = array_filter([$hint_id, isset($errors[$name]) ? $name . '-error' : null]);
    return ($ids ? ' aria-describedby="' . e(implode(' ', $ids)) . '"' : '') . (isset($errors[$name]) ? ' aria-invalid="true"' : '');
};

// ── Live preview (p19, element 6) ───────────────────────
// The card exactly as it will appear on the Exchange page (P10), drawn by the same partial. Here it shows the chosen
// plant and what the form holds now; week 6 JavaScript updates it while the member types.
// A plant from the garden lends its photo; an edited listing keeps its own; nothing chosen shows the empty pot.

if ($plant) {
    $preview_photo = ($is_edit && $listing['plant_id'] === $plant['id']) ? $listing['photo'] : $plant['photo'];
} elseif ($is_edit && $listing['plant_id'] === null) {
    $preview_photo = $listing['photo'];
} else {
    $preview_photo = 'empty-garden.svg';
}
$preview_type = array_key_exists($values['type'], $types) ? $values['type'] : 'swap';

$preview = [
    'id'             => $is_edit ? $listing['id'] : 0,
    'type'           => $preview_type,
    'title'          => $values['title'] !== '' ? $values['title'] : 'Your listing title',
    'photo'          => $preview_photo,
    'location'       => $values['location'] !== '' ? $values['location'] : $current_user['location'],
    'distance_km'    => null,   // the owner needs no distance to their own town
    'created_at'     => $is_edit ? $listing['created_at'] : date('Y-m-d H:i:s'),
    // A free plant asks nothing in return: the same words as the free listings on the Exchange page. A swap whose
    // owner names nothing in particular is open to offers.
    'looking_for'    => $preview_type === 'free' ? 'nothing, free to a good home' : ($values['looking_for'] !== '' ? $values['looking_for'] : 'open to offers'),
    'interest_count' => $is_edit ? $listing['interest_count'] : 0,
    'username'       => $current_user['username'],
    'full_name'      => $current_user['full_name'],
    'avatar'         => $current_user['avatar'],
    'rating_avg'     => $current_user['rating_avg'],
    'rating_count'   => $current_user['rating_count'],
];

$back_url    = $is_edit ? url('listing.php?id=' . $listing['id']) : url('exchange.php');
$form_action = url('listing-form.php' . ($is_edit ? '?id=' . $listing['id'] : ''));

$page_title = $is_edit ? 'Edit listing' : 'List a plant';
$page_css   = 'listing-form.css';

include __DIR__ . '/../includes/header.php';
?>
<div class="container page">
  <a class="back-link" href="<?= e($back_url) ?>"><?= icon('arrow-left') ?> <?= $is_edit ? 'Back to the listing' : 'Back to Exchange' ?></a>
  <h1 class="form-title"><?= $is_edit ? 'Edit listing' : 'List a plant for exchange' ?></h1>
  <p class="form-intro">Share a plant or cuttings with the community. Your contact is only shown to people who are interested.</p>

  <?php if ($errors): ?>
    <?php // role="alert" makes a screen reader announce it as soon as the page opens ?>
    <p class="alert alert-danger form-errors" role="alert"><?= icon('triangle-alert') ?> <span>Please check <?= e(count_words(count($errors), 'field', 'fields')) ?> below.</span></p>
  <?php endif; ?>

  <?php // multipart/form-data: the only form encoding that can carry a file. The whole two-column layout is inside the
        // form, so Publish in the preview column sends every field. ?>
  <form class="listing-form" method="post" action="<?= e($form_action) ?>" enctype="multipart/form-data">
    <?php // Stage B: a hidden CSRF token goes here, in every POST form ?>
    <input type="hidden" name="plant" value="<?= e($choice ?? '') ?>">

    <div class="listing-form-grid">
      <div class="form-card listing-form-main">
        <?php // 1. Which plant? (p19, element 1) ?>
        <section id="which-plant" aria-labelledby="which-plant-title">
          <h2 class="form-card-title listing-step" id="which-plant-title">1. Which plant?</h2>
          <p class="field-hint listing-step-hint">Pick one from your garden to reuse its photos and species.</p>

          <ul class="pick-list">
            <?php foreach ($my_plants as $option): ?>
              <?php $is_chosen = $choice === $option['id']; ?>
              <li>
                <a class="pick-tile" href="<?= e($pick_url($option['id'])) ?>"<?= $is_chosen ? ' aria-current="true"' : '' ?>>
                  <span class="pick-photo">
                    <img src="<?= e(photo_url($option['photo'])) ?>" alt="" loading="lazy">
                    <?php if ($is_chosen): ?><span class="pick-check"><?= icon('check') ?></span><?php endif; ?>
                  </span>
                  <span class="pick-name"><?= e($option['nickname']) ?></span>
                  <?php if (in_array($option['id'], $listed_ids, true)): ?>
                    <span class="pick-note">Listed</span>
                  <?php endif; ?>
                </a>
              </li>
            <?php endforeach; ?>
            <li>
              <a class="pick-tile" href="<?= e($pick_url('none')) ?>"<?= $choice === 'none' ? ' aria-current="true"' : '' ?>>
                <span class="pick-photo pick-photo-none">
                  <?= icon('plus') ?>
                  <?php if ($choice === 'none'): ?><span class="pick-check"><?= icon('check') ?></span><?php endif; ?>
                </span>
                <span class="pick-name">Not in my garden</span>
              </a>
            </li>
          </ul>
          <?php if (isset($errors['plant'])): ?>
            <p class="field-error" id="plant-error" role="alert"><?= e($errors['plant']) ?></p>
          <?php endif; ?>

          <?php if ($plant): ?>
            <p class="pick-summary">
              <?= icon('leaf') ?>
              <span>
                Uses <?= e($plant['nickname']) ?>'s photo and species, <span class="scientific-name"><?= e($plant['scientific_name']) ?></span>.
                <a href="<?= e(url('plant.php?id=' . $plant['id'])) ?>">See the story<span class="visually-hidden"> of <?= e($plant['nickname']) ?></span></a>
              </span>
            </p>
          <?php elseif ($choice === 'none'): ?>
            <?php // Something that is not in the garden (seedlings, cuttings): its own photo and species ?>
            <div class="listing-own">
              <div class="field">
                <span class="field-label" id="photo-label">Photo</span>
                <?php if ($is_edit && $listing['plant_id'] === null): ?>
                  <div class="photo-current">
                    <img src="<?= e(photo_url($listing['photo'])) ?>" alt="Current photo of <?= e($listing['title']) ?>">
                    <label class="btn btn-secondary btn-sm photo-change" for="listing-photo"><?= icon('camera') ?> Change photo</label>
                  </div>
                <?php else: ?>
                  <label class="dropzone listing-dropzone" for="listing-photo">
                    <span class="dropzone-icon"><?= icon('image-up') ?></span>
                    <span class="dropzone-title">Add a photo of what you offer</span>
                    <span class="dropzone-text">Drag it here, browse, or use your phone camera</span>
                    <span class="btn btn-secondary btn-sm"><?= icon('camera') ?> Choose photo</span>
                  </label>
                <?php endif; ?>
                <input class="visually-hidden" type="file" id="listing-photo" name="photo" accept="image/jpeg,image/png"
                       capture="environment" aria-labelledby="photo-label"<?= $describe('photo', 'photo-hint') ?>>
                <p class="field-hint photo-hint" id="photo-hint"><?= icon('info') ?> JPG or PNG, up to 5 MB.</p>
                <?php if (isset($errors['photo'])): ?>
                  <span class="field-error" id="photo-error"><?= e($errors['photo']) ?></span>
                <?php endif; ?>
              </div>

              <?php // The same list of known species as the plant form: typing suggests matches, any name is allowed ?>
              <div class="field">
                <label class="field-label" for="species">Species <span class="field-optional">(optional)</span></label>
                <input class="input" type="text" id="species" name="species" value="<?= e($values['species']) ?>"
                       list="species-list" placeholder="Start typing, e.g. Tomato" maxlength="120" autocomplete="off">
                <datalist id="species-list">
                  <?php foreach ($mock_species as $species): ?>
                    <option value="<?= e($species['scientific_name']) ?>"><?= e($species['common_name']) ?></option>
                  <?php endforeach; ?>
                </datalist>
              </div>
            </div>
          <?php endif; ?>
        </section>

        <?php // 2. Listing details (p19, elements 2 and 3) ?>
        <section class="form-section" aria-labelledby="details-title">
          <h2 class="form-card-title listing-step" id="details-title">2. Listing details</h2>

          <div class="field">
            <label class="field-label" for="title">Title</label>
            <input class="input" type="text" id="title" name="title" value="<?= e($values['title']) ?>"
                   placeholder="Pothos 'Golden', 3 rooted cuttings" maxlength="<?= e($title_limit) ?>" required<?= $describe('title', $title_suggested ? 'title-hint' : null) ?>>
            <?php if ($title_suggested): ?>
              <span class="field-hint" id="title-hint">Suggested from <?= e($plant['nickname']) ?>'s name and species. Change it as you like.</span>
            <?php endif; ?>
            <?php if (isset($errors['title'])): ?>
              <span class="field-error" id="title-error"><?= e($errors['title']) ?></span>
            <?php endif; ?>
          </div>

          <div class="field-row">
            <?php // Exchange type (p19, element 2): three radio buttons drawn as one control ?>
            <fieldset class="field form-fieldset"<?= isset($errors['type']) ? ' aria-describedby="type-error"' : '' ?>>
              <legend class="field-label">Exchange type</legend>
              <div class="segmented">
                <?php foreach ($types as $key => $label): ?>
                  <input type="radio" id="type-<?= e($key) ?>" name="type" value="<?= e($key) ?>"<?= $key === $values['type'] ? ' checked' : '' ?>>
                  <label for="type-<?= e($key) ?>"><?php if ($key !== 'either'): ?><?= icon(listing_type($key)['icon']) ?><?php endif; ?> <?= e($label) ?></label>
                <?php endforeach; ?>
              </div>
              <?php if (isset($errors['type'])): ?>
                <span class="field-error" id="type-error"><?= e($errors['type']) ?></span>
              <?php endif; ?>
            </fieldset>

            <?php // Looking for (p19, element 3): only for Swap and Either. listing-form.css hides it while Free is
                  // chosen, with no JavaScript; stage B ignores it for a free listing. ?>
            <div class="field looking-for-field">
              <label class="field-label" for="looking-for">Looking for <span class="field-optional">(Swap and Either only)</span></label>
              <input class="input" type="text" id="looking-for" name="looking_for" value="<?= e($values['looking_for']) ?>"
                     placeholder="Succulents or a snake plant pup" maxlength="150">
            </div>
          </div>

          <div class="field">
            <label class="field-label" for="description">Description <span class="field-optional">(optional)</span></label>
            <textarea class="input" id="description" name="description" rows="4" maxlength="<?= e($text_limit) ?>"
                      placeholder="How big is it, how healthy, is it rooted or potted?"<?= $describe('description', 'description-count') ?>><?= e($values['description']) ?></textarea>
            <?php // Counted once here, as on Settings; week 6 JavaScript updates the count while typing ?>
            <span class="field-hint listing-count" id="description-count"><?= e(mb_strlen($values['description'])) ?> / <?= e($text_limit) ?></span>
            <?php if (isset($errors['description'])): ?>
              <span class="field-error" id="description-error"><?= e($errors['description']) ?></span>
            <?php endif; ?>
          </div>

          <?php // Size and condition fill the details list of Listing Detail (P11, p18). Not drawn on p19: my addition,
                // so the proposed size and plant_condition columns have a place where the owner types them. ?>
          <div class="field-row">
            <div class="field">
              <label class="field-label" for="size">Size <span class="field-optional">(optional)</span></label>
              <input class="input" type="text" id="size" name="size" value="<?= e($values['size']) ?>"
                     placeholder="3 cuttings, 15 to 20 cm" maxlength="60">
            </div>
            <div class="field">
              <label class="field-label" for="plant-condition">Condition <span class="field-optional">(optional)</span></label>
              <input class="input" type="text" id="plant-condition" name="plant_condition" value="<?= e($values['plant_condition']) ?>"
                     placeholder="Healthy, rooted in water" maxlength="60">
            </div>
          </div>
        </section>

        <?php // 3. Location and contact (p19, elements 4 and 5) ?>
        <section class="form-section" aria-labelledby="contact-title">
          <h2 class="form-card-title listing-step" id="contact-title">3. Location and contact</h2>

          <div class="field-row">
            <div class="field">
              <label class="field-label" for="location">Town</label>
              <div class="input-icon">
                <?= icon('map-pin') ?>
                <input class="input" type="text" id="location" name="location" value="<?= e($values['location']) ?>"
                       autocomplete="address-level2" maxlength="100" required<?= $describe('location') ?>>
              </div>
              <?php if (isset($errors['location'])): ?>
                <span class="field-error" id="location-error"><?= e($errors['location']) ?></span>
              <?php endif; ?>
              <label class="check">
                <input type="checkbox" name="approx_area" value="1"<?= $values['approx_area'] ? ' checked' : '' ?>>
                <span>Show approximate area only</span>
              </label>
            </div>

            <?php // Contact sharing (p19, element 5): exactly which details an interested member will see ?>
            <fieldset class="field form-fieldset" aria-describedby="share-hint<?= isset($errors['share']) ? ' share-error' : '' ?>">
              <legend class="field-label">Share with interested people</legend>
              <div class="share-options">
                <label class="check">
                  <input type="checkbox" name="share_email" value="1"<?= $values['share_email'] ? ' checked' : '' ?>>
                  <span>Email <span class="share-value">· <?= e($current_user['email']) ?></span></span>
                </label>
                <?php if ($has_phone): ?>
                  <label class="check">
                    <input type="checkbox" name="share_phone" value="1"<?= $values['share_phone'] ? ' checked' : '' ?>>
                    <span>Phone <span class="share-value">· <?= e($current_user['phone']) ?></span></span>
                  </label>
                <?php else: ?>
                  <label class="check is-disabled">
                    <input type="checkbox" name="share_phone" value="1" disabled>
                    <span>Phone <span class="share-value">· <a href="<?= e(url('settings.php#profile')) ?>">add one in Settings</a></span></span>
                  </label>
                <?php endif; ?>
              </div>
              <span class="field-hint" id="share-hint">Shown only after someone says "I'm interested".</span>
              <?php if (isset($errors['share'])): ?>
                <span class="field-error" id="share-error"><?= e($errors['share']) ?></span>
              <?php endif; ?>
            </fieldset>
          </div>
        </section>
      </div>

      <?php // Preview and Publish (p19, elements 6 and 7). After the form on a phone; beside it on a desktop, where it
            // stays in view while the form scrolls. ?>
      <aside class="listing-form-side" aria-labelledby="preview-title">
        <h2 class="label listing-preview-label" id="preview-title">Preview</h2>
        <?php partial('listing-card', ['listing' => $preview, 'preview' => true]); ?>
        <p class="listing-form-privacy"><?= icon('lock') ?> Contact details never appear on the card.</p>

        <button class="btn btn-primary btn-lg listing-form-submit" type="submit"><?= $is_edit ? 'Save changes' : 'Publish listing' ?></button>
        <a class="btn btn-ghost listing-form-cancel" href="<?= e($back_url) ?>">Cancel</a>

        <?php if ($is_edit): ?>
          <?php // Edit mode only (p19, UX notes). Mark as exchanged needs the person it went to, so it opens the
                // interest table of My exchanges (P13). Close listing is destructive, so week 5 asks first (S6). ?>
          <div class="listing-form-manage">
            <a class="btn btn-secondary" href="<?= e(url('my-exchanges.php?tab=received&listing=' . $listing['id'])) ?>"><?= icon('check') ?> Mark as exchanged</a>
            <?php // Stage F: does nothing yet. Week 5 opens the confirmation that names the listing; stage B closes it. ?>
            <button type="button" class="btn btn-ghost listing-form-close"><?= icon('x') ?> Close listing</button>
          </div>
        <?php endif; ?>
      </aside>
    </div>
  </form>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
