<?php
// Listing card for the exchange marketplace (CORE-05, wireframe p17).
// Expects $listing — an exchange_listings row joined with its owner (username, full_name, avatar) and the
// calculated columns rating_avg, rating_count, interest_count and distance_km (see $mock_listings).
// Contact details are never printed here: they appear only after "I'm interested" (privacy rule).

$type = listing_type($listing['type']);
?>
<article class="card listing-card">
  <div class="card-media">
    <img src="<?= e(photo_url($listing['photo'])) ?>" alt="Photo of <?= e($listing['title']) ?>" loading="lazy">
    <span class="badge badge-type-<?= e($listing['type']) ?>"><?= icon($type['icon']) ?> <?= e($type['label']) ?></span>
    <?php if ($listing['interest_count'] > 0): ?>
      <span class="badge listing-card-interest"><?= icon('user') ?> <?= e($listing['interest_count']) ?> interested</span>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <h3 class="listing-card-title">
      <a class="card-link" href="<?= e(url('listing.php?id=' . $listing['id'])) ?>"><?= e($listing['title']) ?></a>
    </h3>
    <p class="listing-card-place">
      <?= icon('map-pin') ?>
      <?= e($listing['location']) ?>
      <?php if ($listing['distance_km'] !== null): ?>· <?= e($listing['distance_km']) ?> km<?php endif; ?>
      · <?= e(time_ago($listing['created_at'])) ?>
    </p>
    <p class="listing-card-wants"><strong>Looking for:</strong> <?= e($listing['looking_for']) ?></p>
    <div class="listing-card-owner">
      <?php partial('avatar', ['member' => $listing, 'size' => 'sm']); ?>
      <span class="listing-card-username">@<?= e($listing['username']) ?></span>
      <?php partial('rating', ['rating' => $listing]); ?>
    </div>
  </div>
</article>
