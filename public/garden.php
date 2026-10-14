<?php
// My Garden (P04, wireframe p11): every plant the member owns, grouped into collections, with its health and
// next care on each card (CORE-14 and CORE-15, UI half). With no plants it shows state S1 instead (CORE-25).
// Members only. Reads the plants and collections from mock data and filters them in PHP.

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mock-data.php';

// Stage F stand-in for require_login(): a visitor (?guest) is sent to Log in, exactly as stage B will do.
if (!$current_user) {
    header('Location: ' . url('login.php'));
    exit;
}

// ── What the member chose, read from the address ────────
// garden.php?collection=1&q=pothos&status=sick&sort=name&view=list. Anyone can type anything into the address bar,
// so every value is checked against what is allowed and falls back to the default.

$collection_ids = array_column($mock_collections, 'id');
$collection_id  = filter_input(INPUT_GET, 'collection', FILTER_VALIDATE_INT);
if (!in_array($collection_id, $collection_ids, true)) {
    $collection_id = null;   // "All plants"
}

$q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);

$status_options = ['thriving', 'stable', 'needs_attention', 'sick'];
$status = $_GET['status'] ?? '';
if (!in_array($status, $status_options, true)) {
    $status = '';
}

$sort_options = [
    'updated' => 'Recently updated',
    'name'    => 'Name A–Z',
    'care'    => 'Next care',
    'health'  => 'Health, worst first',
];
$sort = $_GET['sort'] ?? 'updated';
if (!array_key_exists($sort, $sort_options)) {
    $sort = 'updated';
}

// Cards, or a table for large gardens (p11, UX notes). Week 5 switches without a reload; for now it is a link.
$view = ($_GET['view'] ?? '') === 'list' ? 'list' : 'grid';

// The choices that differ from the defaults. Links on the page start from these, so changing one filter keeps
// the others; query_url() leaves out the empty ones, so the plain page stays plain "garden.php".
$filters = [
    'collection' => $collection_id,
    'q'          => $q,
    'status'     => $status,
    'sort'       => $sort === 'updated' ? '' : $sort,
    'view'       => $view === 'grid' ? '' : $view,
];

$view_links = [
    'grid' => ['label' => 'Grid view', 'icon' => 'layout-grid'],
    'list' => ['label' => 'List view', 'icon' => 'list'],
];
$is_filtered = $q !== '' || $status !== '';

// ── The plants to show ─────────────────────────────────
// Stage B: one prepared query with WHERE user_id = ? AND collection_id = ? AND health_status = ?
// AND nickname LIKE ?, and the ORDER BY picked from the same whitelist. Here the mock rows are filtered in PHP.

$plants = array_filter($mock_plants, function ($plant) use ($collection_id, $q, $status) {
    if ($collection_id !== null && $plant['collection_id'] !== $collection_id) {
        return false;
    }
    if ($status !== '' && $plant['health_status'] !== $status) {
        return false;
    }
    if ($q !== '') {
        // The search looks in the nickname and both species names; mb_stripos ignores capitals
        $haystack = $plant['nickname'] . ' ' . $plant['common_name'] . ' ' . $plant['scientific_name'];
        if (mb_stripos($haystack, $q) === false) {
            return false;
        }
    }
    return true;
});

// Health order for "worst first": the plants that need help come to the top
$health_rank = ['sick' => 0, 'needs_attention' => 1, 'stable' => 2, 'thriving' => 3];

// usort() compares two plants at a time; <=> gives -1, 0 or 1, which tells it which one comes first
usort($plants, function ($a, $b) use ($sort, $health_rank) {
    switch ($sort) {
        case 'name':
            return strcasecmp($a['nickname'], $b['nickname']);
        case 'care':
            // Soonest first, so overdue care leads; a plant without a reminder goes last
            return ($a['next_due'] ?? '9999-12-31') <=> ($b['next_due'] ?? '9999-12-31');
        case 'health':
            return $health_rank[$a['health_status']] <=> $health_rank[$b['health_status']];
        default:
            return $b['last_logged_at'] <=> $a['last_logged_at'];   // newest first
    }
});

$has_plants  = $current_user['plant_count'] > 0;
$plant_count = $current_user['plant_count'];
$coll_count  = $current_user['collection_count'];

$page_title = 'My Garden';
$page_css   = 'garden.css';

include __DIR__ . '/../includes/header.php';
?>
<div class="container page">
  <div class="garden-head">
    <div>
      <h1 class="garden-title">My Garden</h1>
      <p class="garden-summary">
        <?php if ($has_plants): ?>
          <?= e($plant_count) ?> <?= $plant_count === 1 ? 'plant' : 'plants' ?> in <?= e($coll_count) ?> <?= $coll_count === 1 ? 'collection' : 'collections' ?>
        <?php else: ?>
          No plants yet
        <?php endif; ?>
      </p>
    </div>
    <div class="garden-actions">
      <?php // Stage F: does nothing yet. Creating a collection is saved in stage B (CORE-13). ?>
      <button type="button" class="btn btn-secondary"><?= icon('folder-plus') ?> New collection</button>
      <a class="btn btn-primary" href="<?= e(url('plant-form.php')) ?>"><?= icon('plus') ?> Add plant</a>
    </div>
  </div>

  <?php if (!$has_plants): ?>
    <section class="panel garden-empty">
      <?php partial('empty-state', [
          'title'   => 'Your garden is waiting',
          'text'    => 'Add your first plant to start its story.',
          'actions' => [
              ['label' => 'Add a plant',           'href' => 'plant-form.php', 'icon' => 'plus'],
              ['label' => 'Identify from a photo', 'href' => 'identify.php',   'icon' => 'sparkles'],
          ],
      ]); ?>
    </section>
  <?php else: ?>
    <?php // Collection tabs (p11, element 2): links, so each collection has its own address ?>
    <nav class="collection-tabs" aria-label="Collections">
      <a class="chip<?= $collection_id === null ? ' is-selected' : '' ?>"
         href="<?= e(query_url('garden.php', array_merge($filters, ['collection' => null]))) ?>"<?= $collection_id === null ? ' aria-current="page"' : '' ?>>
        All plants <span class="chip-count"><?= e($plant_count) ?></span>
      </a>
      <?php foreach ($mock_collections as $collection): ?>
        <?php $selected = $collection['id'] === $collection_id; ?>
        <a class="chip<?= $selected ? ' is-selected' : '' ?>"
           href="<?= e(query_url('garden.php', array_merge($filters, ['collection' => $collection['id']]))) ?>"<?= $selected ? ' aria-current="page"' : '' ?>>
          <?php if (!$collection['is_public']): ?>
            <?= icon('lock') ?><span class="visually-hidden">Private:</span>
          <?php endif; ?>
          <?= e($collection['name']) ?> <span class="chip-count"><?= e($collection['plant_count']) ?></span>
        </a>
      <?php endforeach; ?>
      <button type="button" class="chip chip-add"><?= icon('plus') ?> New collection</button>
    </nav>

    <?php // Search, filter, sort (p11, element 3): a GET form, so the choices land in the address like the tabs ?>
    <form class="garden-toolbar" method="get" action="<?= e(url('garden.php')) ?>" role="search">
      <?php // Hidden fields carry the choices made outside the form, so Apply does not lose them ?>
      <?php if ($collection_id !== null): ?>
        <input type="hidden" name="collection" value="<?= e($collection_id) ?>">
      <?php endif; ?>
      <?php if ($view === 'list'): ?>
        <input type="hidden" name="view" value="list">
      <?php endif; ?>

      <div class="input-icon garden-search">
        <?= icon('search') ?>
        <label class="visually-hidden" for="garden-q">Search your garden</label>
        <input class="input" type="search" id="garden-q" name="q" value="<?= e($q) ?>" placeholder="Search your garden" maxlength="100">
      </div>

      <label class="toolbar-select">
        <span class="toolbar-select-label">Status:</span>
        <select class="input" name="status">
          <option value="">All</option>
          <?php foreach ($status_options as $option): ?>
            <option value="<?= e($option) ?>"<?= $option === $status ? ' selected' : '' ?>><?= e(health_label($option)) ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <label class="toolbar-select">
        <span class="toolbar-select-label">Sort:</span>
        <select class="input" name="sort">
          <?php foreach ($sort_options as $value => $label): ?>
            <option value="<?= e($value) ?>"<?= $value === $sort ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <?php // Without JavaScript a <select> cannot send the form by itself. Week 6 submits on change and hides this. ?>
      <button type="submit" class="btn btn-secondary garden-apply">Apply</button>

      <?php // Links, not buttons: each view has its own address, like the tabs ?>
      <div class="view-toggle" role="group" aria-label="View">
        <?php foreach ($view_links as $key => $link): ?>
          <a class="view-toggle-btn<?= $key === $view ? ' is-active' : '' ?>"
             href="<?= e(query_url('garden.php', array_merge($filters, ['view' => $key === 'grid' ? '' : $key]))) ?>"
             aria-label="<?= e($link['label']) ?>"<?= $key === $view ? ' aria-current="true"' : '' ?>><?= icon($link['icon']) ?></a>
        <?php endforeach; ?>
      </div>
    </form>

    <?php if ($is_filtered): ?>
      <p class="garden-results" role="status">
        <?= e(count($plants)) ?> <?= count($plants) === 1 ? 'plant matches' : 'plants match' ?>
        <a href="<?= e(query_url('garden.php', ['collection' => $collection_id, 'view' => $filters['view']])) ?>"><?= icon('x') ?> Clear filters</a>
      </p>
    <?php endif; ?>

    <?php if ($plants && $view === 'grid'): ?>
      <div class="plant-grid">
        <?php foreach ($plants as $plant): ?>
          <?php partial('plant-card', ['plant' => $plant, 'menu' => true]); ?>
        <?php endforeach; ?>
      </div>
    <?php elseif ($plants): ?>
      <?php // List view: the same plants as a table (photo, name, collection, status, next care). On a phone
            // garden.css turns each row into a compact stacked block, because five columns do not fit. ?>
      <table class="garden-table">
        <caption class="visually-hidden">Your plants</caption>
        <thead>
          <tr>
            <th scope="col"><span class="visually-hidden">Photo</span></th>
            <th scope="col">Plant</th>
            <th scope="col">Collection</th>
            <th scope="col">Status</th>
            <th scope="col">Next care</th>
            <th scope="col"><span class="visually-hidden">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($plants as $plant): ?>
            <?php $due = care_hint($plant); ?>
            <tr>
              <td class="cell-photo"><img class="thumb" src="<?= e(photo_url($plant['photo'])) ?>" alt=""></td>
              <td class="cell-name">
                <a class="garden-table-name" href="<?= e(url('plant.php?id=' . $plant['id'])) ?>"><?= e($plant['nickname']) ?></a>
                <span class="scientific-name"><?= e($plant['scientific_name']) ?></span>
              </td>
              <td class="cell-collection"><?= e($plant['collection_name']) ?></td>
              <td class="cell-status"><?php partial('status-badge', ['status' => $plant['health_status']]); ?></td>
              <td class="cell-care">
                <?php if ($due): ?>
                  <span class="care-hint care-hint-<?= e($due['state']) ?>"><?= icon(task_icon($plant['task'])) ?> <?= e($due['text']) ?></span>
                <?php else: ?>
                  <span class="text-small">No reminder</span>
                <?php endif; ?>
              </td>
              <td class="cell-menu"><?php partial('plant-menu', ['plant' => $plant]); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <section class="panel">
        <?php partial('empty-state', [
            'title'   => 'No plants match',
            'text'    => 'Try another name or status, or look in all collections.',
            'actions' => [
                ['label' => 'Clear filters', 'href' => 'garden.php', 'icon' => 'x'],
            ],
        ]); ?>
      </section>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
