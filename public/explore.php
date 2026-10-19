<?php
// Explore and Search (P09, wireframe p16): one search box for the whole community's public plants, species and
// @usernames, with Plants / People tabs, active filter chips and a sort (COM-01, COM-02, COM-03, UI half).
// A search also shows the members it matches, and finding nothing shows popular plants instead of a blank page.
// Public: visitors and members see the same results. Only public plants can ever appear.
// Reads $mock_public_plants and $mock_users from mock data and filters them in PHP.

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mock-data.php';

// ── What the visitor asked for, read from the address ───
// explore.php?q=monstera&species[]=1&health=thriving&location=Kandy&exchange=1&sort=newest&tab=people
// The wireframe asks for everything in the address (GET), so a search can be shared and Back works.
// Anyone can type anything there, so every value is checked against what is allowed.

// A hand-made address can send a list where one word belongs (q[]=x makes $_GET['q'] an array). Only text is
// accepted; anything else counts as "not given", so it falls back to the default like any other wrong value.
$get_text = fn ($name) => is_string($_GET[$name] ?? null) ? $_GET[$name] : '';

$q = mb_substr(trim($get_text('q')), 0, 100);

// "@kandy" looks for a member: the search opens on the People tab, and the @ is not part of the name (p16, element 1)
$is_member_search = str_starts_with($q, '@');
$term             = trim(ltrim($q, '@'));

$default_tab = $is_member_search ? 'people' : 'plants';
$tab         = $get_text('tab');
if (!in_array($tab, ['plants', 'people'], true)) {
    $tab = $default_tab;
}

// species[]=1&species[]=9 arrives as an array. Only whole numbers of species that exist are kept; is_string()
// first, because a hand-made address could put an array inside the array.
$species_filter = [];
foreach ((array) ($_GET['species'] ?? []) as $value) {
    if (is_string($value) && ctype_digit($value) && isset($mock_species[(int) $value])) {
        $species_filter[] = (int) $value;
    }
}
$species_filter = array_values(array_unique($species_filter));

$health_options = ['thriving', 'stable', 'needs_attention', 'sick'];
$health = $get_text('health');
if (!in_array($health, $health_options, true)) {
    $health = '';
}

// The towns that have public plants: the only places worth choosing
$locations = array_values(array_unique(array_column($mock_public_plants, 'location')));
sort($locations);
$location = $get_text('location');
if (!in_array($location, $locations, true)) {
    $location = '';
}

$on_exchange = $get_text('exchange') === '1';

$sort_options = [
    'likes'  => 'Most liked',
    'newest' => 'Newest',
    'name'   => 'Name A–Z',
];
$sort = $get_text('sort');
if (!array_key_exists($sort, $sort_options)) {
    $sort = 'likes';
}

// The choices that differ from the defaults. Every link on the page starts from these and changes one thing,
// so removing one filter keeps the others; query_url() leaves out the empty ones.
$filters = [
    'q'        => $q,
    'tab'      => $tab === $default_tab ? '' : $tab,
    'species'  => $species_filter,
    'health'   => $health,
    'location' => $location,
    'exchange' => $on_exchange ? '1' : '',
    'sort'     => $sort === 'likes' ? '' : $sort,
];
$explore_url = fn (array $changes) => query_url('explore.php', array_merge($filters, $changes));

// The two tabs. The default tab needs no tab= in the address, so a shared search link stays short.
$plants_tab_url = $explore_url(['tab' => $default_tab === 'plants' ? '' : 'plants']);
$people_tab_url = $explore_url(['tab' => $default_tab === 'people' ? '' : 'people']);

// ── The plants to show ──────────────────────────────────
// Stage B: one prepared query on plants JOIN plant_species JOIN users WHERE plants.is_public = 1, with
// (nickname LIKE ? OR scientific_name LIKE ? …) for the search and one AND per filter. Here the rows are filtered in PHP.

/** Does the search term appear in the plant's names, its species or its owner's username? An empty term matches all. */
function plant_matches_search($plant, $term)
{
    if ($term === '') {
        return true;
    }
    $haystack = $plant['nickname'] . ' ' . $plant['scientific_name'] . ' ' . $plant['common_name'] . ' ' . $plant['username'];

    return mb_stripos($haystack, $term) !== false;   // mb_stripos ignores capitals and handles any language
}

/** Most liked first; equal likes fall back to the name, so the order never changes between visits. For usort(). */
function most_liked_first($a, $b)
{
    return [$b['like_count'], $a['nickname']] <=> [$a['like_count'], $b['nickname']];
}

$found = array_filter($mock_public_plants, fn ($plant) => plant_matches_search($plant, $term));

// Two passes: first every filter except species, then species. The plants between the two passes are the ones
// the species counts in the sidebar are made from (see "Species counts" below).
$before_species = array_filter($found, function ($plant) use ($health, $location, $on_exchange) {
    if ($health !== '' && $plant['health_status'] !== $health) {
        return false;
    }
    if ($location !== '' && $plant['location'] !== $location) {
        return false;
    }
    if ($on_exchange && !$plant['on_exchange']) {
        return false;
    }
    return true;
});

$plants = array_filter($before_species, fn ($plant) => !$species_filter || in_array($plant['species_id'], $species_filter, true));

usort($plants, function ($a, $b) use ($sort) {
    switch ($sort) {
        case 'newest':
            // plants has no created_at column; ids grow with every new row, so the highest id is the newest plant
            return $b['id'] <=> $a['id'];
        case 'name':
            return strcasecmp($a['nickname'], $b['nickname']);
        default:
            return most_liked_first($a, $b);
    }
});

// ── The people to show ──────────────────────────────────
// A member matches when the term is in their username, name or town, or when they own a plant that matches
// (searching "monstera" finds the people who grow one, p16 element 6). An @ search looks at names only.
// The members with the most matching plants come first, then the biggest gardens.
// Stage B: users LEFT JOIN the matching public plants, GROUP BY users.id, with COUNT(plants.id) AS match_count.
// match_count stays 0 without a search word (every plant would "match") and for an @ search (it is about names).

$people = [];
foreach ($mock_users as $member) {
    $match_count = ($term === '' || $is_member_search)
        ? 0
        : count(array_filter($found, fn ($plant) => $plant['user_id'] === $member['id']));
    $name_text   = $member['username'] . ' ' . $member['full_name'] . ($is_member_search ? '' : ' ' . $member['location']);
    $name_hit    = $term === '' || mb_stripos($name_text, $term) !== false;

    if ($name_hit || $match_count > 0) {
        $people[] = $member + ['match_count' => $match_count];
    }
}
usort($people, fn ($a, $b) => [$b['match_count'], $b['plant_count']] <=> [$a['match_count'], $a['plant_count']]);

// "Plant lovers matching ..." above the plant results (p16, element 6): only for a search, and three at most.
// The People tab has the full list.
$people_row = ($tab === 'plants' && $term !== '') ? array_slice($people, 0, 3) : [];

// ── Active filters (p16, element 4) ─────────────────────
// One removable chip per filter: its link is the same address without that one filter.

$active_filters = [];
foreach ($species_filter as $species_id) {
    $active_filters[] = [
        'label' => $mock_species[$species_id]['common_name'],
        'href'  => $explore_url(['species' => array_values(array_diff($species_filter, [$species_id]))]),
    ];
}
if ($health !== '') {
    $active_filters[] = ['label' => health_label($health), 'href' => $explore_url(['health' => ''])];
}
if ($location !== '') {
    $active_filters[] = ['label' => $location, 'href' => $explore_url(['location' => ''])];
}
if ($on_exchange) {
    $active_filters[] = ['label' => 'On the exchange', 'href' => $explore_url(['exchange' => ''])];
}
$clear_filters_url = $explore_url(['species' => [], 'health' => '', 'location' => '', 'exchange' => '']);

// ── Species counts for the sidebar (p16, element 3) ─────
// Each species shows how many plants ticking it would give: the search and the other filters count, the species
// filter itself does not (otherwise ticking Monstera would set every other species to 0).
// Stage B: SELECT species_id, COUNT(*) … GROUP BY species_id with the same WHERE minus the species condition.
// The most common species come first; the first five are shown and the rest fold under "+ N more".

$species_counts = [];
foreach ($before_species as $plant) {
    $species_counts[$plant['species_id']] = ($species_counts[$plant['species_id']] ?? 0) + 1;
}
foreach ($species_filter as $species_id) {
    $species_counts[$species_id] ??= 0;   // a ticked species stays in the list, even when nothing matches it any more
}

$species_list = [];
foreach ($species_counts as $species_id => $count) {
    $species_list[] = [
        'id'      => $species_id,
        'name'    => $mock_species[$species_id]['common_name'],
        'count'   => $count,
        'checked' => in_array($species_id, $species_filter, true),
    ];
}
usort($species_list, fn ($a, $b) => [$b['count'], $a['name']] <=> [$a['count'], $b['name']]);

$species_shown = array_slice($species_list, 0, 5);
$species_more  = array_slice($species_list, 5);
// Open "+ N more" when one of the folded species is ticked, so the tick is never hidden
$more_open = in_array(true, array_column($species_more, 'checked'), true);

$active_count = count($active_filters);

$plant_count  = count($plants);
$people_count = count($people);

// ── No plants found (p16 UX notes) ──────────────────────
// "Suggest removing the last filter and show popular plants instead of a blank page."
// The filter form sends every choice at once, so the order they were picked in is not known: "the last filter"
// is the last chip in the row, and its link already removes just that one filter.

$no_plants      = null;
$popular_plants = [];
if ($tab === 'plants' && $plant_count === 0) {
    $last_filter = $active_filters ? end($active_filters) : null;
    $actions     = [];

    if ($last_filter) {
        $actions[] = ['label' => 'Remove "' . $last_filter['label'] . '"', 'url' => $last_filter['href'], 'icon' => 'x'];
        if ($active_count > 1) {
            $actions[] = ['label' => 'Clear all filters', 'url' => $clear_filters_url, 'icon' => 'sliders-horizontal'];
        } elseif ($q !== '') {
            $actions[] = ['label' => 'Clear the search', 'url' => $explore_url(['q' => '']), 'icon' => 'search'];
        }
    } elseif ($q !== '') {
        $actions[] = ['label' => 'Show all plants', 'url' => $explore_url(['q' => '']), 'icon' => 'search'];
    }

    // The most-liked public plants, whatever the search and filters said.
    // Stage B: the same query as the results without the search and filters, ORDER BY like_count DESC LIMIT 6.
    $popular_plants = $mock_public_plants;
    usort($popular_plants, 'most_liked_first');
    $popular_plants = array_slice($popular_plants, 0, 6);

    if ($last_filter) {
        $no_plants_text = 'Remove a filter to widen the search. Until then, here are some popular plants.';
    } elseif ($q !== '') {
        $no_plants_text = 'Check the spelling or try a shorter word. Until then, here are some popular plants.';
    } else {
        $no_plants_text = 'Plants appear here when members make them public.';   // only before anyone shares a plant
    }

    if ($q !== '') {
        $no_plants_title = 'No plants match "' . $q . '"' . ($last_filter ? ' with these filters' : '');
    } else {
        $no_plants_title = $last_filter ? 'No plants match these filters' : 'No public plants yet';
    }

    $no_plants = [
        'title'   => $no_plants_title,
        'text'    => $no_plants_text,
        'actions' => $actions,
    ];
}

// The People tab with nobody to show: a way back to every member
$no_people = [
    'title'   => $q !== '' ? 'No members match "' . $q . '"' : 'No members yet',
    'text'    => 'Check the spelling, or try part of a name, a town or an @username.',
    'actions' => [
        ['label' => 'Show all members', 'url' => query_url('explore.php', ['tab' => 'people']), 'icon' => 'search'],
    ],
];

$page_title = $q !== '' ? 'Search: ' . $q : 'Explore';
$page_css   = 'explore.css';

include __DIR__ . '/../includes/header.php';
?>
<?php // The top band (p16, elements 1 and 2): title, the one search box and the two tabs ?>
<section class="explore-top">
  <div class="container">
    <h1 class="explore-title">Explore the community</h1>

    <form class="explore-search" method="get" action="<?= e(url('explore.php')) ?>" role="search">
      <?php // A new search starts without filters (their counts belong to the old search) but keeps the tab and sort ?>
      <?php if ($tab === 'people' && !$is_member_search): ?>
        <input type="hidden" name="tab" value="people">
      <?php endif; ?>
      <?php if ($filters['sort'] !== ''): ?>
        <input type="hidden" name="sort" value="<?= e($sort) ?>">
      <?php endif; ?>

      <div class="input-icon explore-search-box">
        <?= icon('search') ?>
        <label class="visually-hidden" for="explore-q">Search plants, species or @usernames</label>
        <input class="input" type="search" id="explore-q" name="q" value="<?= e($q) ?>" placeholder="Plants, species or @usernames" maxlength="100">
      </div>
      <button type="submit" class="btn btn-primary">Search</button>
    </form>

    <?php // Links, not buttons: each tab has its own address, like the tabs on My Garden and the Plant Profile ?>
    <nav class="explore-tabs" aria-label="Results">
      <a class="explore-tab" href="<?= e($plants_tab_url) ?>"<?= $tab === 'plants' ? ' aria-current="page"' : '' ?>>
        Plants <span class="chip-count"><?= e($plant_count) ?></span>
      </a>
      <a class="explore-tab" href="<?= e($people_tab_url) ?>"<?= $tab === 'people' ? ' aria-current="page"' : '' ?>>
        People <span class="chip-count"><?= e($people_count) ?></span>
      </a>
    </nav>
  </div>
</section>

<div class="container page explore-body<?= $tab === 'plants' ? ' has-filters' : '' ?>">
  <?php if ($tab === 'plants'): ?>
    <?php // Filters (p16, element 3). A <details> folds them behind a "Filters" button on a phone and a tablet
          // (p23: "filters collapse behind a button"); explore.css keeps them open beside the results on a desktop.
          // One GET form, so Apply puts every choice in the address. Week 6 applies them without the button. ?>
    <details class="filter-panel">
      <summary class="btn btn-secondary filter-toggle">
        <?= icon('sliders-horizontal') ?> Filters
        <?php if ($active_count > 0): ?>
          <span class="filter-toggle-count"><?= e($active_count) ?><span class="visually-hidden"> active</span></span>
        <?php endif; ?>
      </summary>

      <form class="filter-form" method="get" action="<?= e(url('explore.php')) ?>" aria-labelledby="filters-title">
        <?php // The search and the sort are chosen elsewhere on the page; hidden fields carry them through Apply ?>
        <?php if ($q !== ''): ?>
          <input type="hidden" name="q" value="<?= e($q) ?>">
        <?php endif; ?>
        <?php if ($filters['tab'] !== ''): ?>
          <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <?php endif; ?>
        <?php if ($filters['sort'] !== ''): ?>
          <input type="hidden" name="sort" value="<?= e($sort) ?>">
        <?php endif; ?>

        <div class="filter-head">
          <h2 class="filter-title" id="filters-title">Filters</h2>
          <?php if ($active_count > 0): ?>
            <a class="results-clear" href="<?= e($clear_filters_url) ?>">Clear all</a>
          <?php endif; ?>
        </div>

        <?php // Species: tick any number. The name species[] makes PHP collect every ticked box into one array. ?>
        <fieldset class="filter-group">
          <legend class="label">Species</legend>
          <?php if (!$species_list): ?>
            <p class="text-small">No species match this search.</p>
          <?php endif; ?>
          <?php foreach ($species_shown as $item): ?>
            <label class="check filter-check">
              <input type="checkbox" name="species[]" value="<?= e($item['id']) ?>"<?= $item['checked'] ? ' checked' : '' ?>>
              <span class="filter-check-name"><?= e($item['name']) ?></span>
              <span class="filter-check-count"><?= e($item['count']) ?></span>
            </label>
          <?php endforeach; ?>
          <?php if ($species_more): ?>
            <details class="filter-more"<?= $more_open ? ' open' : '' ?>>
              <summary>+ <?= e(count($species_more)) ?> more</summary>
              <?php foreach ($species_more as $item): ?>
                <label class="check filter-check">
                  <input type="checkbox" name="species[]" value="<?= e($item['id']) ?>"<?= $item['checked'] ? ' checked' : '' ?>>
                  <span class="filter-check-name"><?= e($item['name']) ?></span>
                  <span class="filter-check-count"><?= e($item['count']) ?></span>
                </label>
              <?php endforeach; ?>
            </details>
          <?php endif; ?>
        </fieldset>

        <?php // Health: one choice, so radio buttons drawn as chips (p16). "Any" is how a choice is taken back. ?>
        <fieldset class="filter-group">
          <legend class="label">Health</legend>
          <div class="filter-chips">
            <?php foreach (array_merge([''], $health_options) as $option): ?>
              <label class="chip-choice">
                <input type="radio" name="health" value="<?= e($option) ?>"<?= $option === $health ? ' checked' : '' ?>>
                <span class="chip"><?= e($option === '' ? 'Any' : health_label($option)) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>

        <div class="filter-group">
          <label class="label" for="filter-location">Location</label>
          <div class="input-icon">
            <?= icon('map-pin') ?>
            <select class="input" id="filter-location" name="location">
              <option value="">All locations</option>
              <?php foreach ($locations as $town): ?>
                <option value="<?= e($town) ?>"<?= $town === $location ? ' selected' : '' ?>><?= e($town) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="filter-group">
          <label class="toggle">
            <input type="checkbox" name="exchange" value="1"<?= $on_exchange ? ' checked' : '' ?>>
            Available for exchange
          </label>
        </div>

        <?php // Without JavaScript a form only sends when a button is pressed. Week 6 sends it on every change. ?>
        <button type="submit" class="btn btn-primary filter-apply">Apply filters</button>
      </form>
    </details>

    <div class="explore-results">
      <?php if ($people_row): ?>
        <?php // Plant lovers matching the search (p16, element 6). On a phone the cards scroll sideways in their own strip. ?>
        <section class="people-row" aria-labelledby="people-row-title">
          <div class="people-row-head">
            <h2 class="explore-section-title" id="people-row-title">Plant lovers matching "<?= e($q) ?>"</h2>
            <a class="panel-link" href="<?= e($people_tab_url) ?>">See all <?= e($people_count) ?> <?= $people_count === 1 ? 'person' : 'people' ?></a>
          </div>
          <div class="people-row-list">
            <?php foreach ($people_row as $member): ?>
              <?php partial('member-card', ['member' => $member, 'compact' => true]); ?>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>

      <?php // Result count, active filter chips and the sort (p16, element 4) ?>
      <div class="results-bar">
        <h2 class="results-count" role="status"><?= e($plant_count) ?> <?= $plant_count === 1 ? 'plant' : 'plants' ?></h2>

        <?php foreach ($active_filters as $filter): ?>
          <a class="chip filter-chip" href="<?= e($filter['href']) ?>">
            <?= e($filter['label']) ?> <?= icon('x') ?><span class="visually-hidden">: remove this filter</span>
          </a>
        <?php endforeach; ?>
        <?php if (count($active_filters) > 1): ?>
          <a class="results-clear" href="<?= e($clear_filters_url) ?>">Clear all</a>
        <?php endif; ?>

        <?php // A <details> dropdown of links: it opens without JavaScript, and each sort has its own address ?>
        <details class="menu sort-menu">
          <summary class="btn btn-secondary">Sort: <?= e($sort_options[$sort]) ?> <?= icon('chevron-down') ?></summary>
          <div class="menu-panel menu-panel-right">
            <?php foreach ($sort_options as $value => $label): ?>
              <a class="menu-item" href="<?= e($explore_url(['sort' => $value === 'likes' ? '' : $value])) ?>"<?= $value === $sort ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
            <?php endforeach; ?>
          </div>
        </details>
      </div>

      <?php if ($plants): ?>
        <div class="explore-grid">
          <?php foreach ($plants as $plant): ?>
            <?php partial('plant-card', ['plant' => $plant, 'community' => true]); ?>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <?php // Never a blank page: a way to widen the search, then the community's favourite plants ?>
        <section class="panel">
          <?php partial('empty-state', $no_plants); ?>
        </section>

        <?php if ($popular_plants): ?>
          <section class="popular-plants" aria-labelledby="popular-title">
            <h2 class="explore-section-title" id="popular-title">Popular plants</h2>
            <p class="popular-text">The most-liked public plants in the community.</p>
            <div class="explore-grid">
              <?php foreach ($popular_plants as $plant): ?>
                <?php partial('plant-card', ['plant' => $plant, 'community' => true]); ?>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <?php // People (p16, element 2): "lists profiles with rating and plant count". Filters are for plants, so none here. ?>
    <div class="results-bar">
      <h2 class="results-count" role="status"><?= e($people_count) ?> <?= $people_count === 1 ? 'person' : 'people' ?></h2>
      <?php if ($term !== '' && $people): ?>
        <p class="results-note">
          <?= $is_member_search ? 'Names and usernames' : 'Names, usernames and towns' ?> that match "<?= e($q) ?>"<?= $is_member_search ? '' : ', and members who grow a matching plant' ?>.
        </p>
      <?php endif; ?>
    </div>

    <?php if ($people): ?>
      <div class="people-grid">
        <?php foreach ($people as $member): ?>
          <?php partial('member-card', ['member' => $member]); ?>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <section class="panel">
        <?php partial('empty-state', $no_people); ?>
      </section>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
