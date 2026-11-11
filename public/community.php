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

// Exchange comes first and is the default: swapping is what most members come to Community for (Ranshitha, 11 Nov).
// The tabs are drawn in this order.
$tabs = ['exchange' => 'Exchange', 'plants' => 'Plants', 'people' => 'People'];
$tab  = get_text('tab');
if (!array_key_exists($tab, $tabs)) {
    $tab = 'exchange';
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

// Distance is measured from a town, never from GPS (p17, UX notes): "Near" is any town in the towns list. A member starts
// on the town of their profile; a visitor on none, so there is no distance limit until they choose one (Ranshitha, 11 Nov).
$viewer_town = $current_user['location'] ?? '';
$town_names  = array_keys($mock_towns);
sort($town_names);

// ── Each tab's filters ──────────────────────────────────

$type_options   = ['' => 'All', 'swap' => 'Swap', 'free' => 'Free'];
// The distance slider: 5 to 100 km in steps of 5. 100, the far right end, means "any distance".
$distance_steps = array_map('strval', range(5, 100, 5));
$rating_options = ['' => 'Any', '4' => '4+', '4.5' => '4.5+'];


/** One choice from the address, or $default when it is not one of $allowed. */
function allowed_text($name, array $allowed, $default = '')
{
    $value = get_text($name);

    return in_array($value, $allowed, true) ? $value : $default;
}

/** Ticked boxes from the address (category[]=indoor&category[]=outdoor): only the allowed values, each once. */
function allowed_list($name, array $allowed)
{
    $values = array_filter((array) ($_GET[$name] ?? []), fn ($value) => is_string($value) && in_array($value, $allowed, true));

    return array_values(array_unique($values));
}

// Only the open tab's filters are read; the other tabs use their defaults (for their counts).
$filters = community_default_filters($tab, $viewer_town);
if ($tab === 'plants') {
    $filters = [
        'species'  => $species_ids,
        // Plants and People start on any distance, so everyone and every plant shows until someone narrows it
        'near'     => allowed_text('near', $town_names, $viewer_town),
        'distance' => allowed_text('distance', $distance_steps, '100'),
    ];
} elseif ($tab === 'exchange') {
    $filters = [
        'type'     => allowed_text('type', ['swap', 'free']),
        // A member always has a town (no empty choice in their list); a visitor's '' means "no town chosen yet"
        'near'     => allowed_text('near', $town_names, $viewer_town),
        'distance' => allowed_text('distance', $distance_steps, '25'),
        'category' => allowed_list('category', array_keys(listing_categories())),
        'light'    => allowed_list('light', array_keys(light_levels())),
        'water'    => allowed_list('water', array_keys(water_needs())),
        'species'  => $species_ids,
    ];
} else {
    $filters = [
        'near'     => allowed_text('near', $town_names, $viewer_town),
        'distance' => allowed_text('distance', $distance_steps, '100'),
        'rating'   => allowed_text('rating', ['4', '4.5']),
    ];
}
$defaults = community_default_filters($tab, $viewer_town);
$near     = $filters['near'];   // the town this tab measures from ('' = none chosen yet: a visitor)

// ── Sort ────────────────────────────────────────────────
// The first option of each tab is its default, so it needs no sort= in the address.

$sort_options = [
    'plants'   => ['likes' => 'Most liked', 'newest' => 'Newest'],
    'exchange' => ['newest' => 'Newest', 'nearest' => 'Nearest', 'interest' => 'Most interested'],
    'people'   => ['rating' => 'Top rated', 'plants' => 'Most plants'],
][$tab];
if ($tab === 'exchange' && $near === '') {
    unset($sort_options['nearest']);   // no town chosen: nothing to be near to
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
$listing_filters  = $tab === 'exchange' ? $filters : community_default_filters('exchange', $viewer_town);
$people_filters   = $tab === 'people' ? $filters : community_default_filters('people');

// Each listing's distance from the "Near" town, for the slider, the Nearest sort and the cards ("Kandy · 32 km").
// Stage B: the Haversine sum in the query, with the chosen town's coordinates as two parameters.
// The same for a plant (its owner's town) and a member (their town), from each tab's own "Near".
/** $rows with distance_km: how far each row's town is from $near (null when no town is chosen). */
function with_distance(array $rows, $near, array $towns)
{
    return array_map(fn ($row) => array_merge($row, [
        'distance_km' => $near !== '' ? town_distance_km($towns, $near, $row['location']) : null,
    ]), $rows);
}
$listing_rows = with_distance($mock_listings, $listing_filters['near'], $mock_towns);
$plant_rows   = with_distance($mock_public_plants, $plant_filters['near'], $mock_towns);
$people_rows  = with_distance($mock_users, $people_filters['near'], $mock_towns);

$plants        = community_plants($plant_rows, $term, $plant_filters);
$listings      = community_listings($listing_rows, $term, $listing_filters);
$search_plants = community_plants($mock_public_plants, $term, community_default_filters('plants'));   // the search alone
$people        = community_people($people_rows, $search_plants, $term, $is_member_search, $people_filters);

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
    $species_list = community_species_list(community_plants($plant_rows, $term, $filters, true), $species_ids, $mock_species);
} elseif ($tab === 'exchange') {
    $species_list = community_species_list(community_listings($listing_rows, $term, $filters, 'species'), $species_ids, $mock_species);
}

// ── The Exchange sidebar's Category, Light and Watering ticks, each with its count ──
// Each group is counted with every filter except its own (the $skip argument), like the species above.
$check_groups = [];
if ($tab === 'exchange') {
    $groups = [
        'category' => ['field' => 'categories',  'options' => listing_categories()],
        'light'    => ['field' => 'light_level', 'options' => light_levels()],
        'water'    => ['field' => 'water_need',  'options' => water_needs()],
    ];
    foreach ($groups as $name => $group) {
        $option_counts = community_option_counts(community_listings($listing_rows, $term, $filters, $name), $group['field'], $group['options']);
        foreach ($group['options'] as $value => $option) {
            $check_groups[$name][] = [
                'value'   => $value,
                'name'    => is_array($option) ? $option['label'] : $option,
                'icon'    => is_array($option) ? $option['icon'] : null,
                'count'   => $option_counts[$value],
                'checked' => in_array($value, $filters[$name], true),
            ];
        }
    }
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
if (($filters['type'] ?? '') !== '') {
    $active_filters[] = ['label' => $type_options[$filters['type']], 'href' => $page_url(['type' => ''])];
}
if ($state['near'] !== '') {
    // Another town than the member's own (or any town, for a visitor); removing it goes back to the default
    $active_filters[] = ['label' => 'Near ' . $near, 'href' => $page_url(['near' => ''])];
}
if ($near !== '' && $state['distance'] !== '') {
    $distance_label   = $filters['distance'] === '100' ? 'Any distance' : 'Within ' . $filters['distance'] . ' km';
    $active_filters[] = ['label' => $distance_label, 'href' => $page_url(['distance' => ''])];
}
foreach ($check_groups as $name => $items) {
    foreach ($items as $item) {
        if ($item['checked']) {
            $label = $name === 'water' ? $item['name'] . ' watering' : $item['name'];   // "Low watering", not just "Low"
            $active_filters[] = [
                'label' => $label,
                'href'  => $page_url([$name => array_values(array_diff($filters[$name], [$item['value']]))]),
            ];
        }
    }
}
if (($filters['rating'] ?? '') !== '') {
    $active_filters[] = ['label' => 'Rated ' . $rating_options[$filters['rating']], 'href' => $page_url(['rating' => ''])];
}
$active_count = count($active_filters);   // the filters only: the number on the Filters button

// The chips above the results: the search word first ("monstera" ×), then the filters.
// "Clear all" there clears the word and the filters; the sidebar's "Clear all" clears the filters only.
$chips = $active_filters;
if ($q !== '') {
    array_unshift($chips, ['label' => "\u{201C}" . $q . "\u{201D}", 'href' => $page_url(['q' => '']), 'remove' => 'remove this search']);
}
$clear_chips_url = $page_url(array_merge($cleared, ['q' => '']));

// ── The results line and the empty states ───────────────

$count = $counts[$tab];
if ($tab === 'plants') {
    $results_text = count_words($count, 'plant', 'plants')
                  . ($near !== '' && $filters['distance'] !== '100' ? ' near ' . $near : '');
} elseif ($tab === 'exchange') {
    // "7 open listings near Kurunegala" (p17): "near" only while a distance limit is on
    $results_text = count_words($count, 'open listing', 'open listings')
                  . ($near !== '' && $filters['distance'] !== '100' ? ' near ' . $near : '');
} else {
    $results_text = count_words($count, 'person', 'people')
                  . ($near !== '' && $filters['distance'] !== '100' ? ' near ' . $near : '');
}

// Nothing found: a friendly line and a way out. "Clear filters" when filters are on, "Clear the search" when there
// is a search word. On the Exchange tab the member's hidden 25 km limit can be the reason, so when listings exist
// further away, "Search any distance" comes first (e.g. "hoya" in Kandy, 42 km away).
$empty          = null;
$popular_plants = [];
if ($count === 0) {
    $nouns   = ['plants' => 'plants', 'exchange' => 'listings', 'people' => 'members'];
    $actions = [];

    // Would any distance find something? Then "Search any distance" is the way out.
    $any_distance  = array_merge($filters, ['distance' => '100']);
    $found_further = [
        'plants'   => fn () => community_plants($plant_rows, $term, $any_distance),
        'exchange' => fn () => community_listings($listing_rows, $term, $any_distance),
        'people'   => fn () => community_people($people_rows, $search_plants, $term, $is_member_search, $any_distance),
    ][$tab];
    if ($near !== '' && $filters['distance'] !== '100' && $found_further()) {
        $actions[] = ['label' => 'Search any distance', 'url' => $page_url(['distance' => '100']), 'icon' => 'map-pin'];
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
    } elseif ($near !== '' && $filters['distance'] !== '100') {
        $title = 'No ' . $nouns[$tab] . ' near ' . $near;
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

// ── 12 per page, with "Load more" ───────────────────────
// ?page=3 shows the first 36, not only the third twelve: without JavaScript "Load more" is a plain link to the next
// page number, and the reader still sees everything above it. community.js fetches that same address and appends only
// the new cards. page= is not kept in the filter, sort and tab links, so any new choice starts again at the top.
// Stage B: LIMIT ? OFFSET ? on each tab's query, plus the COUNT(*) that "Showing 12 of 19" needs.
$per_page    = 12;
$rows        = ['plants' => $plants, 'exchange' => $listings, 'people' => $people][$tab];
$last_page   = max(1, (int) ceil($count / $per_page));
$page_text   = get_text('page');
$page_number = ctype_digit($page_text) ? min(max(1, (int) $page_text), $last_page) : 1;
$shown_rows  = array_slice($rows, 0, $page_number * $per_page);
$has_more    = count($shown_rows) < $count;
$next_url    = $page_url(['page' => $page_number + 1]);
$item_words  = ['plants' => ['plant', 'plants'], 'exchange' => ['listing', 'listings'], 'people' => ['person', 'people']][$tab];
$showing     = 'Showing ' . count($shown_rows) . ' of ' . count_words($count, $item_words[0], $item_words[1]);

// "Plant lovers matching …" above the plant results (p16, element 6): only for a search, three at most
$people_row = ($tab === 'plants' && $term !== '') ? array_slice($people, 0, 3) : [];

// The search box says what the open tab searches. The placeholder is short, so it is never cut off on a phone;
// the label (read by screen readers) says everything the search looks through.
$search_placeholder = ['plants' => 'Search plants', 'exchange' => 'Search listings', 'people' => 'Search people'][$tab];
$search_label       = [
    'plants'   => 'Search plants by name, species or @username',
    'exchange' => 'Search listings by title, species or @username',
    'people'   => 'Search people by name, town or @username',
][$tab];

// The four-step exchange flow (p17, element 2)
$steps = ['Browse listings', "Tap \u{201C}I'm interested\u{201D}", "Get the owner's contact", 'Swap, then rate'];

$page_title = $q !== '' ? 'Search: ' . $q : 'Community';
$page_css   = 'community.css';
$page_js    = 'community.js';

include __DIR__ . '/../includes/header.php';
?>
<?php // The top band: title and exchange buttons, the three tabs, then the search box for the open tab ?>
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
          <?php // Hidden on a phone (community.css): the bottom bar's round + button does the same job there ?>
          <a class="btn btn-primary community-list-btn" href="<?= e(url('listing-form.php')) ?>"><?= icon('plus') ?> List a plant</a>
        <?php else: ?>
          <?php // A visitor has no exchanges to manage, so one button: listing a plant needs an account ?>
          <a class="btn btn-primary" href="<?= e(url('login.php')) ?>"><?= icon('plus') ?> Log in to list a plant</a>
        <?php endif; ?>
      </div>
    </div>

    <?php // The tabs come before the search box, because the open tab decides what the search looks through.
          // Links, not buttons (and not role="tab"): each tab has its own address, so Back and shared links work. ?>
    <nav class="community-tabs" aria-label="Community sections" data-swap="tabs">
      <?php foreach ($tabs as $name => $label): ?>
        <a class="community-tab" href="<?= e($tab_url($name)) ?>"<?= $name === $tab ? ' aria-current="page"' : '' ?>>
          <?= e($label) ?> <span class="chip-count"><?= e($counts[$name]) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>

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
        <label class="visually-hidden" for="community-q"><?= e($search_label) ?></label>
        <input class="input" type="search" id="community-q" name="q" value="<?= e($q) ?>" placeholder="<?= e($search_placeholder) ?>" maxlength="100">
      </div>
      <button type="submit" class="btn btn-primary">Search</button>
    </form>
  </div>
</section>

<div class="container page community-body">
  <?php // Filters. A column on the left from 900 px; below that, a panel that slides in from the left when "Filters"
        // is pressed (community.js). Without JavaScript the Filters link still opens it: its href is #community-filters,
        // and community.css shows the panel while it is the :target of the address. ?>
  <aside class="community-filters" id="community-filters" aria-labelledby="filters-title" tabindex="-1">
    <div class="filter-head">
      <h2 class="filter-title" id="filters-title">Filters</h2>
      <?php // data-swap="…": a part that community.js replaces with the same part of the new page after a filter changes ?>
      <span class="filter-head-clear" data-swap="filters-clear">
        <?php if ($active_count > 0): ?>
          <a class="results-clear" href="<?= e($clear_filters_url) ?>">Clear all</a>
        <?php endif; ?>
      </span>
      <a class="icon-btn filters-close" href="#" data-drawer-close aria-label="Close filters"><?= icon('x') ?></a>
    </div>

    <?php // The counts change with every filter, so the whole body is swapped (focus and folded groups are kept by community.js).
          // Each group is a <details>: its title folds it open and shut without JavaScript; all start open. ?>
    <div class="filters-body" data-swap="filters">
      <?php if ($tab === 'exchange'): ?>
        <?php // Type: one choice, radio buttons drawn as chips. A "Swap or free" listing counts as both. ?>
        <details class="filter-section" data-section="type" open>
          <summary class="filter-section-head"><?= icon('repeat') ?> <span>Type</span> <span class="filter-section-chevron"><?= icon('chevron-down') ?></span></summary>
          <fieldset class="filter-group">
            <legend class="visually-hidden">Listing type</legend>
            <div class="filter-chips">
              <?php foreach ($type_options as $value => $label): ?>
                <label class="chip-choice">
                  <input type="radio" name="type" value="<?= e($value) ?>" form="community-form"<?= $value === $filters['type'] ? ' checked' : '' ?>>
                  <span class="chip"><?= e($label) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </fieldset>
        </details>

        <?php partial('near-filter', ['near' => $near, 'distance' => $filters['distance'], 'town_names' => $town_names,
                                      'can_be_empty' => $viewer_town === '']); ?>

        <?php // Category, Light and Watering: what owners file their listing under on Create Listing. Tick any number. ?>
        <?php foreach (['category' => ['Category', 'layout-grid'], 'light' => ['Light', 'sun'], 'water' => ['Watering', 'droplet']] as $name => [$title, $section_icon]): ?>
          <details class="filter-section" data-section="<?= e($name) ?>" open>
            <summary class="filter-section-head"><?= icon($section_icon) ?> <span><?= e($title) ?></span> <span class="filter-section-chevron"><?= icon('chevron-down') ?></span></summary>
            <fieldset class="filter-group">
              <legend class="visually-hidden"><?= e($title) ?></legend>
              <?php partial('filter-checks', ['name' => $name, 'items' => $check_groups[$name]]); ?>
            </fieldset>
          </details>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if ($tab === 'plants' || $tab === 'exchange'): ?>
        <details class="filter-section" data-section="species" open>
          <summary class="filter-section-head"><?= icon('leaf') ?> <span>Species</span> <span class="filter-section-chevron"><?= icon('chevron-down') ?></span></summary>
          <fieldset class="filter-group">
            <legend class="visually-hidden">Species</legend>
            <?php if (!$species_list): ?>
              <p class="filter-note">No species match this search.</p>
            <?php endif; ?>
            <?php partial('filter-checks', ['name' => 'species', 'items' => $species_shown]); ?>
            <?php // The most common five are shown; "+ N more" opens the rest, by itself when one of them is ticked ?>
            <?php if ($species_more): ?>
              <details class="filter-more"<?= $more_open ? ' open' : '' ?>>
                <summary>+ <?= e(count($species_more)) ?> more</summary>
                <?php partial('filter-checks', ['name' => 'species', 'items' => $species_more]); ?>
              </details>
            <?php endif; ?>
          </fieldset>
        </details>
      <?php endif; ?>

      <?php // Plants has no Health filter and no "Available for exchange" switch (Ranshitha, 11 Nov): health is the owner's
            // business, and plants on offer have their own tab, Exchange. ?>
      <?php if ($tab === 'plants' || $tab === 'people'): ?>
        <?php // Distance from a town, as on the Exchange tab: to the plant owner's town, or to the member's town ?>
        <?php partial('near-filter', ['near' => $near, 'distance' => $filters['distance'], 'town_names' => $town_names,
                                      'can_be_empty' => $viewer_town === '']); ?>
      <?php endif; ?>

      <?php if ($tab === 'people'): ?>
        <details class="filter-section" data-section="rating" open>
          <summary class="filter-section-head"><?= icon('star') ?> <span>Minimum rating</span> <span class="filter-section-chevron"><?= icon('chevron-down') ?></span></summary>
          <fieldset class="filter-group">
            <legend class="visually-hidden">Minimum rating</legend>
            <div class="filter-chips">
              <?php foreach ($rating_options as $value => $label): ?>
                <label class="chip-choice">
                  <input type="radio" name="rating" value="<?= e($value) ?>" form="community-form"<?= $value === $filters['rating'] ? ' checked' : '' ?>>
                  <span class="chip"><?php if ($value !== ''): ?><?= icon('star') ?><?php endif; ?> <?= e($label) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </fieldset>
        </details>
      <?php endif; ?>
    </div>

    <?php // From 900px with JavaScript every change applies at once, so this button is hidden there (community.css).
          // In the phone panel community.js turns its words into the live count: "Show 7 plants". ?>
    <div class="filters-foot">
      <button type="submit" form="community-form" class="btn btn-primary filter-apply" data-apply data-noun-one="<?= e($item_words[0]) ?>" data-noun-many="<?= e($item_words[1]) ?>">Apply filters</button>
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
        <span data-swap="filters-badge">
          <?php if ($active_count > 0): ?>
            <span class="filter-toggle-count"><?= e($active_count) ?><span class="visually-hidden"> active</span></span>
          <?php endif; ?>
        </span>
      </a>

      <?php // aria-live: when community.js changes the number, screen readers read the new one out ("7 plants").
            // On the span, not the heading, so the heading stays a heading. ?>
      <h2 class="results-count"><span data-swap="count" aria-live="polite"><?= e($results_text) ?></span></h2>

      <?php // display: contents (community.css): the chips still flow in the bar's row, as if this box were not there ?>
      <div class="results-chips" data-swap="chips">
        <?php foreach ($chips as $chip): ?>
          <a class="chip filter-chip" href="<?= e($chip['href']) ?>">
            <?= e($chip['label']) ?> <?= icon('x') ?><span class="visually-hidden">: <?= e($chip['remove'] ?? 'remove this filter') ?></span>
          </a>
        <?php endforeach; ?>
        <?php if (count($chips) > 1): ?>
          <a class="results-clear" href="<?= e($clear_chips_url) ?>">Clear all</a>
        <?php endif; ?>
      </div>

      <?php // A <details> dropdown of links: it opens without JavaScript, and each sort has its own address ?>
      <details class="menu sort-menu" data-swap="sort">
        <summary class="btn btn-secondary"><span class="sort-word">Sort:</span> <?= e($sort_options[$sort]) ?> <?= icon('chevron-down') ?></summary>
        <div class="menu-panel menu-panel-right">
          <?php foreach ($sort_options as $value => $label): ?>
            <a class="menu-item" href="<?= e($page_url(['sort' => $value === $default_sort ? '' : $value])) ?>"<?= $value === $sort ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
          <?php endforeach; ?>
        </div>
      </details>
    </div>

    <?php // Everything below the results bar: swapped as one part when a filter changes ?>
    <div class="community-main" data-swap="main">
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
          <div class="community-grid community-grid-compact">
            <?php foreach ($popular_plants as $plant): ?>
              <?php partial('plant-card', ['plant' => $plant, 'community' => true, 'show_health' => false]); ?>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>
    <?php else: ?>
      <?php // data-grid: where "Load more" appends the next cards. $shown_rows holds this page's cards (12 per page). ?>
      <?php if ($tab === 'plants'): ?>
        <div class="community-grid community-grid-compact" data-grid>
          <?php foreach ($shown_rows as $plant): ?>
            <?php partial('plant-card', ['plant' => $plant, 'community' => true, 'show_health' => false]); ?>
          <?php endforeach; ?>
        </div>
      <?php elseif ($tab === 'exchange'): ?>
        <?php // Contact details are never on a card: only Listing Detail shows them, after "I'm interested" ?>
        <div class="community-grid community-grid-compact" data-grid>
          <?php foreach ($shown_rows as $listing): ?>
            <?php partial('listing-card', ['listing' => $listing]); ?>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="people-grid" data-grid>
          <?php foreach ($shown_rows as $member): ?>
            <?php partial('person-card', ['member' => $member]); ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php // "Showing 12 of 19 plants" and Load more: a real link to ?page=2, which community.js turns into a fetch ?>
      <?php if ($count > $per_page): ?>
        <div class="load-more">
          <p class="load-more-text" data-showing aria-live="polite"><?= e($showing) ?></p>
          <?php if ($has_more): ?>
            <a class="btn btn-secondary load-more-btn" href="<?= e($next_url) ?>" data-load-more>Load more</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
