<?php
// Exchange Marketplace (P10, wireframe p17): open listings to swap or take for free, with a search, Swap/Free type,
// distance and species filters and a sort (COM-07, UI half). The four-step strip explains the rules before anyone clicks.
// Public: visitors browse every open listing; listing a plant and "I'm interested" (P11) need an account.
// Reads $mock_listings, $mock_species and $current_user from mock data and filters them in PHP.

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mock-data.php';

// ── What the visitor asked for, read from the address ───
// exchange.php?q=pothos&type=swap&distance=10&species=2&sort=nearest
// Every value is checked against what is allowed, as on Explore: a wrong or hand-made value falls back to its default.
// get_text() (helpers.php) accepts only one piece of valid text: q[]=x or q=%FF count as "not given".

$q    = mb_substr(trim(get_text('q')), 0, 100);
$term = trim(ltrim($q, '@'));   // "@ruwan" finds Ruwan's listings: the @ is not part of the username

// All / Swap / Free (p17, element 3). "All" is the default, so it has no value in the address.
$type_options = ['' => 'All', 'swap' => 'Swap', 'free' => 'Free'];
$type = get_text('type');
if (!in_array($type, ['swap', 'free'], true)) {
    $type = '';
}

// Distance is measured from the town on the member's profile, never from GPS (p17, UX notes).
// A visitor has no town: distance_km is null for them (see $mock_listings), so the Near filter and the Nearest sort
// are left out and every listing is shown.
$viewer_town = $current_user['location'] ?? '';
$has_town    = $viewer_town !== '';

$distance_choices = ['5', '10', '25', '50', 'any'];
$default_distance = $has_town ? '25' : 'any';   // p17 opens on "Near Kurunegala · 25 km"
$distance = get_text('distance');
if (!$has_town || !in_array($distance, $distance_choices, true)) {
    $distance = $default_distance;
}

/** The words for a distance choice: "Within 5 km" or "Any distance". */
function distance_label($distance)
{
    return $distance === 'any' ? 'Any distance' : 'Within ' . $distance . ' km';
}

$sort_options = ['newest' => 'Newest', 'nearest' => 'Nearest', 'interest' => 'Most interest'];
if (!$has_town) {
    unset($sort_options['nearest']);   // nothing to be near to
}
$sort = get_text('sort');
if (!array_key_exists($sort, $sort_options)) {
    $sort = 'newest';
}

// Closed and exchanged listings are hidden (p17, UX notes). Stage B: WHERE exchange_listings.status = 'open'.
$open_listings = array_filter($mock_listings, fn ($listing) => $listing['status'] === 'open');

// The species on offer right now, A–Z: the only ones worth choosing. Stage B: SELECT DISTINCT on the same join.
$species_options = [];
foreach ($open_listings as $listing) {
    $species_options[$listing['species_id']] = $listing['common_name'];
}
asort($species_options);

$species = get_text('species');
$species = (ctype_digit($species) && isset($species_options[(int) $species])) ? (int) $species : null;

// The choices that differ from the defaults. Every link on the page starts from these and changes one thing.
$filters = [
    'q'        => $q,
    'type'     => $type,
    'distance' => $distance === $default_distance ? '' : $distance,
    'species'  => $species,
    'sort'     => $sort === 'newest' ? '' : $sort,
];
$exchange_url = fn (array $changes) => query_url('exchange.php', array_merge($filters, $changes));

// ── The listings to show ────────────────────────────────
// Stage B: one prepared query on exchange_listings JOIN users LEFT JOIN plants and plant_species, WHERE status = 'open',
// with (title LIKE ? OR looking_for LIKE ? …) for the search and one AND per filter.

/** Does the term appear in the title, what the owner wants, the species or the owner's username? Empty matches all. */
function listing_matches_search($listing, $term)
{
    if ($term === '') {
        return true;
    }
    $haystack = $listing['title'] . ' ' . $listing['looking_for'] . ' ' . $listing['common_name'] . ' '
              . $listing['scientific_name'] . ' ' . $listing['username'];

    return mb_stripos($haystack, $term) !== false;
}

/**
 * The listings that pass the search and every filter. A function, not a one-off filter, because the no-results
 * state below runs it again with one filter loosened, to offer only a way out that really finds listings.
 */
function filter_listings($listings, $term, $type, $distance, $species)
{
    return array_filter($listings, function ($listing) use ($term, $type, $distance, $species) {
        if (!listing_matches_search($listing, $term)) {
            return false;
        }
        // A listing of type 'either' (swap or free) belongs under both Swap and Free
        if ($type !== '' && !in_array($listing['type'], [$type, 'either'], true)) {
            return false;
        }
        if ($distance !== 'any' && $listing['distance_km'] !== null && $listing['distance_km'] > (int) $distance) {
            return false;
        }
        if ($species !== null && $listing['species_id'] !== $species) {
            return false;
        }
        return true;
    });
}

$listings = filter_listings($open_listings, $term, $type, $distance, $species);

// Ties fall back to the newest, so the order never changes between visits
usort($listings, function ($a, $b) use ($sort) {
    switch ($sort) {
        case 'nearest':
            return [$a['distance_km'], $b['created_at']] <=> [$b['distance_km'], $a['created_at']];
        case 'interest':
            return [$b['interest_count'], $b['created_at']] <=> [$a['interest_count'], $a['created_at']];
        default:
            return $b['created_at'] <=> $a['created_at'];
    }
});

$listing_count = count($listings);

// "7 open listings near Kurunegala" (p17): "near" only while a distance limit is on
$results_text = count_words($listing_count, 'open listing', 'open listings')
              . ($has_town && $distance !== 'any' ? ' near ' . $viewer_town : '');

// ── Active filters ──────────────────────────────────────
// One removable chip per filter; its link is the same address without it. The default distance is not a filter
// a member chose, so it has no chip; a changed one has, and removing it goes back to 25 km.

$active_filters = [];
if ($type !== '') {
    $active_filters[] = ['label' => $type_options[$type], 'href' => $exchange_url(['type' => ''])];
}
if ($filters['distance'] !== '') {
    $active_filters[] = ['label' => distance_label($distance), 'href' => $exchange_url(['distance' => ''])];
}
if ($species !== null) {
    $active_filters[] = ['label' => $species_options[$species], 'href' => $exchange_url(['species' => null])];
}
$clear_filters_url = $exchange_url(['type' => '', 'distance' => '', 'species' => null]);

// "Any distance" has a chip but only widens the search, so it is not one of "these filters" that can hide listings
$has_narrowing_filter = $type !== '' || $species !== null || ($filters['distance'] !== '' && $distance !== 'any');

// ── No listings found ───────────────────────────────────
// The empty-state partial with a way out. A button that leads to another empty page is no way out, so the page
// counts first (the same filter, one choice loosened) and offers the smallest change that really finds listings:
// 1. "Search any distance": keeps the search and the filters. A member's 25 km limit is a filter too, only a hidden
//    one, so this is the way to the listings further away (e.g. "hoya" in Kandy, 42 km).
// 2. "Clear the search": keeps the filters the visitor chose.
// Then "Show all listings": no search, no filters, any distance, the same sort. It always finds every open listing.
// Stage B: each count is a SELECT COUNT(*) with the same WHERE, minus the one condition being loosened.
$no_listings = null;
if ($listing_count === 0) {
    $show_all_url = query_url('exchange.php', ['distance' => $has_town ? 'any' : '', 'sort' => $filters['sort']]);

    $further_count = ($has_town && $distance !== 'any')
        ? count(filter_listings($open_listings, $term, $type, 'any', $species))
        : 0;
    $without_search_count = ($q !== '' && $active_filters)
        ? count(filter_listings($open_listings, '', $type, $distance, $species))
        : 0;

    $actions = [];
    if ($further_count > 0) {
        $actions[] = ['label' => 'Search any distance', 'url' => $exchange_url(['distance' => 'any']), 'icon' => 'map-pin'];
    } elseif ($without_search_count > 0) {
        $actions[] = ['label' => 'Clear the search', 'url' => $exchange_url(['q' => '']), 'icon' => 'search'];
    }
    // Not twice: with no search and no filters, "Search any distance" already is the address of every listing
    if ($open_listings && (!$actions || $actions[0]['url'] !== $show_all_url)) {
        $actions[] = ['label' => 'Show all listings', 'url' => $show_all_url, 'icon' => 'layout-grid'];
    }

    if (!$open_listings) {
        $empty_title = 'No open listings yet';   // only before anyone lists a plant
        $empty_text  = 'New listings appear here as soon as members post them.';
    } else {
        if ($q !== '') {
            $empty_title = 'No listings match "' . $q . '"' . ($has_narrowing_filter ? ' with these filters' : '');
        } elseif ($active_filters) {
            $empty_title = 'No listings match these filters';
        } else {
            $empty_title = 'No open listings near ' . $viewer_town;   // only the 25 km limit can hide every listing
        }

        // Say why: the distance when it is to blame (naming it, as the 25 km has no chip), otherwise the word or the filters
        if ($further_count > 0) {
            $empty_text = 'Nothing within ' . $distance . ' km of ' . $viewer_town . ', but there '
                        . ($further_count === 1 ? 'is 1 listing' : 'are ' . $further_count . ' listings') . ' further away.';
        } elseif ($q !== '') {
            $empty_text = 'Check the spelling or try a shorter word, like a plant name.';
        } else {
            $empty_text = 'Try another type or species.';
        }
    }

    $no_listings = ['title' => $empty_title, 'text' => $empty_text, 'actions' => $actions];
}

// The four-step MVP flow (p17, element 2)
$steps = ['Browse listings', "Tap \u{201C}I'm interested\u{201D}", "Get the owner's contact", 'Swap, then rate'];

$page_title = 'Plant exchange';
$page_css   = 'exchange.css';

include __DIR__ . '/../includes/header.php';
?>
<div class="container page">
  <?php // Title and the listing actions (p17, element 1) ?>
  <div class="exchange-head">
    <div>
      <h1 class="exchange-title">Plant exchange</h1>
      <p class="exchange-lead">Swap cuttings and plants with plant lovers near you. No money involved.</p>
    </div>
    <div class="exchange-actions">
      <?php if ($current_user): ?>
        <a class="btn btn-secondary" href="<?= e(url('my-exchanges.php')) ?>"><?= icon('repeat') ?> My exchanges</a>
        <a class="btn btn-primary" href="<?= e(url('listing-form.php')) ?>"><?= icon('plus') ?> List a plant</a>
      <?php else: ?>
        <?php // A visitor has no exchanges to manage, so one button: listing a plant needs an account ?>
        <a class="btn btn-primary" href="<?= e(url('login.php')) ?>"><?= icon('plus') ?> Log in to list a plant</a>
      <?php endif; ?>
    </div>
  </div>

  <?php // How it works (p17, element 2): an ordered list, because the steps happen in this order ?>
  <section class="exchange-steps" aria-labelledby="steps-title">
    <h2 class="visually-hidden" id="steps-title">How the exchange works</h2>
    <ol class="exchange-steps-list">
      <?php foreach ($steps as $number => $step): ?>
        <li class="exchange-step">
          <span class="exchange-step-number" aria-hidden="true"><?= e($number + 1) ?></span>
          <span><?= e($step) ?></span>
        </li>
      <?php endforeach; ?>
    </ol>
  </section>

  <?php // Filters (p17, element 3): one GET form, so every choice ends up in the address. Without JavaScript the form
        // only sends when Search is pressed; week 6 sends it on every change and moves it into a bottom sheet on a phone. ?>
  <form class="exchange-filters" method="get" action="<?= e(url('exchange.php')) ?>" role="search" aria-label="Filter listings">
    <?php if ($filters['sort'] !== ''): ?>
      <input type="hidden" name="sort" value="<?= e($sort) ?>">
    <?php endif; ?>

    <div class="exchange-search">
      <div class="input-icon exchange-search-box">
        <?= icon('search') ?>
        <label class="visually-hidden" for="exchange-q">Search listings</label>
        <input class="input" type="search" id="exchange-q" name="q" value="<?= e($q) ?>" placeholder="Search listings" maxlength="100">
      </div>
      <button type="submit" class="btn btn-secondary">Search</button>
    </div>

    <?php // The choices: one line from 768 px. On a phone the type gets a line of its own, and the two selects share a
          // line that scrolls sideways on its own, never the whole page (p23), with the Species select peeking in at the edge. ?>
    <div class="exchange-choices">
      <fieldset class="segmented exchange-type">
        <legend class="visually-hidden">Listing type</legend>
        <?php foreach ($type_options as $value => $label): ?>
          <input type="radio" id="type-<?= e($value === '' ? 'all' : $value) ?>" name="type" value="<?= e($value) ?>"<?= $value === $type ? ' checked' : '' ?>>
          <label for="type-<?= e($value === '' ? 'all' : $value) ?>"><?= e($label) ?></label>
        <?php endforeach; ?>
      </fieldset>

      <div class="exchange-filter-row">
        <?php if ($has_town): ?>
          <div class="input-icon exchange-select">
            <?= icon('map-pin') ?>
            <label class="visually-hidden" for="exchange-distance">Distance from <?= e($viewer_town) ?></label>
            <select class="input" id="exchange-distance" name="distance">
              <?php foreach ($distance_choices as $choice): ?>
                <option value="<?= e($choice) ?>"<?= $choice === $distance ? ' selected' : '' ?>>
                  <?= e($choice === 'any' ? 'Any distance' : 'Near ' . $viewer_town . ' · ' . $choice . ' km') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

        <div class="input-icon exchange-select">
          <?= icon('leaf') ?>
          <label class="visually-hidden" for="exchange-species">Species</label>
          <select class="input" id="exchange-species" name="species">
            <option value="">Species: Any</option>
            <?php foreach ($species_options as $species_id => $name): ?>
              <option value="<?= e($species_id) ?>"<?= $species_id === $species ? ' selected' : '' ?>><?= e($name) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>
  </form>

  <?php if (!$has_town): ?>
    <p class="exchange-note">
      <?= icon('map-pin') ?>
      <span><a href="<?= e(url('login.php')) ?>">Log in</a> to see how far away each plant is, measured from the town on your profile.</span>
    </p>
  <?php endif; ?>

  <?php // Result count, active filter chips and the sort, as on Explore ?>
  <div class="results-bar">
    <h2 class="results-count"><?= e($results_text) ?></h2>

    <?php foreach ($active_filters as $filter): ?>
      <a class="chip filter-chip" href="<?= e($filter['href']) ?>">
        <?= e($filter['label']) ?> <?= icon('x') ?><span class="visually-hidden">: remove this filter</span>
      </a>
    <?php endforeach; ?>
    <?php if ($active_filters): ?>
      <a class="results-clear" href="<?= e($clear_filters_url) ?>">Clear all</a>
    <?php endif; ?>

    <details class="menu sort-menu">
      <summary class="btn btn-secondary">Sort: <?= e($sort_options[$sort]) ?> <?= icon('chevron-down') ?></summary>
      <div class="menu-panel menu-panel-right">
        <?php foreach ($sort_options as $value => $label): ?>
          <a class="menu-item" href="<?= e($exchange_url(['sort' => $value === 'newest' ? '' : $value])) ?>"<?= $value === $sort ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
      </div>
    </details>
  </div>

  <?php if ($listings): ?>
    <?php // Listing cards (p17, elements 4 to 7). Contact details are never on a card: only P11 shows them, after "I'm interested". ?>
    <div class="exchange-grid">
      <?php foreach ($listings as $listing): ?>
        <?php partial('listing-card', ['listing' => $listing]); ?>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <section class="panel">
      <?php partial('empty-state', $no_listings); ?>
    </section>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
