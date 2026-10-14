<?php
// Explore and Search (P09, wireframe p16): one search box for the whole community's public plants, species and
// @usernames, with Plants / People tabs, active filter chips and a sort (COM-01, COM-02, COM-03, UI half).
// Public: visitors and members see the same results. Only public plants can ever appear.
// Reads $mock_public_plants and $mock_users from mock data and filters them in PHP.

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mock-data.php';

// ── What the visitor asked for, read from the address ───
// explore.php?q=monstera&species[]=1&health=thriving&location=Kandy&exchange=1&sort=newest&tab=people
// The wireframe asks for everything in the address (GET), so a search can be shared and Back works.
// Anyone can type anything there, so every value is checked against what is allowed.

$q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);

// "@kandy" looks for a member: the search opens on the People tab, and the @ is not part of the name (p16, element 1)
$is_member_search = str_starts_with($q, '@');
$term             = trim(ltrim($q, '@'));

$default_tab = $is_member_search ? 'people' : 'plants';
$tab         = $_GET['tab'] ?? $default_tab;
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
$health = $_GET['health'] ?? '';
if (!in_array($health, $health_options, true)) {
    $health = '';
}

// The towns that have public plants: the only places worth choosing
$locations = array_values(array_unique(array_column($mock_public_plants, 'location')));
sort($locations);
$location = $_GET['location'] ?? '';
if (!in_array($location, $locations, true)) {
    $location = '';
}

$on_exchange = ($_GET['exchange'] ?? '') === '1';

$sort_options = [
    'likes'  => 'Most liked',
    'newest' => 'Newest',
    'name'   => 'Name A–Z',
];
$sort = $_GET['sort'] ?? 'likes';
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

$found = array_filter($mock_public_plants, fn ($plant) => plant_matches_search($plant, $term));

$plants = array_filter($found, function ($plant) use ($species_filter, $health, $location, $on_exchange) {
    if ($species_filter && !in_array($plant['species_id'], $species_filter, true)) {
        return false;
    }
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

usort($plants, function ($a, $b) use ($sort) {
    switch ($sort) {
        case 'newest':
            // plants has no created_at column; ids grow with every new row, so the highest id is the newest plant
            return $b['id'] <=> $a['id'];
        case 'name':
            return strcasecmp($a['nickname'], $b['nickname']);
        default:
            // Most liked first; equal likes fall back to the name, so the order never changes between visits
            return [$b['like_count'], $a['nickname']] <=> [$a['like_count'], $b['nickname']];
    }
});

// ── The people to show ──────────────────────────────────
// A member matches when the term is in their username, name or town, or when they own a plant that matches
// (searching "monstera" finds the people who grow one, p16 element 6). An @ search looks at names only.
// The members with the most matching plants come first, then the biggest gardens.

$people = [];
foreach ($mock_users as $member) {
    $match_count = count(array_filter($found, fn ($plant) => $plant['user_id'] === $member['id']));
    $name_text   = $member['username'] . ' ' . $member['full_name'] . ($is_member_search ? '' : ' ' . $member['location']);
    $name_hit    = $term === '' || mb_stripos($name_text, $term) !== false;

    if ($name_hit || (!$is_member_search && $match_count > 0)) {
        $people[] = $member + ['match_count' => $match_count];
    }
}
usort($people, fn ($a, $b) => [$b['match_count'], $b['plant_count']] <=> [$a['match_count'], $a['plant_count']]);

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

$plant_count  = count($plants);
$people_count = count($people);

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
      <a class="explore-tab"
         href="<?= e($explore_url(['tab' => $default_tab === 'plants' ? '' : 'plants'])) ?>"<?= $tab === 'plants' ? ' aria-current="page"' : '' ?>>
        Plants <span class="chip-count"><?= e($plant_count) ?></span>
      </a>
      <a class="explore-tab"
         href="<?= e($explore_url(['tab' => $default_tab === 'people' ? '' : 'people'])) ?>"<?= $tab === 'people' ? ' aria-current="page"' : '' ?>>
        People <span class="chip-count"><?= e($people_count) ?></span>
      </a>
    </nav>
  </div>
</section>

<div class="container page explore-body">
  <?php if ($tab === 'plants'): ?>
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
      <section class="panel">
        <?php partial('empty-state', [
            'title'   => 'No plants match',
            'text'    => 'Try another word, or remove a filter.',
            'actions' => [
                ['label' => 'Show all plants', 'href' => 'explore.php', 'icon' => 'search'],
            ],
        ]); ?>
      </section>
    <?php endif; ?>
  <?php else: ?>
    <?php // Step 4 of P09 fills the People tab: one card per member with rating and plant count ?>
    <section class="panel">
      <p class="panel-empty"><?= icon('info') ?> The People results are built in the next step.</p>
    </section>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
