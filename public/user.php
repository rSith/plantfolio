<?php
// Public User Profile (P14, wireframe p21): a member's portfolio of public plants and collections and their trust
// record as a trader: identity, stats, tabs, collection collages, rating breakdown and latest review (COM-05, COM-13, UI half).
// Public: anyone may open user.php?u=nimali.grows. A logged-in member with no ?u sees their own profile.
// Contact details are never shown here: contact happens through listings only (p21, UX notes).
// Reads $mock_users, the collections, $mock_public_plants, $mock_listings and the ratings from mock data.

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mock-data.php';

// ── Whose profile ───────────────────────────────────────
// user.php?u=nimali.grows&tab=plants&collection=1. Only text is accepted from the address, and only valid UTF-8, as on
// Explore: a hand-made u[]=x or u=%FF counts as "not given" instead of causing a PHP error or a blank name.
$get_text = fn ($name) => (is_string($_GET[$name] ?? null) && mb_check_encoding($_GET[$name], 'UTF-8')) ? $_GET[$name] : '';

// Usernames are lowercase and the @ belongs to a mention, not to the name, so "@Nimali.Grows" finds nimali.grows
$asked_for = mb_strtolower(ltrim(trim($get_text('u')), '@'));
if ($asked_for === '' && $current_user) {
    $asked_for = $current_user['username'];   // nothing in the address: a member sees their own profile
}

// Stage B: one prepared query, SELECT id, username, full_name, avatar, bio, location, created_at … FROM users
// WHERE username = ?, plus the public counts. email and phone are not even selected, so they cannot leak.
$member = null;
foreach ($mock_users as $row) {
    if ($row['username'] === $asked_for) {
        $member = $row;
        break;
    }
}

if (!$member) {
    http_response_code(404);   // tells browsers and search engines that the page does not exist
    $shown_name = mb_strlen($asked_for) > 40 ? mb_substr($asked_for, 0, 40) . '…' : $asked_for;
    $page_title = 'Member not found';
    $nav_area   = 'explore';   // looking for a member is part of Explore
    include __DIR__ . '/../includes/header.php';
    ?>
    <div class="container page">
      <section class="panel">
        <?php partial('empty-state', [
            'title'   => 'Member not found',
            'text'    => $asked_for === ''
                ? 'The address does not say which member to show. You can find people on Explore.'
                : 'Nobody is called @' . $shown_name . '. Check the spelling, or search for the member on Explore.',
            'actions' => [['label' => 'Find people', 'url' => query_url('explore.php', ['tab' => 'people']), 'icon' => 'search']],
        ]); ?>
      </section>
    </div>
    <?php
    include __DIR__ . '/../includes/footer.php';
    exit;
}

$is_owner = $current_user && $member['id'] === $current_user['id'];

// ── What the profile shows ──────────────────────────────
// Public things only, for the owner too: p21 is the profile "as another member sees it", and the owner's note says so.
// Each list is one table filtered by user_id = ? in stage B.

// Collections: public ones only (stage B: AND is_public = 1). Nimali's and other members' rows are two mock lists.
$collections = array_values(array_filter(
    array_merge($mock_collections, $mock_community_collections),
    fn ($collection) => $collection['user_id'] === $member['id'] && $collection['is_public']
));

// Plants: $mock_public_plants holds public plants only. The most recently updated come first: a living portfolio.
$plants = array_values(array_filter($mock_public_plants, fn ($plant) => $plant['user_id'] === $member['id']));
usort($plants, fn ($a, $b) => $b['last_logged_at'] <=> $a['last_logged_at']);

// Listings: open ones, newest first. Closed and exchanged listings are history, not offers (p17, UX notes).
$listings = array_values(array_filter(
    $mock_listings,
    fn ($listing) => $listing['user_id'] === $member['id'] && $listing['status'] === 'open'
));
usort($listings, fn ($a, $b) => $b['created_at'] <=> $a['created_at']);

// Written reviews, newest first; the first is the "Latest review" of p21 (element 7)
$reviews = array_values(array_filter($mock_user_ratings, fn ($rating) => $rating['rated_id'] === $member['id']));
usort($reviews, fn ($a, $b) => $b['created_at'] <=> $a['created_at']);
$latest_review = $reviews[0] ?? null;

// The rating breakdown (p21, element 6). The GROUP BY gives no row for a score nobody gave, so every score starts at 0.
$score_counts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
foreach ($mock_rating_counts as $row) {
    if ($row['rated_id'] === $member['id']) {
        $score_counts[$row['score']] = $row['rating_count'];
    }
}
$rating_count = $member['rating_count'];
$has_ratings  = $rating_count > 0;

// ── Tabs (p21, element 4) ───────────────────────────────
// Links with ?tab=, like the Plant Profile, so each tab has an address and works without JavaScript (week 5 adds the
// shared tab script). The #tab-plants at the end points at the label inside that tab: the browser scrolls the page
// down to the tabs, and on a phone also scrolls the sideways tab row until the chosen tab is in view.
$listing_count = count($listings);
$tabs = [
    'collections' => ['label' => 'Collections', 'count' => $member['collection_count']],
    'plants'      => ['label' => 'Plants',      'count' => $member['plant_count']],
    'listings'    => ['label' => 'Listings',    'count' => $listing_count],
    'ratings'     => ['label' => 'Ratings',     'count' => $rating_count],
];
$tab = $get_text('tab');
if (!array_key_exists($tab, $tabs)) {
    $tab = 'collections';
}

/** Address of one tab of this profile; Collections, the first tab, needs no tab= in the address. */
$tab_url = fn ($key, array $more = []) => query_url('user.php', array_merge(
    ['u' => $member['username'], 'tab' => $key === 'collections' ? '' : $key],
    $more
)) . '#tab-' . $key;

// A collection card opens the Plants tab with only that collection's plants. Only this member's public collections
// count: any other id, or a private collection's id, shows every public plant instead.
$collection_filter = null;
$collection_param  = $get_text('collection');
if ($tab === 'plants' && ctype_digit($collection_param)) {
    foreach ($collections as $collection) {
        if ($collection['id'] === (int) $collection_param) {
            $collection_filter = $collection;
        }
    }
}
if ($collection_filter) {
    $plants = array_values(array_filter($plants, fn ($plant) => $plant['collection_id'] === $collection_filter['id']));
}

// ── Words for the page ──────────────────────────────────

$handle       = '@' . $member['username'];
$member_since = date('M Y', strtotime($member['created_at']));

/** "1 plant" or "13 plants": the number and the right form of the word. */
function count_words($count, $singular, $plural)
{
    return $count . ' ' . ($count === 1 ? $singular : $plural);
}

/** A review's date: "14 Sep" this year, "8 Mar 2025" in an earlier year, so an old review never looks recent. */
function review_date($datetime)
{
    $time = strtotime($datetime);

    return date('Y', $time) === date('Y') ? date('j M', $time) : date('j M Y', $time);
}

// The owner's note: what other people do not see. Their own pages count everything ($current_user); this page counts
// only public things ($member), so the difference is what is private.
$hidden_text = '';
if ($is_owner) {
    $hidden = [];
    $private_collections = $current_user['collection_count'] - $member['collection_count'];
    $private_plants      = $current_user['plant_count'] - $member['plant_count'];
    if ($private_collections > 0) {
        $hidden[] = count_words($private_collections, 'private collection', 'private collections');
    }
    if ($private_plants > 0) {
        $hidden[] = count_words($private_plants, 'private plant', 'private plants');
    }
    $hidden_text = $hidden ? 'Hidden from them: ' . implode(' and ', $hidden) . '.' : '';
}

$page_title = $member['full_name'] . ' (' . $handle . ')';
$page_css   = 'profile.css';

// Like the Plant Profile: your own profile lights Profile in the navigation, anyone else's is part of Explore
$nav_area = $is_owner ? 'profile' : 'explore';

include __DIR__ . '/../includes/header.php';
?>
<?php if ($is_owner): ?>
  <div class="container profile-note-row">
    <p class="alert profile-note">
      <?= icon('eye') ?>
      <span>
        This is how other people see your profile. <?= e($hidden_text) ?>
        <a href="<?= e(url('settings.php#privacy')) ?>">Privacy settings</a>
      </span>
    </p>
  </div>
<?php endif; ?>

<?php // Identity (p21, elements 1 to 3): a sage cover band, the avatar overlapping it, the name, a short bio, the main action ?>
<section class="profile-top" aria-labelledby="profile-name">
  <div class="profile-cover">
    <div class="container profile-cover-inner">
      <?php // Decoration only, so alt="": three potted plants drawn from the mock drawings ?>
      <img class="profile-cover-art" src="<?= e(url('assets/img/profile-cover.svg')) ?>" alt="">
    </div>
  </div>

  <div class="container">
    <div class="profile-head">
      <div class="profile-avatar">
        <?php partial('avatar', ['member' => $member, 'size' => 'lg']); ?>
      </div>

      <h1 class="profile-name" id="profile-name"><?= e($member['full_name']) ?></h1>

      <div class="profile-about">
        <p class="profile-meta">
          <span class="profile-handle"><?= e($handle) ?></span>
          <?php if (!empty($member['location'])): ?>
            <span><?= icon('map-pin') ?> <?= e($member['location']) ?></span>
          <?php endif; ?>
          <span>Member since <?= e($member_since) ?></span>
        </p>
        <?php if (!empty($member['bio'])): ?>
          <p class="profile-bio"><?= e($member['bio']) ?></p>
        <?php elseif ($is_owner): ?>
          <p class="profile-bio profile-bio-empty"><a href="<?= e(url('settings.php#profile')) ?>">Add a short bio</a> so people know what you grow.</p>
        <?php endif; ?>
      </div>

      <?php // Main action (p21, element 3): one green button. The owner edits; everyone else sees what is on offer. ?>
      <div class="profile-actions">
        <?php if ($is_owner): ?>
          <a class="btn btn-primary" href="<?= e(url('settings.php#profile')) ?>"><?= icon('pencil') ?> Edit profile</a>
        <?php elseif ($listing_count > 0): ?>
          <?php // p21 says "See her listings": the page is generated for any member, so it names no he or she ?>
          <a class="btn btn-primary" href="<?= e($tab_url('listings')) ?>"><?= icon('repeat') ?> See listings (<?= e($listing_count) ?>)</a>
        <?php endif; ?>

        <details class="menu profile-menu">
          <summary class="icon-btn" aria-label="More actions for <?= e($handle) ?>"><?= icon('ellipsis') ?></summary>
          <div class="menu-panel menu-panel-right">
            <?php if ($is_owner): ?>
              <a class="menu-item" href="<?= e(url('settings.php')) ?>"><?= icon('settings') ?> Account settings</a>
              <a class="menu-item" href="<?= e(url('settings.php#privacy')) ?>"><?= icon('lock') ?> Privacy settings</a>
              <a class="menu-item" href="<?= e(url('garden.php')) ?>"><?= icon('leaf') ?> Go to My Garden</a>
            <?php else: ?>
              <?php // No report form yet: the Contact section is where a member who breaks the guidelines is reported ?>
              <a class="menu-item" href="<?= e(url('about.php#contact')) ?>"><?= icon('triangle-alert') ?> Report profile</a>
              <a class="menu-item" href="<?= e(url('about.php#guidelines')) ?>"><?= icon('info') ?> Community guidelines</a>
            <?php endif; ?>
          </div>
        </details>
      </div>

      <?php // Community stats (p21, element 2): public counts only, in one row ?>
      <ul class="profile-stats">
        <li>
          <strong class="profile-stat-value"><?= e($member['plant_count']) ?></strong>
          <span class="profile-stat-label"><?= $member['plant_count'] === 1 ? 'plant' : 'plants' ?></span>
        </li>
        <li>
          <strong class="profile-stat-value"><?= e($member['collection_count']) ?></strong>
          <span class="profile-stat-label"><?= $member['collection_count'] === 1 ? 'collection' : 'collections' ?></span>
        </li>
        <li>
          <strong class="profile-stat-value"><?= e($member['swap_count']) ?></strong>
          <span class="profile-stat-label"><?= $member['swap_count'] === 1 ? 'swap completed' : 'swaps completed' ?></span>
        </li>
        <li>
          <?php if ($has_ratings): ?>
            <strong class="profile-stat-value"><?= e(number_format($member['rating_avg'], 1)) ?></strong>
            <?php // "rating · 23" never breaks apart: on a phone the label wraps after "trader" instead ?>
            <span class="profile-stat-label">trader <span class="profile-stat-keep">rating<span aria-hidden="true"> · <?= e($rating_count) ?></span></span><span class="visually-hidden">, from <?= e(count_words($rating_count, 'rating', 'ratings')) ?></span></span>
          <?php else: ?>
            <strong class="profile-stat-value" aria-hidden="true">–</strong>
            <span class="profile-stat-label">no ratings yet</span>
          <?php endif; ?>
        </li>
      </ul>
    </div>
  </div>
</section>

<div class="container page profile-body">
  <div class="profile-main">
    <nav class="tabs" aria-label="<?= e($handle) ?>'s profile">
      <?php foreach ($tabs as $key => $item): ?>
        <a class="tab<?= $key === $tab ? ' is-active' : '' ?>" href="<?= e($tab_url($key)) ?>"<?= $key === $tab ? ' aria-current="page"' : '' ?>>
          <?php // The id is on a span inside the link, not on the link: a link that is the target of #… gets focus and its ring ?>
          <span class="profile-tab-inner" id="tab-<?= e($key) ?>"><?= e($item['label']) ?> <span class="tab-count"><?= e($item['count']) ?></span></span>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="profile-panel">
      <?php if ($tab === 'collections'): ?>
        <?php // Collection cards (p21, element 5): a three-photo collage per public collection; private ones never get here ?>
        <?php if ($collections): ?>
          <div class="collection-grid">
            <?php foreach ($collections as $collection): ?>
              <article class="card collection-card">
                <?php // The photos only repeat what the name says, so they are decorative (alt=""); one large, two small ?>
                <div class="collage">
                  <?php for ($slot = 0; $slot < 3; $slot++): ?>
                    <?php $photo = $collection['cover_photos'][$slot] ?? null; ?>
                    <?php if ($photo): ?>
                      <img class="collage-tile" src="<?= e(photo_url($photo)) ?>" alt="" loading="lazy">
                    <?php else: ?>
                      <?php // A collection with fewer than three plants: a leaf on sage instead of an empty hole ?>
                      <span class="collage-tile collage-tile-empty"><?= icon('leaf') ?></span>
                    <?php endif; ?>
                  <?php endfor; ?>
                </div>
                <div class="collection-card-body">
                  <h3 class="collection-card-name">
                    <a class="card-link" href="<?= e($tab_url('plants', ['collection' => $collection['id']])) ?>"><?= e($collection['name']) ?></a>
                  </h3>
                  <p class="collection-card-count"><?= e(count_words($collection['plant_count'], 'plant', 'plants')) ?></p>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <section class="panel">
            <?php partial('empty-state', $is_owner
                ? ['title' => 'No public collections yet',
                   'text' => 'Collections you make public in My Garden appear here as photo collages.',
                   'actions' => [['label' => 'Go to My Garden', 'href' => 'garden.php', 'icon' => 'leaf']]]
                : ['title' => 'No public collections yet',
                   'text' => $handle . ' has not shared a collection yet.',
                   'actions' => []]); ?>
          </section>
        <?php endif; ?>

      <?php elseif ($tab === 'plants'): ?>
        <?php if ($collection_filter): ?>
          <?php // Which collection is open, and the way back to all of them ?>
          <div class="profile-filter">
            <h2 class="profile-section-title">Plants in <?= e($collection_filter['name']) ?> · <?= e(count_words($collection_filter['plant_count'], 'plant', 'plants')) ?></h2>
            <a class="profile-filter-clear" href="<?= e($tab_url('plants')) ?>"><?= icon('arrow-left') ?> Show all plants</a>
          </div>
        <?php endif; ?>

        <?php if ($plants): ?>
          <div class="profile-grid">
            <?php foreach ($plants as $plant): ?>
              <?php // The community card, with the collection instead of the owner: the page already says whose plants these are ?>
              <?php partial('plant-card', ['plant' => $plant, 'community' => true, 'show_owner' => false]); ?>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <section class="panel">
            <?php partial('empty-state', $is_owner
                ? ['title' => 'No public plants yet',
                   'text' => 'Plants you make public appear here, for everyone to see.',
                   'actions' => [['label' => 'Add a plant', 'href' => 'plant-form.php', 'icon' => 'plus']]]
                : ['title' => 'No public plants yet',
                   'text' => $handle . ' has not shared a plant yet.',
                   'actions' => []]); ?>
          </section>
        <?php endif; ?>

      <?php elseif ($tab === 'listings'): ?>
        <?php if ($listings): ?>
          <div class="profile-grid profile-grid-listings">
            <?php foreach ($listings as $listing): ?>
              <?php partial('listing-card', ['listing' => $listing]); ?>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <section class="panel">
            <?php partial('empty-state', $is_owner
                ? ['title' => 'No open listings',
                   'text' => 'List a plant to swap it or give it away. Your open listings appear here.',
                   'actions' => [['label' => 'List a plant', 'href' => 'listing-form.php', 'icon' => 'plus']]]
                : ['title' => 'No open listings',
                   'text' => $handle . ' has nothing on the exchange right now.',
                   'actions' => [['label' => 'Browse the exchange', 'href' => 'exchange.php', 'icon' => 'repeat']]]); ?>
          </section>
        <?php endif; ?>

      <?php else: ?>
        <?php // Ratings: the written reviews. Ratings without a comment are counted in the breakdown, not listed. ?>
        <?php if ($reviews): ?>
          <p class="review-summary">
            <strong><?= e(count_words(count($reviews), 'written review', 'written reviews')) ?></strong>
            out of <?= e(count_words($rating_count, 'rating', 'ratings')) ?>. Ratings without a comment still count in the average.
          </p>
          <ol class="review-list">
            <?php foreach ($reviews as $review): ?>
              <?php $rater = ['username' => $review['rater_username'], 'full_name' => $review['rater_full_name'], 'avatar' => $review['rater_avatar']]; ?>
              <li class="review">
                <div class="review-head">
                  <?php partial('avatar', ['member' => $rater, 'size' => 'sm']); ?>
                  <a class="review-author" href="<?= e(url('user.php?u=' . urlencode($review['rater_username']))) ?>">@<?= e($review['rater_username']) ?></a>
                  <?php partial('stars', ['score' => $review['score'], 'size' => 'sm']); ?>
                  <time class="review-date" datetime="<?= e(substr($review['created_at'], 0, 10)) ?>"><?= e(review_date($review['created_at'])) ?></time>
                </div>
                <p class="review-comment"><?= e($review['comment']) ?></p>
                <p class="review-exchange"><?= icon('repeat') ?> <span class="visually-hidden">Exchange:</span> <?= e($review['listing_title']) ?></p>
              </li>
            <?php endforeach; ?>
          </ol>
        <?php elseif ($has_ratings): ?>
          <p class="panel-empty"><?= icon('info') ?> No written reviews yet: all <?= e(count_words($rating_count, 'rating was', 'ratings were')) ?> given without a comment.</p>
        <?php else: ?>
          <section class="panel">
            <?php partial('empty-state', [
                'title'   => 'No ratings yet',
                'text'    => ($is_owner ? 'You get' : $handle . ' gets') . ' a rating after each completed swap. Ratings and reviews appear here.',
                'actions' => [],
            ]); ?>
          </section>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <?php // Rating breakdown (p21, element 6) and latest review (element 7): beside the tabs on a desktop, below them on a phone ?>
  <aside class="panel profile-rating" aria-labelledby="rating-title">
    <h2 class="panel-title" id="rating-title">Trader rating</h2>
    <?php if ($has_ratings): ?>
      <div class="rating-summary">
        <?php // The stars carry the number for screen readers ("Rated 4.9 out of 5"), so the big number is hidden from them ?>
        <span class="rating-big" aria-hidden="true"><?= e(number_format($member['rating_avg'], 1)) ?></span>
        <span class="rating-summary-text">
          <?php partial('stars', ['score' => (float) $member['rating_avg']]); ?>
          <span class="text-small"><?= e(count_words($rating_count, 'rating', 'ratings')) ?></span>
        </span>
      </div>

      <?php // One bar per score, so one bad rating is seen in context (p21). The bars repeat the numbers: hidden from screen readers. ?>
      <ul class="rating-bars">
        <?php foreach ($score_counts as $score => $count): ?>
          <li class="rating-bar-row">
            <span class="rating-bar-score" aria-hidden="true"><?= e($score) ?><?= icon('star') ?></span>
            <span class="rating-bar" aria-hidden="true"><span class="rating-bar-fill" style="width: <?= e(round($count / $rating_count * 100)) ?>%"></span></span>
            <span class="rating-bar-count" aria-hidden="true"><?= e($count) ?></span>
            <span class="visually-hidden"><?= e($score) ?> <?= $score === 1 ? 'star' : 'stars' ?>: <?= e(count_words($count, 'rating', 'ratings')) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>

      <?php if ($latest_review): ?>
        <div class="latest-review">
          <h3 class="label latest-review-title">Latest review</h3>
          <blockquote class="latest-review-quote">
            <p>“<?= e($latest_review['comment']) ?>”</p>
          </blockquote>
          <p class="latest-review-by">
            <?php partial('avatar', ['member' => ['username' => $latest_review['rater_username'], 'full_name' => $latest_review['rater_full_name'], 'avatar' => $latest_review['rater_avatar']], 'size' => 'sm']); ?>
            <span>
              <a href="<?= e(url('user.php?u=' . urlencode($latest_review['rater_username']))) ?>">@<?= e($latest_review['rater_username']) ?></a>
              · <time datetime="<?= e(substr($latest_review['created_at'], 0, 10)) ?>"><?= e(review_date($latest_review['created_at'])) ?></time>
            </span>
          </p>
          <?php if ($tab !== 'ratings'): ?>
            <a class="panel-link" href="<?= e($tab_url('ratings')) ?>">See all reviews (<?= e(count($reviews)) ?>)</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <p class="rating-none-text"><?= icon('star') ?> No ratings yet</p>
      <p class="text-small">Ratings appear after a completed swap.</p>
    <?php endif; ?>
  </aside>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
