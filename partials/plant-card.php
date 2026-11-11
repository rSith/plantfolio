<?php
// Plant card: photo, health badge, nickname, species, next-care hint and collection (CORE-05, wireframe p11).
// Expects $plant — a plants row joined with its species, collection and soonest reminder (see $mock_plants).
// Optional $menu = true adds the owner's ⋯ menu (partials/plant-menu.php); only My Garden passes it.
// Optional $community = true draws the community card (Community page, wireframe p16, element 5; COM-01, COM-07):
// only the health badge on the photo, then in the body the town with a pin, an "Open to swap" link when an open
// listing offers the plant (listing_id, listing_type in the row), the like count as plain text, and the owner's
// @username as a link to their profile. $plant then also needs like_count, username, full_name, avatar and location
// (see $mock_public_plants).
// Optional $show_owner = false (with $community): the collection instead of the owner and town. The Public Profile (P14)
// uses it, because every card there has the same owner, already named at the top of the page.
// Optional $simple = true: no health badge and no care hint, so a single plant looks like a stack card
// (partials/plant-stack.php). My Garden's grid uses it; the list view there still shows health and care.
//
// Links on a community card: the plant's name (its ::after covers the whole card), and, lifted above it with
// z-index, "Open to swap" and the @username. They are siblings, never one link inside another (not allowed in HTML).

$community  = !empty($community);
$simple     = !empty($simple);
$show_owner = $community && ($show_owner ?? true);
$due        = ($community || $simple) ? null : care_hint($plant);   // null when the plant has no reminder yet

// The listing that offers this plant: "Open to swap", "Free to a good home" or "Swap or free"
$offer_words = ['swap' => 'Open to swap', 'free' => 'Free to a good home', 'either' => 'Swap or free'];
$offer       = ($community && !empty($plant['listing_id'])) ? ($plant['listing_type'] ?? 'swap') : null;
?>
<article class="card plant-card<?= $community ? ' plant-card-community' : '' ?>">
  <div class="card-media">
    <?php // On a community card the name link already says which plant it is, so the drawing is decorative (alt="") ?>
    <img src="<?= e(photo_url($plant['photo'])) ?>" alt="<?= $community ? '' : 'Photo of ' . e($plant['nickname']) ?>" loading="lazy">
    <?php if (!$simple): ?>
      <div class="card-media-badges">
        <?php partial('status-badge', ['status' => $plant['health_status']]); ?>
      </div>
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
      <?php if ($show_owner): ?>
        <p class="plant-card-town"><?= icon('map-pin') ?> <?= e($plant['location']) ?></p>
      <?php endif; ?>

      <?php // The offer (a link above the card's link) on the left, the likes as plain text on the right ?>
      <div class="plant-card-tags">
        <?php if ($offer): ?>
          <a class="plant-card-offer plant-card-offer-<?= e($offer) ?>" href="<?= e(url('listing.php?id=' . $plant['listing_id'])) ?>">
            <?= icon(listing_type($offer)['icon']) ?> <?= e($offer_words[$offer] ?? $offer_words['swap']) ?><span class="visually-hidden">: listing for <?= e($plant['nickname']) ?></span>
          </a>
        <?php endif; ?>
        <?php // Not a button: there is no like feature yet (COM-15 is optional), so it must not look clickable ?>
        <span class="plant-card-likes"><?= icon('heart') ?> <?= e($plant['like_count']) ?><span class="visually-hidden"> <?= $plant['like_count'] === 1 ? 'like' : 'likes' ?></span></span>
      </div>

      <?php if ($show_owner): ?>
        <div class="plant-card-owner">
          <?php partial('avatar', ['member' => $plant, 'size' => 'sm']); ?>
          <?php // urlencode: a username is text the member chose, made safe for an address before e() makes it safe for HTML ?>
          <a class="plant-card-username" href="<?= e(url('user.php?u=' . urlencode($plant['username']))) ?>">@<?= e($plant['username']) ?></a>
        </div>
      <?php elseif ($plant['collection_name'] !== null): ?>
        <p class="plant-card-collection"><?= e($plant['collection_name']) ?></p>
      <?php endif; ?>
    <?php else: ?>
      <div class="plant-card-meta">
        <?php if ($due): ?>
          <span class="care-hint care-hint-<?= e($due['state']) ?>"><?= icon(task_icon($plant['task'])) ?> <?= e($due['text']) ?></span>
        <?php endif; ?>
        <?php // collection_name is null for a plant in no collection: nothing to show then ?>
        <?php if ($plant['collection_name'] !== null): ?>
          <span class="plant-card-collection"><?= e($plant['collection_name']) ?></span>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</article>
