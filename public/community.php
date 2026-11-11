<?php
// Community (P09 Explore + P10 Exchange merged, wireframes p16 and p17): one search, three tabs (Plants, Exchange,
// People), a filter sidebar that changes with the tab, a sort, and the cards of the old pages (COM-01, COM-02, COM-03,
// COM-07, UI half). Public: visitors see the same results; listing a plant and "My exchanges" need an account.
// The searches themselves are in includes/community-data.php, one function per tab; this file reads the address,
// runs them and draws the page. Stage F: mock data. Stage B: the same functions run prepared queries.

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mock-data.php';
require_once __DIR__ . '/../includes/community-data.php';

// ── What the visitor asked for, read from the address ───
// community.php?tab=exchange&q=pothos&type=swap&species[]=2&sort=nearest
// Everything is in the address (GET), so a search can be shared and Back works. Anyone can type anything there,
// so every value is checked against what is allowed; a wrong value falls back to its default.

$tabs = ['plants' => 'Plants', 'exchange' => 'Exchange', 'people' => 'People'];
$tab  = get_text('tab');
if (!array_key_exists($tab, $tabs)) {
    $tab = 'plants';
}

$q                = mb_substr(trim(get_text('q')), 0, 100);
$is_member_search = str_starts_with($q, '@');   // "@kandy": on the People tab, look at names only
$term             = trim(ltrim($q, '@'));       // the @ is not part of a username

// species[]=1&species[]=9 arrives as an array (an old exchange.php?species=2 link as one value: (array) handles both).
// Only whole numbers of species that exist are kept; is_string() first, as a hand-made address can nest arrays.
$species_ids = [];
foreach ((array) ($_GET['species'] ?? []) as $value) {
    if (is_string($value) && ctype_digit($value) && isset($mock_species[(int) $value])) {
        $species_ids[] = (int) $value;
    }
}
$species_ids = array_values(array_unique($species_ids));

// "Available for exchange" on the Plants tab: the plants on offer are the Exchange tab's listings, so the switch
// takes the visitor there, keeping the search and the ticked species (the two tabs share species ids).
// 302, not 301: this address is a choice made in a form, not a page that has moved.
if ($tab === 'plants' && get_text('exchange') === '1') {
    header('Location: ' . query_url('community.php', ['tab' => 'exchange', 'q' => $q, 'species' => $species_ids]), true, 302);
    exit;
}

// Distance is measured from the town on the member's profile, never from GPS (p17, UX notes). A visitor has no town:
// distance_km is null for them, so the distance filter and the Nearest sort are left out.
$viewer_town = $current_user['location'] ?? '';
$has_town    = $viewer_town !== '';

// ── Each tab's filters ──────────────────────────────────

$health_options   = ['thriving', 'stable', 'needs_attention', 'sick'];
$type_options     = ['' => 'All', 'swap' => 'Swap', 'free' => 'Free'];
$distance_choices = ['5', '10', '25', '50', 'any'];
$rating_options   = ['' => 'Any', '4' => '4+', '4.5' => '4.5+'];

// The towns worth choosing: where public plants are (Plants tab), where members live (People tab)
$plant_towns = array_values(array_unique(array_column($mock_public_plants, 'location')));
$people_towns = array_values(array_unique(array_column($mock_users, 'location')));
sort($plant_towns);
sort($people_towns);

/** One choice from the address, or $default when it is not one of $allowed. */
function allowed_text($name, array $allowed, $default = '')
{
    $value = get_text($name);

    return in_array($value, $allowed, true) ? $value : $default;
}

// Only the open tab's filters are read; the other tabs use their defaults (for their counts).
$filters = community_default_filters($tab, $has_town);
if ($tab === 'plants') {
    $filters = [
        'species'  => $species_ids,
        'health'   => allowed_text('health', $health_options),
        'location' => allowed_text('location', $plant_towns),
    ];
} elseif ($tab === 'exchange') {
    $filters = [
        'type'     => allowed_text('type', ['swap', 'free']),
        'distance' => $has_town ? allowed_text('distance', $distance_choices, '25') : 'any',
        'species'  => $species_ids,
    ];
} else {
    $filters = [
        'location' => allowed_text('location', $people_towns),
        'rating'   => allowed_text('rating', ['4', '4.5']),
    ];
}
$defaults = community_default_filters($tab, $has_town);

// ── Sort ────────────────────────────────────────────────
// The first option of each tab is its default, so it needs no sort= in the address.

$sort_options = [
    'plants'   => ['likes' => 'Most liked', 'newest' => 'Newest'],
    'exchange' => ['newest' => 'Newest', 'nearest' => 'Nearest', 'interest' => 'Most interested'],
    'people'   => ['rating' => 'Top rated', 'plants' => 'Most plants'],
][$tab];
if (!$has_town) {
    unset($sort_options['nearest']);   // nothing to be near to
}
$default_sort = array_key_first($sort_options);
$sort         = allowed_text('sort', array_keys($sort_options), $default_sort);

// ── Addresses ───────────────────────────────────────────
// Every link starts from the current choices and changes one thing; query_url() leaves out the empty ones and
// a filter at its default value is left out too, so a shared link stays short.

$state = ['tab' => $tab, 'q' => $q];
foreach ($filters as $name => $value) {
    $state[$name] = $value === $defaults[$name] ? '' : $value;
}
$state['sort'] = $sort === $default_sort ? '' : $sort;

$page_url          = fn (array $changes = []) => query_url('community.php', array_merge($state, $changes));
$cleared           = array_map(fn ($value) => is_array($value) ? [] : '', $defaults);   // every filter back to its default
$clear_filters_url = $page_url($cleared);

// Switching tab keeps the search only: the sort and the filters belong to the tab they were chosen on.
$tab_url = fn ($name) => query_url('community.php', ['tab' => $name, 'q' => $q]);

// ── The searches ────────────────────────────────────────
// The open tab runs with its filters, the other two with their defaults: each count is what that tab would show
// for this search. The People tab also counts the plants each member grows that match the search.

/** Most liked first; equal likes fall back to the name, so the order never changes between visits. For usort(). */
function most_liked_first($a, $b)
{
    return [$b['like_count'], $a['nickname']] <=> [$a['like_count'], $b['nickname']];
}

$plant_filters    = $tab === 'plants' ? $filters : community_default_filters('plants');
$listing_filters  = $tab === 'exchange' ? $filters : community_default_filters('exchange', $has_town);
$people_filters   = $tab === 'people' ? $filters : community_default_filters('people');

$plants        = community_plants($mock_public_plants, $term, $plant_filters);
$listings      = community_listings($mock_listings, $term, $listing_filters);
$search_plants = community_plants($mock_public_plants, $term, community_default_filters('plants'));   // the search alone
$people        = community_people($mock_users, $search_plants, $term, $is_member_search, $people_filters);

$counts = ['plants' => count($plants), 'exchange' => count($listings), 'people' => count($people)];

// Ties fall back to a fixed order, so the cards never swap places between visits
usort($plants, fn ($a, $b) => $sort === 'newest' && $tab === 'plants'
    ? $b['id'] <=> $a['id']   // plants has no created_at column; ids grow with every new row
    : most_liked_first($a, $b));

usort($listings, function ($a, $b) use ($sort, $tab) {
    $sort = $tab === 'exchange' ? $sort : 'newest';
    switch ($sort) {
        case 'nearest':
            return [$a['distance_km'], $b['created_at']] <=> [$b['distance_km'], $a['created_at']];
        case 'interest':
            return [$b['interest_count'], $b['created_at']] <=> [$a['interest_count'], $a['created_at']];
        default:
            return $b['created_at'] <=> $a['created_at'];
    }
});

// People: the members with the most matching plants first, then the chosen sort
usort($people, function ($a, $b) use ($sort, $tab) {
    $by_plants = $tab === 'people' && $sort === 'plants';
    $first     = $by_plants ? 'plant_count' : 'rating_avg';
    $second    = $by_plants ? 'rating_avg' : 'plant_count';

    return [$b['match_count'], $b[$first] ?? 0, $b[$second] ?? 0, $a['username']]
       <=> [$a['match_count'], $a[$first] ?? 0, $a[$second] ?? 0, $b['username']];
});

// ── The sidebar's species lists, with a count beside each species ──
$species_list = [];
if ($tab === 'plants') {
    $species_list = community_species_list(community_plants($mock_public_plants, $term, $filters, true), $species_ids, $mock_species);
} elseif ($tab === 'exchange') {
    $species_list = community_species_list(community_listings($mock_listings, $term, $filters, true), $species_ids, $mock_species);
}
// The first five are shown, the rest fold under "+ N more", which opens by itself when one of them is ticked
$species_shown = array_slice($species_list, 0, 5);
$species_more  = array_slice($species_list, 5);
$more_open     = in_array(true, array_column($species_more, 'checked'), true);

// ── Active filters: one removable chip each ─────────────
// Its link is the same address without that one filter. The member's default 25 km is not a choice they made,
// so it has no chip; a changed distance has, and removing it goes back to 25 km.

$active_filters = [];
foreach ($tab === 'people' ? [] : $species_ids as $species_id) {
    $active_filters[] = [
        'label' => $mock_species[$species_id]['common_name'],
        'href'  => $page_url(['species' => array_values(array_diff($species_ids, [$species_id]))]),
    ];
}
if (($filters['health'] ?? '') !== '') {
    $active_filters[] = ['label' => health_label($filters['health']), 'href' => $page_url(['health' => ''])];
}
if (($filters['type'] ?? '') !== '') {
    $active_filters[] = ['label' => $type_options[$filters['type']], 'href' => $page_url(['type' => ''])];
}
if ($tab === 'exchange' && $state['distance'] !== '') {
    $distance_label   = $filters['distance'] === 'any' ? 'Any distance' : 'Within ' . $filters['distance'] . ' km';
    $active_filters[] = ['label' => $distance_label, 'href' => $page_url(['distance' => ''])];
}
if (($filters['location'] ?? '') !== '') {
    $active_filters[] = ['label' => $filters['location'], 'href' => $page_url(['location' => ''])];
}
if (($filters['rating'] ?? '') !== '') {
    $active_filters[] = ['label' => 'Rated ' . $rating_options[$filters['rating']], 'href' => $page_url(['rating' => ''])];
}
$active_count = count($active_filters);

// ── The results line and the empty states ───────────────

$count = $counts[$tab];
if ($tab === 'plants') {
    $results_text = count_words($count, 'plant', 'plants');
} elseif ($tab === 'exchange') {
    // "7 open listings near Kurunegala" (p17): "near" only while a distance limit is on
    $results_text = count_words($count, 'open listing', 'open listings')
                  . ($has_town && $filters['distance'] !== 'any' ? ' near ' . $viewer_town : '');
} else {
    $results_text = count_words($count, 'person', 'people');
}

// Nothing found: a friendly line and a way out. "Clear filters" when filters are on, "Clear the search" when there
// is a search word. On the Exchange tab the member's hidden 25 km limit can be the reason, so when listings exist
// further away, "Search any distance" comes first (e.g. "hoya" in Kandy, 42 km away).
$empty          = null;
$popular_plants = [];
if ($count === 0) {
    $nouns   = ['plants' => 'plants', 'exchange' => 'listings', 'people' => 'members'];
    $actions = [];

    if ($tab === 'exchange' && $has_town && $filters['distance'] !== 'any'
        && community_listings($mock_listings, $term, array_merge($filters, ['distance' => 'any']))) {
        $actions[] = ['label' => 'Search any distance', 'url' => $page_url(['distance' => 'any']), 'icon' => 'map-pin'];
    }
    if ($active_count > 0) {
        $actions[] = ['label' => 'Clear filters', 'url' => $clear_filters_url, 'icon' => 'sliders-horizontal'];
    }
    if ($q !== '') {
        $actions[] = ['label' => 'Clear the search', 'url' => $page_url(['q' => '']), 'icon' => 'search'];
    }

    if ($q !== '') {
        $title = 'No ' . $nouns[$tab] . ' match "' . $q . '"' . ($active_count > 0 ? ' with these filters' : '');
    } elseif ($active_count > 0) {
        $title = 'No ' . $nouns[$tab] . ' match these filters';
    } elseif ($tab === 'exchange' && $has_town) {
        $title = 'No open listings near ' . $viewer_town;
    } else {
        $title = ['plants' => 'No public plants yet', 'exchange' => 'No open listings yet', 'people' => 'No members yet'][$tab];
    }

    $texts = [
        'plants'   => 'Try another word or fewer filters. Until then, here are some popular plants.',
        'exchange' => 'Try another word, type or species. New listings appear as soon as members post them.',
        'people'   => 'Check the spelling, or try part of a name, a town or an @username.',
    ];
    $text = $actions ? $texts[$tab] : 'Check back soon: the community grows every day.';
    if ($tab === 'plants' && !$actions) {
        $text = 'Plants appear here when members make them public.';
    }

    $empty = ['title' => $title, 'text' => $text, 'actions' => $actions];

    // Never a blank page on the Plants tab: the most-liked public plants, whatever the search and filters said
    if ($tab === 'plants') {
        $popular_plants = $mock_public_plants;
        usort($popular_plants, 'most_liked_first');
        $popular_plants = array_slice($popular_plants, 0, 6);
    }
}

// "Plant lovers matching …" above the plant results (p16, element 6): only for a search, three at most
$people_row = ($tab === 'plants' && $term !== '') ? array_slice($people, 0, 3) : [];

// The four-step exchange flow (p17, element 2)
$steps = ['Browse listings', "Tap \u{201C}I'm interested\u{201D}", "Get the owner's contact", 'Swap, then rate'];

$page_title = $q !== '' ? 'Search: ' . $q : 'Community';
$page_css   = 'community.css';
$page_js    = 'community.js';

include __DIR__ . '/../includes/header.php';
?>
<?php // The top band: title and exchange buttons, the one search box, the three tabs ?>
<section class="community-top">
  <div class="container">
    <div class="community-head">
      <div>
        <h1 class="community-title">Community</h1>
        <p class="community-lead">Discover plants, swap cuttings and meet growers near you.</p>
      </div>
      <div class="community-actions">
        <?php if ($current_user): ?>
          <a class="btn btn-secondary" href="<?= e(url('my-exchanges.php')) ?>"><?= icon('repeat') ?> My exchanges</a>
          <a class="btn btn-primary" href="<?= e(url('listing-form.php')) ?>"><?= icon('plus') ?> List a plant</a>
        <?php else: ?>
          <?php // A visitor has no exchanges to manage, so one button: listing a plant needs an account ?>
          <a class="btn btn-primary" href="<?= e(url('login.php')) ?>"><?= icon('plus') ?> Log in to list a plant</a>
        <?php endif; ?>
      </div>
    </div>

    <?php // One GET form for the search AND the sidebar: the sidebar's fields sit further down the page and join it
          // with form="community-form", so Search and Apply both send the word and every filter together. ?>
    <form class="community-search" id="community-form" method="get" action="<?= e(url('community.php')) ?>" role="search">
      <?= preview_field() ?>
      <input type="hidden" name="tab" value="<?= e($tab) ?>">
      <?php if ($state['sort'] !== ''): ?>
        <input type="hidden" name="sort" value="<?= e($sort) ?>">
      <?php endif; ?>

      <div class="input-icon community-search-box">
        <?= icon('search') ?>
        <label class="visually-hidden" for="community-q">Search plants, species, listings or @usernames</label>
        <input class="input" type="search" id="community-q" name="q" value="<?= e($q) ?>" placeholder="Search plants, species, listings or @usernames" maxlength="100">
      </div>
      <button type="submit" class="btn btn-primary">Search</button>
    </form>

    <?php // Links, not buttons: each tab has its own address, so Back and shared links work ?>
    <nav class="community-tabs" aria-label="Community sections">
      <?php foreach ($tabs as $name => $label): ?>
        <a class="community-tab" href="<?= e($tab_url($name)) ?>"<?= $name === $tab ? ' aria-current="page"' : '' ?>>
          <?= e($label) ?> <span class="chip-count"><?= e($counts[$name]) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
  </div>
</section>

<div class="container page community-body">
  <?php // Filters. A column on the left from 900 px; below that, a panel that slides in from the left when "Filters"
        // is pressed (community.js). Without JavaScript the Filters link still opens it: its href is #community-filters,
        // and community.css shows the panel while it is the :target of the address. ?>
  <aside class="community-filters" id="community-filters" aria-labelledby="filters-title" tabindex="-1">
    <div class="filter-head">
      <h2 class="filter-title" id="filters-title">Filters</h2>
      <?php if ($active_count > 0): ?>
        <a class="results-clear" href="<?= e($clear_filters_url) ?>">Clear all</a>
      <?php endif; ?>
      <a class="icon-btn filters-close" href="#" data-drawer-close aria-label="Close filters"><?= icon('x') ?></a>
    </div>

    <div class="filters-body">
      <?php if ($tab === 'plants' || $tab === 'exchange'): ?>
        <?php if ($tab === 'exchange'): ?>
          <?php // Type: one choice, radio buttons drawn as chips. A "Swap or free" listing counts as both. ?>
          <fieldset class="filter-group">
            <legend class="label">Type</legend>
            <div class="filter-chips">
              <?php foreach ($type_options as $value => $label): ?>
                <label class="chip-choice">
                  <input type="radio" name="type" value="<?= e($value) ?>" form="community-form"<?= $value === $filters['type'] ? ' checked' : '' ?>>
                  <span class="chip"><?= e($label) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </fieldset>

          <div class="filter-group">
            <?php if ($has_town): ?>
              <label class="label" for="filter-distance">Distance</label>
              <div class="input-icon">
                <?= icon('map-pin') ?>
                <select class="input" id="filter-distance" name="distance" form="community-form">
                  <?php foreach ($distance_choices as $choice): ?>
                    <option value="<?= e($choice) ?>"<?= $choice === $filters['distance'] ? ' selected' : '' ?>>
                      <?= e($choice === 'any' ? 'Any distance' : 'Near ' . $viewer_town . ' · ' . $choice . ' km') ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            <?php else: ?>
              <p class="label">Distance</p>
              <p class="filter-note">
                <a href="<?= e(url('login.php')) ?>">Log in</a> to see how far away each plant is, measured from the town on your profile.
              </p>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php // Species: tick any number. The name species[] makes PHP collect every ticked box into one array. ?>
        <fieldset class="filter-group">
          <legend class="label">Species</legend>
          <?php if (!$species_list): ?>
            <p class="filter-note">No species match this search.</p>
          <?php endif; ?>
          <?php foreach ([$species_shown, $species_more] as $part => $items): ?>
            <?php if ($part === 1 && $items): ?>
              <details class="filter-more"<?= $more_open ? ' open' : '' ?>>
                <summary>+ <?= e(count($items)) ?> more</summary>
            <?php endif; ?>
            <?php foreach ($items as $item): ?>
              <label class="check filter-check">
                <input type="checkbox" name="species[]" value="<?= e($item['id']) ?>" form="community-form"<?= $item['checked'] ? ' checked' : '' ?>>
                <span class="filter-check-name"><?= e($item['name']) ?></span>
                <span class="filter-check-count"><?= e($item['count']) ?></span>
              </label>
            <?php endforeach; ?>
            <?php if ($part === 1 && $items): ?>
              </details>
            <?php endif; ?>
          <?php endforeach; ?>
        </fieldset>
      <?php endif; ?>

      <?php if ($tab === 'plants'): ?>
        <?php // Health: one choice, radio buttons drawn as chips (p16). "Any" is how a choice is taken back. ?>
        <fieldset class="filter-group">
          <legend class="label">Health</legend>
          <div class="filter-chips">
            <?php foreach (array_merge([''], $health_options) as $option): ?>
              <label class="chip-choice">
                <input type="radio" name="health" value="<?= e($option) ?>" form="community-form"<?= $option === $filters['health'] ? ' checked' : '' ?>>
                <span class="chip"><?= e($option === '' ? 'Any' : health_label($option)) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>
      <?php endif; ?>

      <?php if ($tab === 'plants' || $tab === 'people'): ?>
        <div class="filter-group">
          <label class="label" for="filter-location">Location</label>
          <div class="input-icon">
            <?= icon('map-pin') ?>
            <select class="input" id="filter-location" name="location" form="community-form">
              <option value="">All locations</option>
              <?php foreach ($tab === 'plants' ? $plant_towns : $people_towns as $town): ?>
                <option value="<?= e($town) ?>"<?= $town === $filters['location'] ? ' selected' : '' ?>><?= e($town) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($tab === 'plants'): ?>
        <?php // The plants on offer are the Exchange tab's listings: switching this on goes there (see the top of the file).
              // community.js sends the form as soon as it changes; without JavaScript, Apply does. ?>
        <div class="filter-group">
          <label class="toggle">
            <input type="checkbox" name="exchange" value="1" form="community-form" data-submit-on-change>
            Available for exchange
          </label>
          <p class="filter-note">Opens the Exchange tab.</p>
        </div>
      <?php endif; ?>

      <?php if ($tab === 'people'): ?>
        <fieldset class="filter-group">
          <legend class="label">Minimum rating</legend>
          <div class="filter-chips">
            <?php foreach ($rating_options as $value => $label): ?>
              <label class="chip-choice">
                <input type="radio" name="rating" value="<?= e($value) ?>" form="community-form"<?= $value === $filters['rating'] ? ' checked' : '' ?>>
                <span class="chip"><?php if ($value !== ''): ?><?= icon('star') ?><?php endif; ?> <?= e($label) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>
      <?php endif; ?>
    </div>

    <div class="filters-foot">
      <button type="submit" form="community-form" class="btn btn-primary filter-apply">Apply filters</button>
    </div>
  </aside>
  <?php // The dimmed page behind the open panel on a phone; a tap on it closes the panel ?>
  <div class="filters-scrim" data-drawer-close hidden></div>

  <div class="community-results">
    <?php if ($tab === 'exchange'): ?>
      <?php // How it works (p17, element 2). The × hides it for good on this browser (community.js, localStorage); it
            // stays hidden until the script runs, because without JavaScript it could not remember anything. ?>
      <section class="exchange-steps" aria-labelledby="steps-title" data-dismissible="exchange-steps">
        <div class="exchange-steps-head">
          <h2 class="label exchange-steps-title" id="steps-title">How the exchange works</h2>
          <button type="button" class="icon-btn exchange-steps-close" data-dismiss aria-label="Hide how the exchange works" hidden><?= icon('x') ?></button>
        </div>
        <ol class="exchange-steps-list">
          <?php foreach ($steps as $number => $step): ?>
            <li class="exchange-step">
              <span class="exchange-step-number" aria-hidden="true"><?= e($number + 1) ?></span>
              <span><?= e($step) ?></span>
            </li>
          <?php endforeach; ?>
        </ol>
      </section>
    <?php endif; ?>

    <?php // Count and sort on one row, with the active filter chips between them. On a phone "Filters" leads the row. ?>
    <div class="results-bar">
      <a class="btn btn-secondary filters-open" href="#community-filters" data-drawer-open aria-controls="community-filters" aria-expanded="false">
        <?= icon('sliders-horizontal') ?> Filters
        <?php if ($active_count > 0): ?>
          <span class="filter-toggle-count"><?= e($active_count) ?><span class="visually-hidden"> active</span></span>
        <?php endif; ?>
      </a>

      <h2 class="results-count"><?= e($results_text) ?></h2>

      <?php foreach ($active_filters as $filter): ?>
        <a class="chip filter-chip" href="<?= e($filter['href']) ?>">
          <?= e($filter['label']) ?> <?= icon('x') ?><span class="visually-hidden">: remove this filter</span>
        </a>
      <?php endforeach; ?>
      <?php if ($active_count > 1): ?>
        <a class="results-clear" href="<?= e($clear_filters_url) ?>">Clear all</a>
      <?php endif; ?>

      <?php // A <details> dropdown of links: it opens without JavaScript, and each sort has its own address ?>
      <details class="menu sort-menu">
        <summary class="btn btn-secondary"><span class="sort-word">Sort:</span> <?= e($sort_options[$sort]) ?> <?= icon('chevron-down') ?></summary>
        <div class="menu-panel menu-panel-right">
          <?php foreach ($sort_options as $value => $label): ?>
            <a class="menu-item" href="<?= e($page_url(['sort' => $value === $default_sort ? '' : $value])) ?>"<?= $value === $sort ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
          <?php endforeach; ?>
        </div>
      </details>
    </div>

    <?php if ($people_row): ?>
      <?php // Plant lovers matching the search (p16, element 6). On a phone the cards scroll sideways in their own strip. ?>
      <section class="people-row" aria-labelledby="people-row-title">
        <div class="people-row-head">
          <h2 class="community-section-title" id="people-row-title">Plant lovers matching "<?= e($q) ?>"</h2>
          <?php if ($counts['people'] > count($people_row)): ?>
            <a class="panel-link" href="<?= e($tab_url('people')) ?>">See all <?= e($counts['people']) ?> people</a>
          <?php endif; ?>
        </div>
        <div class="people-row-list">
          <?php foreach ($people_row as $member): ?>
            <?php partial('person-card', ['member' => $member, 'compact' => true]); ?>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($empty): ?>
      <section class="panel">
        <?php partial('empty-state', $empty); ?>
      </section>

      <?php if ($popular_plants): ?>
        <section class="popular-plants" aria-labelledby="popular-title">
          <h2 class="community-section-title" id="popular-title">Popular plants</h2>
          <p class="popular-text">The most-liked public plants in the community.</p>
          <div class="community-grid">
            <?php foreach ($popular_plants as $plant): ?>
              <?php partial('plant-card', ['plant' => $plant, 'community' => true]); ?>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>
    <?php elseif ($tab === 'plants'): ?>
      <div class="community-grid">
        <?php foreach ($plants as $plant): ?>
          <?php partial('plant-card', ['plant' => $plant, 'community' => true]); ?>
        <?php endforeach; ?>
      </div>
    <?php elseif ($tab === 'exchange'): ?>
      <?php // Contact details are never on a card: only Listing Detail shows them, after "I'm interested" ?>
      <div class="community-grid">
        <?php foreach ($listings as $listing): ?>
          <?php partial('listing-card', ['listing' => $listing]); ?>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="people-grid">
        <?php foreach ($people as $member): ?>
          <?php partial('person-card', ['member' => $member]); ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
