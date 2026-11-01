<?php
// My Exchanges (P13, wireframe p20): the owner's control room for swaps: which listings are open, who is interested,
// and which trades still need a rating (COM-10, UI half). Members only.
// Four tabs, each with its own address: ?tab=listings, ?tab=received (the default, as drawn on p20), ?tab=sent and
// ?tab=ratings. ?listing=7 picks the listing whose interest table is shown (Listing Detail's "Manage in My exchanges").
// "Mark as exchanged" and "Submit rating" work in the preview session (mock_mark_exchanged(), mock_rate_exchange()), like
// "I'm interested" on Listing Detail, so a demo can walk through a whole swap; stage B saves them in the database.

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mock-data.php';

// Stage F stand-in for require_login(): a visitor (?guest) is sent to Log in, exactly as stage B will do.
if (!$current_user) {
    header('Location: ' . url('login.php'));
    exit;
}

$me = $current_user['id'];

// ── The member's listings ───────────────────────────────
// Open ones first, newest first; then the finished ones, the most recently exchanged first.
// Stage B: SELECT … FROM exchange_listings WHERE user_id = ? ORDER BY status = 'open' DESC, COALESCE(exchanged_at, created_at) DESC

$my_listings = array_values(array_filter($mock_listings, fn ($listing) => $listing['user_id'] === $me));
usort($my_listings, fn ($a, $b) => [$b['status'] === 'open', $b['exchanged_at'] ?? $b['created_at']]
                                <=> [$a['status'] === 'open', $a['exchanged_at'] ?? $a['created_at']]);
$listings_by_id = array_column($my_listings, null, 'id');
$all_listings   = array_column($mock_listings, null, 'id');   // every listing, for the ones the member is interested in

// ── Interests received (p20, elements 2 to 5) ───────────
// Withdrawn rows are gone for the owner too. The tab counts the people waiting on open listings: those need an answer.

$received = array_values(array_filter(
    $mock_received_interests,
    fn ($interest) => $interest['status'] !== 'withdrawn' && isset($listings_by_id[$interest['listing_id']])
));
$received_count = count(array_filter($received, fn ($interest) => $listings_by_id[$interest['listing_id']]['status'] === 'open'));

// ── Interests sent ──────────────────────────────────────
// The member's own interest rows, each with the listing it is about (stage B: one query that joins exchange_listings
// and users for the owner; here 'listing' holds that joined row), newest first.

$sent = [];
foreach ($mock_listing_interests as $interest) {
    if ($interest['user_id'] === $me && $interest['status'] !== 'withdrawn' && isset($all_listings[$interest['listing_id']])) {
        $sent[] = $interest + ['listing' => $all_listings[$interest['listing_id']]];
    }
}
usort($sent, fn ($a, $b) => $b['created_at'] <=> $a['created_at']);

// ── Finished exchanges and ratings (p20, element 6) ─────
// An exchange is a closed listing with an exchanged_with: the member's own (the other person is exchanged_with), or
// someone else's that went to the member (exchanged_with is the member). Each side may rate once (p20, UX notes).

$exchanges = [];
foreach ($mock_listings as $listing) {
    if ($listing['status'] !== 'closed' || $listing['exchanged_with'] === null) {
        continue;
    }
    if ($listing['user_id'] === $me) {
        $other_id = $listing['exchanged_with'];
    } elseif ($listing['exchanged_with'] === $me) {
        $other_id = $listing['user_id'];
    } else {
        continue;
    }
    $exchanges[$listing['id']] = ['listing' => $listing, 'other' => $mock_users[$other_id], 'is_owner' => $listing['user_id'] === $me];
}

// The ratings the member gave: the mock rows with a comment, and the ones given in this preview. A rating without a
// comment is only a number in $mock_rating_counts, with no listing attached; none of the member's are like that.
$given = array_merge(
    $mock_preview_ratings,
    array_values(array_filter($mock_user_ratings, fn ($rating) => $rating['rater_id'] === $me))
);
usort($given, fn ($a, $b) => $b['created_at'] <=> $a['created_at']);
$rated_listing_ids = array_column($given, 'listing_id');

// Waiting for a rating: finished exchanges the member has not rated yet, the most recent first
$waiting = array_filter($exchanges, fn ($exchange) => !in_array($exchange['listing']['id'], $rated_listing_ids, true));
uasort($waiting, fn ($a, $b) => $b['listing']['exchanged_at'] <=> $a['listing']['exchanged_at']);

/**
 * The sentence about one finished exchange, with the title and the other member in bold (p20, element 6):
 * "You swapped Spider plant babies with @amaya.grows on 12 Sep." Every value goes through e() here, so the page prints
 * the result as it is.
 */
function exchange_sentence($exchange)
{
    $listing = $exchange['listing'];
    $title   = '<strong>' . e($listing['title']) . '</strong>';
    $other   = '<strong>@' . e($exchange['other']['username']) . '</strong>';
    $day     = e(format_day($listing['exchanged_at']));

    if (!$exchange['is_owner']) {
        return 'You received ' . $title . ' from ' . $other . ' on ' . $day . '.';
    }
    if ($listing['type'] === 'free') {
        return 'You gave ' . $title . ' to ' . $other . ' on ' . $day . '.';
    }
    if ($listing['type'] === 'either') {
        return 'You exchanged ' . $title . ' with ' . $other . ' on ' . $day . '.';
    }
    return 'You swapped ' . $title . ' with ' . $other . ' on ' . $day . '.';
}

// ── Tabs (p20, element 1) ───────────────────────────────
// Links with ?tab=, like the Public Profile's tabs, so each tab has an address and works without JavaScript.

$tabs = [
    'listings' => ['label' => 'My listings',        'count' => count($my_listings)],
    'received' => ['label' => 'Interests received', 'count' => $received_count],
    'sent'     => ['label' => 'Interests sent',     'count' => count($sent)],
    'ratings'  => ['label' => 'Ratings',            'count' => count($waiting)],   // shown only while one is waiting
];
$tab = get_text('tab');
if (!array_key_exists($tab, $tabs)) {
    $tab = 'received';
}

$tab_url = fn ($key, array $more = []) => query_url('my-exchanges.php', array_merge(['tab' => $key], $more));

// ── The selected listing (p20, element 2) ───────────────
// ?listing= must be one of the member's own listings. Without it: the first open listing that has interest, else the
// first listing, so the table is never empty when there is something to show.

$listing_param = get_text('listing');
$selected      = ctype_digit($listing_param) ? ($listings_by_id[(int) $listing_param] ?? null) : null;
if (!$selected) {
    foreach ($my_listings as $listing) {
        if ($listing['status'] === 'open' && $listing['interest_count'] > 0) {
            $selected = $listing;
            break;
        }
    }
    $selected = $selected ?? ($my_listings[0] ?? null);
}

$selected_rows = $selected
    ? array_values(array_filter($received, fn ($interest) => $interest['listing_id'] === $selected['id']))
    : [];

// The "All (4)" menu of the table (p20, element 3): all rows, only New ones, or only Seen ones
$show_options = ['' => 'All', 'new' => 'New', 'seen' => 'Seen'];
$show         = get_text('show');
if (!array_key_exists($show, $show_options)) {
    $show = '';
}
$show_counts = [
    ''     => count($selected_rows),
    'new'  => count(array_filter($selected_rows, fn ($interest) => $interest['status'] === 'new')),
    'seen' => count(array_filter($selected_rows, fn ($interest) => $interest['status'] === 'seen')),
];
$shown_rows = $show === '' ? $selected_rows : array_values(array_filter($selected_rows, fn ($interest) => $interest['status'] === $show));

// ── Mark as exchanged · Submit rating (Post/Redirect/Get) ──
// Stage B, before anything else: check the CSRF token. The checks below stay, because a hand-made request can skip the
// form: only an interest in the member's own open listing can be marked, and only a waiting exchange can be rated, once.

$rate_errors = [];   // listing id => message, printed in that exchange's rating form
$rate_values = [];   // listing id => what was typed, kept when the form comes back with an error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post_text('action');

    if ($action === 'exchange') {
        // Mark as exchanged (p20, element 5): closes the listing, records who it went to and unlocks rating
        $interest_id = post_text('interest');
        $row = null;
        foreach ($received as $interest) {
            if (ctype_digit($interest_id) && $interest['id'] === (int) $interest_id) {
                $row = $interest;
            }
        }
        if ($row && $listings_by_id[$row['listing_id']]['status'] === 'open') {
            mock_mark_exchanged($row['listing_id'], $row['user_id']);
            header('Location: ' . $tab_url('received', ['listing' => $row['listing_id'], 'exchanged' => 1]) . '#interests');
            exit;
        }
        header('Location: ' . $tab_url('received'));
        exit;
    }

    if ($action === 'rate') {
        $listing_id = post_text('listing');
        $exchange   = ctype_digit($listing_id) ? ($waiting[(int) $listing_id] ?? null) : null;

        if (!$exchange) {
            // Not an exchange of this member, or already rated: nothing to do
            header('Location: ' . $tab_url('ratings'));
            exit;
        }

        $id      = $exchange['listing']['id'];
        $score   = post_text('score');
        $comment = mb_substr(trim(str_replace("\r\n", "\n", post_text('comment'))), 0, 300);   // \r\n: see Listing Detail
        $rate_values[$id] = ['score' => $score, 'comment' => $comment];

        if (!in_array($score, ['1', '2', '3', '4', '5'], true)) {
            $rate_errors[$id] = 'Choose from 1 to 5 stars.';
            $tab = 'ratings';
        } else {
            mock_rate_exchange($id, $me, $exchange['other']['id'], (int) $score, $comment === '' ? null : $comment);
            header('Location: ' . $tab_url('ratings', ['rated' => $id]));
            exit;
        }
    }
}

// ── Messages after a redirect ───────────────────────────
// Each is shown only when the data agrees, so a hand-made ?exchanged=1 or ?rated=5 shows nothing.

$just_exchanged = get_text('exchanged') === '1' && $selected && $selected['status'] === 'closed'
                  && isset($exchanges[$selected['id']]) && isset($waiting[$selected['id']]);
$rated_param    = get_text('rated');
$just_rated     = null;
foreach ($mock_preview_ratings as $rating) {
    if (ctype_digit($rated_param) && $rating['listing_id'] === (int) $rated_param) {
        $just_rated = $mock_users[$rating['rated_id']];
    }
}

// The words for each rating score, shown beside the stars (S4, wireframe p24: four stars "Great")
$score_words = [1 => 'Poor', 2 => 'Fair', 3 => 'Good', 4 => 'Great', 5 => 'Excellent'];

// The member's own public profile, on its Ratings tab: the ratings other people gave them (P14)
$my_ratings_url = query_url('user.php', ['u' => $current_user['username'], 'tab' => 'ratings']);

$page_title = 'My exchanges';
$page_css   = 'my-exchanges.css';

include __DIR__ . '/../includes/header.php';
?>
<div class="container page">
  <div class="exchanges-head">
    <div>
      <h1 class="exchanges-title">My exchanges</h1>
      <p class="exchanges-lead">Manage your listings, see who is interested, and rate your swaps.</p>
    </div>
    <a class="btn btn-primary" href="<?= e(url('listing-form.php')) ?>"><?= icon('plus') ?> List a plant</a>
  </div>

  <?php // Section tabs (p20, element 1), each with a count. Ratings shows its count only while a rating is waiting,
        // in terracotta: something to do. ?>
  <nav class="tabs exchanges-tabs" aria-label="My exchanges">
    <?php foreach ($tabs as $key => $item): ?>
      <a class="tab<?= $key === $tab ? ' is-active' : '' ?>" href="<?= e($tab_url($key)) ?>"<?= $key === $tab ? ' aria-current="page"' : '' ?>>
        <?= e($item['label']) ?>
        <?php if ($key !== 'ratings'): ?>
          <span class="tab-count"><?= e($item['count']) ?></span>
        <?php elseif ($item['count'] > 0): ?>
          <span class="tab-count tab-count-due"><?= e($item['count']) ?><span class="visually-hidden"> waiting</span></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="exchanges-body">
    <?php if ($tab === 'received'): ?>
      <?php // ── Interests received (p20, elements 2 to 5) ── ?>
      <?php if (!$my_listings): ?>
        <section class="panel">
          <?php partial('empty-state', [
              'title'   => 'No listings yet',
              'text'    => 'List a plant to swap it or give it away. The people who are interested appear here.',
              'actions' => [['label' => 'List a plant', 'href' => 'listing-form.php', 'icon' => 'plus'],
                            ['label' => 'Browse the exchange', 'href' => 'exchange.php', 'icon' => 'repeat']],
          ]); ?>
        </section>
      <?php else: ?>
        <?php if ($just_exchanged): ?>
          <?php $partner = $exchanges[$selected['id']]['other']; ?>
          <p class="alert alert-success exchanges-alert" role="status">
            <?= icon('check') ?>
            <span>Marked as exchanged with @<?= e($partner['username']) ?>. The listing is closed.
              <a href="<?= e($tab_url('ratings')) ?>#rate-<?= e($selected['id']) ?>">Rate @<?= e($partner['username']) ?></a></span>
          </p>
        <?php endif; ?>

        <?php // Listing selector (p20, element 2): open and exchanged listings; the chosen one fills the table ?>
        <h2 class="visually-hidden">Choose a listing</h2>
        <ul class="listing-picker">
          <?php foreach ($my_listings as $listing): ?>
            <?php $is_selected = $selected && $listing['id'] === $selected['id']; ?>
            <li>
              <a class="picker-card" href="<?= e($tab_url('received', ['listing' => $listing['id']])) ?>#interests"<?= $is_selected ? ' aria-current="true"' : '' ?>>
                <img class="thumb" src="<?= e(photo_url($listing['photo'])) ?>" alt="" loading="lazy">
                <span class="picker-text">
                  <span class="picker-title"><?= e($listing['title']) ?></span>
                  <span class="picker-meta">
                    <?php if ($listing['status'] === 'open'): ?>
                      <span class="badge badge-open">Open</span>
                      <?= e($listing['interest_count'] > 0 ? $listing['interest_count'] . ' interested' : 'No interest yet') ?>
                    <?php else: ?>
                      <span class="badge badge-exchanged">Exchanged</span>
                      <?= e(format_day($listing['exchanged_at'])) ?>
                    <?php endif; ?>
                  </span>
                </span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>

        <?php // Interest table (p20, elements 3 to 5). A real table on a tablet and up, because owners compare people
              // across the same columns (p20, UX notes); stacked cards on a phone (p23). ?>
        <section class="panel interests-panel" id="interests" aria-labelledby="interests-title">
          <div class="panel-head">
            <h2 class="panel-title" id="interests-title">People interested in "<?= e($selected['title']) ?>"</h2>
            <?php if ($selected_rows): ?>
              <details class="menu interests-filter">
                <summary class="btn btn-secondary btn-sm"><?= e($show_options[$show]) ?> (<?= e($show_counts[$show]) ?>) <?= icon('chevron-down') ?></summary>
                <div class="menu-panel menu-panel-right">
                  <?php foreach ($show_options as $value => $label): ?>
                    <a class="menu-item" href="<?= e($tab_url('received', ['listing' => $selected['id'], 'show' => $value])) ?>#interests"<?= $value === $show ? ' aria-current="page"' : '' ?>><?= e($label) ?> (<?= e($show_counts[$value]) ?>)</a>
                  <?php endforeach; ?>
                </div>
              </details>
            <?php endif; ?>
          </div>

          <?php if ($selected['status'] === 'closed' && isset($exchanges[$selected['id']])): ?>
            <p class="interests-closed"><?= icon('lock') ?> <span><?= exchange_sentence($exchanges[$selected['id']]) ?> The listing is closed.</span></p>
          <?php endif; ?>

          <?php if ($shown_rows): ?>
            <table class="interest-table">
              <thead>
                <tr>
                  <th scope="col">Person</th>
                  <th scope="col">Note</th>
                  <th scope="col">Received</th>
                  <th scope="col">Status</th>
                  <th scope="col"><span class="visually-hidden">Action</span></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($shown_rows as $interest): ?>
                  <tr>
                    <?php // Requester trust (p20, element 4): the person's rating, before the owner replies ?>
                    <td class="interest-person">
                      <?php partial('avatar', ['member' => $interest]); ?>
                      <span class="interest-who">
                        <a href="<?= e(url('user.php?u=' . urlencode($interest['username']))) ?>">@<?= e($interest['username']) ?></a>
                        <?php partial('rating', ['rating' => $interest]); ?>
                      </span>
                    </td>
                    <td class="interest-note">
                      <?php if ($interest['message'] !== null): ?>
                        <?= e($interest['message']) ?>
                      <?php else: ?>
                        <span class="interest-none">No note</span>
                      <?php endif; ?>
                    </td>
                    <?php // On a phone the column headings are hidden, so the cells say what they are ?>
                    <td class="interest-date"><span class="interest-cell-label">Received </span><?= e(format_day($interest['created_at'])) ?></td>
                    <td class="interest-status">
                      <?php // New rows keep a terracotta tag until the owner has seen them (p20, UX notes) ?>
                      <?php if ($interest['status'] === 'new'): ?>
                        <span class="badge badge-due">New</span>
                      <?php else: ?>
                        <span class="badge badge-neutral">Seen</span>
                      <?php endif; ?>
                    </td>
                    <td class="interest-action">
                      <?php if ($selected['status'] === 'open'): ?>
                        <form method="post" action="<?= e($tab_url('received', ['listing' => $selected['id']])) ?>">
                          <?php // Stage B: a hidden CSRF token goes here, in every POST form ?>
                          <input type="hidden" name="action" value="exchange">
                          <input type="hidden" name="interest" value="<?= e($interest['id']) ?>">
                          <button type="submit" class="btn btn-secondary btn-sm">Mark as exchanged<span class="visually-hidden"> with @<?= e($interest['username']) ?></span></button>
                        </form>
                      <?php elseif ($interest['user_id'] === $selected['exchanged_with']): ?>
                        <span class="badge badge-tag"><?= icon('check') ?> <?= $selected['type'] === 'free' ? 'Given' : 'Exchanged' ?></span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php elseif ($selected_rows): ?>
            <p class="panel-empty"><?= icon('info') ?> No <?= e(strtolower($show_options[$show])) ?> interest. <a href="<?= e($tab_url('received', ['listing' => $selected['id']])) ?>#interests">Show all</a></p>
          <?php else: ?>
            <p class="panel-empty"><?= icon('info') ?> No one is interested yet. People appear here as soon as they tap "I'm interested".</p>
          <?php endif; ?>
        </section>
      <?php endif; ?>

    <?php elseif ($tab === 'listings'): ?>
      <?php // ── My listings ── ?>
      <?php if (!$my_listings): ?>
        <section class="panel">
          <?php partial('empty-state', [
              'title'   => 'No listings yet',
              'text'    => 'List a plant or some cuttings to swap them or give them away.',
              'actions' => [['label' => 'List a plant', 'href' => 'listing-form.php', 'icon' => 'plus']],
          ]); ?>
        </section>
      <?php else: ?>
        <ul class="exchange-rows">
          <?php foreach ($my_listings as $listing): ?>
            <?php $type = listing_type($listing['type']); ?>
            <li class="panel exchange-row">
              <img class="thumb exchange-row-photo" src="<?= e(photo_url($listing['photo'])) ?>" alt="" loading="lazy">
              <div class="exchange-row-text">
                <h2 class="exchange-row-title"><a href="<?= e(url('listing.php?id=' . $listing['id'])) ?>"><?= e($listing['title']) ?></a></h2>
                <p class="exchange-row-meta">
                  <span class="badge badge-type-<?= e($listing['type']) ?>"><?= icon($type['icon']) ?> <?= e($type['label']) ?></span>
                  <?php if ($listing['status'] === 'open'): ?>
                    <span class="badge badge-open">Open</span>
                    <span>Posted <?= e(format_day($listing['created_at'])) ?> · <?= e(count_words($listing['interest_count'], 'person', 'people')) ?> interested</span>
                  <?php else: ?>
                    <span class="badge badge-exchanged">Exchanged</span>
                    <span><?= e(format_day($listing['exchanged_at'])) ?><?php if (isset($exchanges[$listing['id']])): ?> · with @<?= e($exchanges[$listing['id']]['other']['username']) ?><?php endif; ?></span>
                  <?php endif; ?>
                </p>
              </div>
              <div class="exchange-row-actions">
                <a class="btn btn-secondary btn-sm" href="<?= e($tab_url('received', ['listing' => $listing['id']])) ?>#interests">See interest<span class="visually-hidden"> in <?= e($listing['title']) ?></span></a>
                <?php if ($listing['status'] === 'open'): ?>
                  <a class="btn btn-ghost btn-sm" href="<?= e(url('listing-form.php?id=' . $listing['id'])) ?>"><?= icon('pencil') ?> Edit<span class="visually-hidden"> <?= e($listing['title']) ?></span></a>
                <?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

    <?php elseif ($tab === 'sent'): ?>
      <?php // ── Interests sent: the member as the requester. The owner's contact details are on each listing (P11). ── ?>
      <?php if (!$sent): ?>
        <section class="panel">
          <?php partial('empty-state', [
              'title'   => 'No interest sent yet',
              'text'    => 'When you tap "I\'m interested" on a listing, it appears here with the owner\'s answer.',
              'actions' => [['label' => 'Browse the exchange', 'href' => 'exchange.php', 'icon' => 'repeat']],
          ]); ?>
        </section>
      <?php else: ?>
        <ul class="exchange-rows">
          <?php foreach ($sent as $interest): ?>
            <?php $listing = $interest['listing']; ?>
            <li class="panel exchange-row">
              <img class="thumb exchange-row-photo" src="<?= e(photo_url($listing['photo'])) ?>" alt="" loading="lazy">
              <div class="exchange-row-text">
                <h2 class="exchange-row-title"><a href="<?= e(url('listing.php?id=' . $listing['id'])) ?>"><?= e($listing['title']) ?></a></h2>
                <p class="exchange-row-meta">
                  <?php if ($listing['status'] !== 'open'): ?>
                    <span class="badge badge-neutral">Closed</span>
                  <?php elseif ($interest['status'] === 'new'): ?>
                    <span class="badge badge-neutral">Not opened yet</span>
                  <?php else: ?>
                    <span class="badge badge-tag"><?= icon('eye') ?> Seen by the owner</span>
                  <?php endif; ?>
                  <span>Sent <?= e(format_day($interest['created_at'])) ?> · @<?= e($listing['username']) ?></span>
                  <?php partial('rating', ['rating' => $listing]); ?>
                </p>
                <?php if ($interest['message'] !== null): ?>
                  <p class="exchange-row-note"><span class="exchange-row-note-label">Your note</span> <?= e($interest['message']) ?></p>
                <?php endif; ?>
              </div>
              <div class="exchange-row-actions">
                <a class="btn btn-secondary btn-sm" href="<?= e(url('listing.php?id=' . $listing['id'])) ?>#summary"><?= $listing['status'] === 'open' ? 'Contact details' : 'View listing' ?><span class="visually-hidden"> for <?= e($listing['title']) ?></span></a>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

    <?php else: ?>
      <?php // ── Ratings: rate each finished exchange once (S4 on p24). Week 5 opens the same form in the Rate dialog. ── ?>
      <?php if ($just_rated): ?>
        <p class="alert alert-success exchanges-alert" role="status"><?= icon('check') ?> <span>Thank you! Your rating of @<?= e($just_rated['username']) ?> is saved.</span></p>
      <?php endif; ?>

      <?php if ($waiting): ?>
        <section class="exchanges-section" aria-labelledby="waiting-title">
          <h2 class="exchanges-section-title" id="waiting-title">Waiting for your rating</h2>
          <?php foreach ($waiting as $id => $exchange): ?>
            <?php
            $other  = $exchange['other'];
            $typed  = $rate_values[$id] ?? ['score' => '', 'comment' => ''];
            $prefix = 'rate-' . $id;   // ids must be unique on the page when several exchanges wait
            ?>
            <article class="panel rate-card" id="<?= e($prefix) ?>" aria-labelledby="<?= e($prefix) ?>-title">
              <div class="rate-card-head">
                <img class="thumb" src="<?= e(photo_url($exchange['listing']['photo'])) ?>" alt="" loading="lazy">
                <div>
                  <h3 class="rate-card-title" id="<?= e($prefix) ?>-title">Rate @<?= e($other['username']) ?></h3>
                  <p class="rate-card-text"><?= exchange_sentence($exchange) ?> How did it go?</p>
                </div>
              </div>

              <form class="rate-form" method="post" action="<?= e($tab_url('ratings')) ?>#<?= e($prefix) ?>">
                <?php // Stage B: a hidden CSRF token goes here, in every POST form ?>
                <input type="hidden" name="action" value="rate">
                <input type="hidden" name="listing" value="<?= e($id) ?>">

                <?php // Five radio buttons drawn as stars. my-exchanges.css fills the stars up to the chosen one and shows
                      // its word, with :has(), so it works without JavaScript. ?>
                <fieldset class="form-fieldset rate-stars"<?= isset($rate_errors[$id]) ? ' aria-describedby="' . e($prefix) . '-error"' : '' ?>>
                  <legend class="field-label">Your rating</legend>
                  <div class="rate-stars-row">
                    <?php for ($score = 1; $score <= 5; $score++): ?>
                      <input class="visually-hidden" type="radio" id="<?= e($prefix . '-' . $score) ?>" name="score" value="<?= e($score) ?>"<?= $typed['score'] === (string) $score ? ' checked' : '' ?> required>
                      <label for="<?= e($prefix . '-' . $score) ?>"><?= icon('star') ?><span class="visually-hidden"><?= e(count_words($score, 'star', 'stars')) ?>, <?= e($score_words[$score]) ?></span></label>
                    <?php endfor; ?>
                    <span class="rate-words" aria-hidden="true">
                      <?php foreach ($score_words as $score => $word): ?>
                        <span class="rate-word rate-word-<?= e($score) ?>"><?= e($word) ?></span>
                      <?php endforeach; ?>
                    </span>
                  </div>
                  <?php if (isset($rate_errors[$id])): ?>
                    <span class="field-error" id="<?= e($prefix) ?>-error"><?= e($rate_errors[$id]) ?></span>
                  <?php endif; ?>
                </fieldset>

                <div class="field">
                  <label class="field-label" for="<?= e($prefix) ?>-comment">Comment <span class="field-optional">(optional)</span></label>
                  <textarea class="input" id="<?= e($prefix) ?>-comment" name="comment" rows="2" maxlength="300"
                            placeholder="Healthy plant and easy to arrange." aria-describedby="<?= e($prefix) ?>-hint"><?= e($typed['comment']) ?></textarea>
                  <span class="field-hint" id="<?= e($prefix) ?>-hint">Shown on @<?= e($other['username']) ?>'s public profile. One rating per exchange.</span>
                </div>

                <button class="btn btn-primary" type="submit">Submit rating</button>
              </form>
            </article>
          <?php endforeach; ?>
        </section>
      <?php endif; ?>

      <section class="exchanges-section" aria-labelledby="given-title">
        <h2 class="exchanges-section-title" id="given-title">Ratings you gave</h2>
        <?php if ($given): ?>
          <ul class="given-list">
            <?php foreach ($given as $rating): ?>
              <?php
              $member = $mock_users[$rating['rated_id']];
              $title  = $rating['listing_title'] ?? ($all_listings[$rating['listing_id']]['title'] ?? '');
              ?>
              <li class="panel given-item">
                <div class="given-head">
                  <?php partial('avatar', ['member' => $member, 'size' => 'sm']); ?>
                  <a class="given-who" href="<?= e(url('user.php?u=' . urlencode($member['username']))) ?>">@<?= e($member['username']) ?></a>
                  <?php partial('stars', ['score' => $rating['score'], 'size' => 'sm', 'show_score' => true]); ?>
                </div>
                <?php if ($rating['comment'] !== null): ?>
                  <p class="given-comment"><?= e($rating['comment']) ?></p>
                <?php endif; ?>
                <p class="given-meta"><?= e($title) ?> · <?= e(date('j M Y', strtotime($rating['created_at']))) ?></p>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="panel panel-empty"><?= icon('info') ?> No ratings yet. After an exchange, rate the other member here.</p>
        <?php endif; ?>
        <a class="exchanges-profile-link" href="<?= e($my_ratings_url) ?>">See the ratings others gave you on your profile <?= icon('arrow-right') ?></a>
      </section>
    <?php endif; ?>

    <?php // Rating prompt (p20, element 6): reminds the member to rate after a finished exchange, on every tab but
          // Ratings itself, where the form is. It opens the form on the Ratings tab; week 5 opens the Rate dialog (S4). ?>
    <?php if ($waiting && $tab !== 'ratings'): ?>
      <?php $first = reset($waiting); ?>
      <div class="rate-prompt">
        <span class="rate-prompt-icon"><?= icon('star') ?></span>
        <p class="rate-prompt-text"><?= exchange_sentence($first) ?> How did it go?</p>
        <a class="btn btn-accent btn-sm" href="<?= e($tab_url('ratings')) ?>#rate-<?= e($first['listing']['id']) ?>">Rate @<?= e($first['other']['username']) ?></a>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
