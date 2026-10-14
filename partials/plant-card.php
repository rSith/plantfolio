<?php
// Plant card: photo, health badge, nickname, species, next-care hint and collection (CORE-05, wireframe p11).
// Expects $plant — a plants row joined with its species, collection and soonest reminder (see $mock_plants).
// Optional $menu = true adds the owner's ⋯ menu (partials/plant-menu.php); only My Garden passes it.
// Optional $community = true draws the community card of Explore (wireframe p16, element 5): likes on the photo,
// and the owner with their town instead of the care hint and collection. $plant then also needs like_count,
// username, full_name, avatar and location (see $mock_public_plants).

$community = !empty($community);
$due       = $community ? null : care_hint($plant);   // null when the plant has no reminder yet
?>
<article class="card plant-card<?= $community ? ' plant-card-community' : '' ?>">
  <div class="card-media">
    <img src="<?= e(photo_url($plant['photo'])) ?>" alt="Photo of <?= e($plant['nickname']) ?>" loading="lazy">
    <?php partial('status-badge', ['status' => $plant['health_status']]); ?>
    <?php if ($community): ?>
      <span class="badge plant-card-likes"><?= icon('heart') ?> <?= e($plant['like_count']) ?><span class="visually-hidden"> <?= $plant['like_count'] === 1 ? 'like' : 'likes' ?></span></span>
    <?php endif; ?>
    <?php if (!empty($menu)): ?>
      <?php partial('plant-menu', ['plant' => $plant]); ?>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <h3 class="plant-card-name">
      <a class="card-link" href="<?= e(url('plant.php?id=' . $plant['id'])) ?>"><?= e($plant['nickname']) ?></a>
    </h3>
    <p class="scientific-name"><?= e($plant['scientific_name']) ?></p>
    <?php if ($community): ?>
      <?php // Whose plant it is. Not a link of its own: the whole card already opens the plant ?>
      <div class="plant-card-owner">
        <?php partial('avatar', ['member' => $plant, 'size' => 'sm']); ?>
        <span class="plant-card-username">@<?= e($plant['username']) ?></span>
        <span class="plant-card-town"><?= e($plant['location']) ?></span>
      </div>
    <?php else: ?>
      <div class="plant-card-meta">
        <?php if ($due): ?>
          <span class="care-hint care-hint-<?= e($due['state']) ?>"><?= icon(task_icon($plant['task'])) ?> <?= e($due['text']) ?></span>
        <?php endif; ?>
        <span class="plant-card-collection"><?= e($plant['collection_name']) ?></span>
      </div>
    <?php endif; ?>
  </div>
</article>
