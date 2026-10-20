<?php
// Listing Detail (P11, wireframe p18): everything needed to decide on a swap, then the owner's contact details once
// the member says "I'm interested" (COM-08, UI half). listing.php?id=7 shows Nimali's pothos cuttings.
// Public · interest needs login: anyone may read a listing; a visitor's button leads to Log in and back here.
// State A (before interest): a short note and "I'm interested". State B (after): the owner's contact details, only the
// fields the owner chose to share. The owner sees their own listing instead, and a closed listing takes no interest.
// Reads $mock_listings, $mock_listing_photos, $mock_listing_interests, $mock_user_contacts, $mock_users and the plants.

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mock-data.php';

// ── Which listing ───────────────────────────────────────
// Stage B: SELECT exchange_listings.*, users.username … FROM exchange_listings JOIN users … WHERE exchange_listings.id = ?
// get_text() turns a hand-made id[]=1 into '', and ctype_digit() lets digits only through, so "abc", "-1" and "1e2"
// are simply not found.

$id_param = get_text('id');
$listing  = null;
if (ctype_digit($id_param)) {
    foreach ($mock_listings as $row) {
        if ($row['id'] === (int) $id_param) {
            $listing = $row;
            break;
        }
    }
}

if (!$listing) {
    http_response_code(404);   // tells browsers and search engines that the page does not exist
    $page_title = 'Listing not found';
    include __DIR__ . '/../includes/header.php';
    ?>
    <div class="container page">
      <section class="panel">
        <?php partial('empty-state', [
            'title'   => 'Listing not found',
            'text'    => 'This listing does not exist, or its owner has removed it.',
            'actions' => [['label' => 'Browse the exchange', 'href' => 'exchange.php', 'icon' => 'repeat']],
        ]); ?>
      </section>
    </div>
    <?php
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$is_owner = $current_user && $listing['user_id'] === $current_user['id'];
$is_open  = $listing['status'] === 'open';

// The owner as other people see them: public columns only, joined from users (as on the Plant Profile)
$owner       = $mock_users[$listing['user_id']];
$owner_first = first_name($owner['full_name']);

// The viewer's own interest row: state B when it exists and is not withdrawn.
// Stage B: SELECT … FROM listing_interests WHERE listing_id = ? AND user_id = ?
$my_row = null;
if ($current_user && !$is_owner) {
    foreach ($mock_listing_interests as $interest) {
        if ($interest['listing_id'] === $listing['id'] && $interest['user_id'] === $current_user['id']) {
            $my_row = $interest;
        }
    }
}
$my_interest = ($my_row && $my_row['status'] !== 'withdrawn') ? $my_row : null;

// ── I'm interested · Withdraw interest ──────────────────
// Post/Redirect/Get: the form posts to this page, the change is made, then the browser is sent back to the listing's
// address. A reload repeats only that GET, never the POST, so an interest is never sent twice.
// Stage B, before anything else: check the CSRF token. The checks below stay, because a hand-made request can skip the
// form: only a member who is not the owner can act, only on an open listing, and only once.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $back = 'listing.php?id=' . $listing['id'];

    if (!$current_user) {
        // A visitor's request, e.g. a page left open after logging out: log in first, then come back here
        header('Location: ' . query_url('login.php', ['next' => $back]));
        exit;
    }

    $action = post_text('action');
    if ($is_open && !$is_owner) {
        if ($action === 'interest' && !$my_interest) {
            // The note is optional and kept as typed (at most 300 letters); e() escapes it wherever it is shown
            $note = mb_substr(trim(post_text('note')), 0, 300);
            mock_send_interest($listing['id'], $current_user['id'], $note === '' ? null : $note);
        } elseif ($action === 'withdraw' && $my_interest) {
            mock_withdraw_interest($my_interest);
            $back .= '&withdrawn=1';   // shows "Interest withdrawn" once, like Settings' ?saved=
        }
    }

    header('Location: ' . url($back) . '#interest');   // #interest scrolls back down to the card on a phone
    exit;
}

// ── Photo gallery (p18, element 1) ──────────────────────
// The card photo first, then the extra photos of the proposed listing_photos table (stage B: ORDER BY created_at).
// Without JavaScript a thumbnail is a link to the same page with ?photo=2, which shows that photo large.
// Any other value (photo=99, photo=abc, photo[]=1) shows the first photo.

$photos = [$listing['photo']];
foreach ($mock_listing_photos as $photo) {
    if ($photo['listing_id'] === $listing['id']) {
        $photos[] = $photo['path'];
    }
}
$photo_count  = count($photos);
$photo_param  = get_text('photo');
$photo_number = (ctype_digit($photo_param) && (int) $photo_param >= 1 && (int) $photo_param <= $photo_count)
    ? (int) $photo_param
    : 1;

/** Address of one photo of this listing. Photo 1 needs no ?photo=, so the plain address stays the only one for it. */
$photo_url = fn ($number) => query_url('listing.php', ['id' => $listing['id'], 'photo' => $number === 1 ? '' : $number]) . '#gallery';

// ── Comes from (p18, element 3) ─────────────────────────
// A listing made from a garden plant links to that plant's story, which proves its history. A private plant is its
// owner's business, so other people get no "Comes from" line at all. Stage B: the LEFT JOIN on plants adds
// AND (plants.is_public = 1 OR plants.user_id = ?), so a private plant is never even loaded for them.

$source_plant = null;
if ($listing['plant_id'] !== null) {
    foreach (array_merge($mock_plants, $mock_community_plants) as $plant) {
        if ($plant['id'] === $listing['plant_id'] && ($plant['is_public'] || $is_owner)) {
            $source_plant = $plant;
        }
    }
}

// ── Contact details (p18, element 6) ────────────────────

/**
 * The contact details an owner chose to share on a listing (share_email, share_phone on the listing form, P12).
 * A field that is not shared is null, and so is a phone the owner does not have.
 */
function shared_contact($listing, $contact)
{
    return [
        'email' => $listing['share_email'] ? $contact['email'] : null,
        'phone' => $listing['share_phone'] ? $contact['phone'] : null,
    ];
}

// State B only: a live interest in an open listing. Stage B fetches them in one query that joins listing_interests,
// so without an interest it returns no row at all:
//   SELECT IF(l.share_email, u.email, NULL) AS email, IF(l.share_phone, u.phone, NULL) AS phone
//   FROM exchange_listings l JOIN users u ON u.id = l.user_id
//   JOIN listing_interests i ON i.listing_id = l.id AND i.user_id = ? AND i.status <> 'withdrawn'
//   WHERE l.id = ? AND l.status = 'open'
$contact = ($my_interest && $is_open) ? shared_contact($listing, $mock_user_contacts[$listing['user_id']]) : null;

// The owner sees which of their own details interested members will get (their own data, so nothing is revealed)
$own_contact = ($is_owner && $is_open) ? shared_contact($listing, $mock_user_contacts[$listing['user_id']]) : null;

/** A phone number as a tel: address keeps only the digits and the +: "+94 70 000 5678" gives "tel:+94700005678". */
function tel_href($phone)
{
    return 'tel:' . preg_replace('/[^0-9+]/', '', $phone);
}

// State A names the shared fields, never their values: "You will see Ruwan's email and phone number."
$shared_words = implode(' and ', array_keys(array_filter([
    'email'        => $listing['share_email'],
    'phone number' => $listing['share_phone'],
])));
if ($shared_words === '') {
    $shared_words = 'contact details';   // the listing form requires one; this only guards against an old row
}

// ── Words for the page ──────────────────────────────────

$type          = listing_type($listing['type']);
$posted        = format_day($listing['created_at']);
$show_distance = !$is_owner && $listing['distance_km'] !== null;   // visitors have no town; the owner needs no distance
$arrange       = $listing['type'] === 'free' ? 'the pick-up' : 'the swap';
$owner_url     = url('user.php?u=' . urlencode($owner['username']));
$login_url     = query_url('login.php', ['next' => 'listing.php?id=' . $listing['id']]);
$form_action   = url('listing.php?id=' . $listing['id']);
$interest_text = count_words($listing['interest_count'], 'person is interested', 'people are interested');

// "Interest withdrawn" after the redirect above, only when the viewer's row really is withdrawn: a hand-made
// ?withdrawn=1 on any other listing shows nothing
$just_withdrawn = get_text('withdrawn') === '1' && $is_open && $my_row && $my_row['status'] === 'withdrawn';

// Who the owner exchanged a closed listing with (exchanged_with), told to the owner only
$exchanged_with = (!$is_open && $is_owner && $listing['exchanged_with'] !== null)
    ? ($mock_users[$listing['exchanged_with']] ?? null)
    : null;

$page_title = $listing['title'];
$page_css   = 'listing.css';

include __DIR__ . '/../includes/header.php';
?>
<div class="container page">
  <nav aria-label="Breadcrumb">
    <ol class="breadcrumb listing-breadcrumb">
      <li><a href="<?= e(url('exchange.php')) ?>">Exchange</a></li>
      <li><span aria-current="page"><?= e($listing['title']) ?></span></li>
    </ol>
  </nav>

  <?php // Four parts in reading order: photos, then the summary and interest card, then About, then the owner and tips
        // cards. A phone shows them in this order, one under the other; a desktop puts them in two columns (listing.css). ?>
  <div class="listing-layout">
    <?php // Photo gallery (p18, element 1) ?>
    <div class="listing-gallery" id="gallery">
      <img class="listing-photo" src="<?= e(photo_url($photos[$photo_number - 1])) ?>"
           alt="<?= $photo_count > 1 ? 'Photo ' . e($photo_number) . ' of ' . e($photo_count) . ': ' : 'Photo of ' ?><?= e($listing['title']) ?>">
      <?php if ($photo_count > 1): ?>
        <ul class="listing-thumbs" aria-label="All photos">
          <?php foreach ($photos as $index => $path): ?>
            <?php $number = $index + 1; ?>
            <li>
              <a class="listing-thumb" href="<?= e($photo_url($number)) ?>"<?= $number === $photo_number ? ' aria-current="true"' : '' ?>>
                <img src="<?= e(photo_url($path)) ?>" alt="Show photo <?= e($number) ?> of <?= e($photo_count) ?>" loading="lazy">
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <div class="listing-side listing-side-top">
      <?php // Listing summary (p18, elements 2 and 4) ?>
      <section class="panel listing-summary">
        <p class="listing-summary-meta">
          <span class="badge badge-type-<?= e($listing['type']) ?>"><?= icon($type['icon']) ?> <?= e($type['label']) ?></span>
          <?php if (!$is_open): ?>
            <span class="badge badge-neutral">Closed</span>
          <?php endif; ?>
          <span>Posted <?= e($posted) ?><?php if ($listing['interest_count'] > 0): ?> · <?= e($listing['interest_count']) ?> interested<?php endif; ?></span>
        </p>
        <h1 class="listing-title"><?= e($listing['title']) ?></h1>
        <p class="listing-place">
          <?= icon('map-pin') ?>
          <span><?= e($listing['location']) ?><?php if ($show_distance): ?> · about <?= e($listing['distance_km']) ?> km from you<?php endif; ?></span>
        </p>
        <div class="listing-wants">
          <p class="label">Looking for</p>
          <p><?= e(ucfirst($listing['looking_for'])) ?></p>
        </div>
      </section>

      <?php // Interest and contact reveal (p18, element 6): one card, five situations ?>
      <section class="panel listing-interest<?= $my_interest && $is_open ? ' is-sent' : '' ?>" id="interest" aria-labelledby="interest-title">
        <?php if (!$is_open): ?>
          <?php // Closed or exchanged: no form and no contact details ?>
          <h2 class="panel-title" id="interest-title"><?= icon('lock') ?> This listing is closed</h2>
          <?php if ($is_owner): ?>
            <p class="listing-interest-text">
              <?= $exchanged_with ? 'You exchanged this plant with ' . e($exchanged_with['full_name']) . '.' : 'You closed this listing.' ?>
            </p>
            <a class="btn btn-primary listing-interest-btn" href="<?= e(url('my-exchanges.php')) ?>"><?= icon('repeat') ?> Go to My exchanges</a>
          <?php else: ?>
            <p class="listing-interest-text">This plant has found a new home, so the listing no longer takes interest.</p>
            <a class="btn btn-primary listing-interest-btn" href="<?= e(url('exchange.php')) ?>"><?= icon('repeat') ?> Browse the exchange</a>
          <?php endif; ?>

        <?php elseif ($is_owner): ?>
          <?php // The owner's own listing: no interest form. Requests are answered in My Exchanges (P13). ?>
          <h2 class="panel-title" id="interest-title">This is your listing</h2>
          <p class="listing-interest-text">
            <?= $listing['interest_count'] > 0 ? e($interest_text) . '.' : 'No one is interested yet.' ?>
          </p>
          <p class="listing-shared-intro">Interested members see your:</p>
          <ul class="listing-contact">
            <?php if ($own_contact['email']): ?>
              <li><span class="listing-contact-item"><?= icon('mail') ?> <?= e($own_contact['email']) ?></span></li>
            <?php endif; ?>
            <?php if ($own_contact['phone']): ?>
              <li><span class="listing-contact-item"><?= icon('phone') ?> <?= e($own_contact['phone']) ?></span></li>
            <?php endif; ?>
          </ul>
          <div class="listing-owner-actions">
            <a class="btn btn-primary" href="<?= e(url('my-exchanges.php?listing=' . $listing['id'])) ?>"><?= icon('repeat') ?> Manage in My exchanges</a>
            <a class="btn btn-secondary" href="<?= e(url('listing-form.php?id=' . $listing['id'])) ?>"><?= icon('pencil') ?> Edit listing</a>
          </div>

        <?php elseif (!$current_user): ?>
          <?php // A visitor: showing interest needs an account. Log in brings them straight back to this listing. ?>
          <h2 class="panel-title" id="interest-title">Interested in this plant?</h2>
          <p class="listing-interest-text">Log in to tell <?= e($owner_first) ?>. You will then see <?= e($owner_first) ?>'s <?= e($shared_words) ?>.</p>
          <a class="btn btn-primary listing-interest-btn" href="<?= e($login_url) ?>">Log in to show interest</a>
          <p class="listing-signup">New to PlantFolio? <a href="<?= e(url('register.php')) ?>">Create a free account</a></p>

        <?php elseif ($my_interest): ?>
          <?php // State B: interest sent, so the details the owner chose to share are shown, and only those ?>
          <h2 class="visually-hidden" id="interest-title">Your interest</h2>
          <p class="alert alert-success listing-sent"><?= icon('check') ?> <span>Interest sent to <?= e($owner_first) ?> · <?= e(format_day($my_interest['created_at'])) ?></span></p>
          <p class="listing-shared-intro">Contact <?= e($owner_first) ?> to arrange <?= e($arrange) ?></p>
          <ul class="listing-contact">
            <?php if ($contact['email']): ?>
              <li><a class="listing-contact-item" href="mailto:<?= e($contact['email']) ?>"><?= icon('mail') ?> <?= e($contact['email']) ?></a></li>
            <?php endif; ?>
            <?php if ($contact['phone']): ?>
              <li><a class="listing-contact-item" href="<?= e(tel_href($contact['phone'])) ?>"><?= icon('phone') ?> <?= e($contact['phone']) ?></a></li>
            <?php endif; ?>
          </ul>
          <?php if ($my_interest['message'] !== null): ?>
            <p class="listing-note"><span class="listing-note-label">Your note</span> <span class="listing-note-text"><?= e($my_interest['message']) ?></span></p>
          <?php endif; ?>
          <div class="listing-interest-actions">
            <button type="button" class="btn btn-secondary" disabled><?= icon('check') ?> Interest sent</button>
            <form method="post" action="<?= e($form_action) ?>">
              <?php // Stage B: a hidden CSRF token goes here, in every POST form ?>
              <input type="hidden" name="action" value="withdraw">
              <button type="submit" class="btn btn-ghost listing-withdraw">Withdraw interest</button>
            </form>
          </div>

        <?php else: ?>
          <?php // State A: before interest. The member's own details are not shared: the owner sees only the request. ?>
          <h2 class="visually-hidden" id="interest-title">Show interest</h2>
          <?php if ($just_withdrawn): ?>
            <p class="alert listing-withdrawn" role="status"><?= icon('info') ?> <span>Interest withdrawn. <?= e($owner_first) ?> no longer sees your request.</span></p>
          <?php endif; ?>
          <form method="post" action="<?= e($form_action) ?>">
            <?php // Stage B: a hidden CSRF token goes here, in every POST form ?>
            <input type="hidden" name="action" value="interest">
            <div class="field">
              <label class="field-label" for="interest-note">Add a short note <span class="field-optional">(optional)</span></label>
              <textarea class="input" id="interest-note" name="note" rows="3" maxlength="300" aria-describedby="interest-note-hint"
                        placeholder="Hi <?= e($owner_first) ?>, …"></textarea>
              <p class="field-hint" id="interest-note-hint">Up to 300 characters. <?= e($owner_first) ?> sees it with your request.</p>
            </div>
            <button type="submit" class="btn btn-primary listing-interest-btn">I'm interested</button>
          </form>
          <p class="listing-privacy"><?= icon('lock') ?> <span>Your contact is not shared. You will see <?= e($owner_first) ?>'s <?= e($shared_words) ?>.</span></p>
        <?php endif; ?>
      </section>
    </div>

    <section class="listing-about" aria-labelledby="about-title">
      <h2 class="listing-about-title" id="about-title">About this plant</h2>
      <p class="listing-description"><?= e($listing['description']) ?></p>
      <dl class="listing-details">
        <div>
          <dt>Species</dt>
          <dd>
            <?= e($listing['common_name']) ?>
            <?php // "Aloe vera (Aloe vera)" says nothing twice: the scientific name only when it differs ?>
            <?php if ($listing['scientific_name'] !== $listing['common_name']): ?>
              <span class="scientific-name">(<?= e($listing['scientific_name']) ?>)</span>
            <?php endif; ?>
          </dd>
        </div>
        <?php if ($listing['size'] !== null): ?>
          <div><dt>Size</dt><dd><?= e($listing['size']) ?></dd></div>
        <?php endif; ?>
        <?php if ($listing['plant_condition'] !== null): ?>
          <div><dt>Condition</dt><dd><?= e($listing['plant_condition']) ?></dd></div>
        <?php endif; ?>
        <?php if ($source_plant): ?>
          <?php // Link to the plant's story (p18, element 3). "See her story" on p18: no he or she in page text. ?>
          <div>
            <dt>Comes from</dt>
            <dd>
              <?= e($source_plant['nickname']) ?> ·
              <a class="listing-story-link" href="<?= e(url('plant.php?id=' . $source_plant['id'])) ?>">See the story<span class="visually-hidden"> of <?= e($source_plant['nickname']) ?></span></a>
            </dd>
          </div>
        <?php endif; ?>
      </dl>
    </section>

    <div class="listing-side listing-side-bottom">
      <?php // Owner trust card (p18, element 5). Never contact details: those are only in the interest card, after interest. ?>
      <aside class="panel listing-owner" aria-labelledby="owner-title">
        <h2 class="label" id="owner-title">Offered by</h2>
        <div class="listing-owner-member">
          <?php partial('avatar', ['member' => $owner]); ?>
          <span>
            <strong><?= e($owner['full_name']) ?></strong>
            <span class="text-small">@<?= e($owner['username']) ?> · <?= e($owner['location']) ?></span>
          </span>
        </div>
        <p class="listing-owner-stats">
          <?php partial('rating', ['rating' => $owner]); ?>
          <span>· <?= e(count_words($owner['swap_count'], 'swap', 'swaps')) ?> · since <?= e(date('M Y', strtotime($owner['created_at']))) ?></span>
        </p>
        <a class="btn btn-secondary listing-owner-btn" href="<?= e($owner_url) ?>">View profile</a>
      </aside>

      <?php // Safe swapping tips (p18, element 7) ?>
      <aside class="panel listing-tips" aria-labelledby="tips-title">
        <h2 class="panel-title" id="tips-title"><?= icon('shield') ?> Safe swapping</h2>
        <ul class="listing-tips-list">
          <li><?= icon('check') ?> Meet in a public place</li>
          <li><?= icon('check') ?> Check the plant before you swap</li>
          <li><?= icon('check') ?> Rate each other afterwards</li>
        </ul>
        <a class="listing-tips-link" href="<?= e(url('about.php#guidelines')) ?>">Read the community guidelines</a>
      </aside>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
