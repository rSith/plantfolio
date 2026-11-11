<?php
// Person card: avatar, @username, name, town, plant count and rating, linking to the Public Profile (COM-01, COM-02).
// Expects $member — a users row with its public columns (username, full_name, avatar, location) and the calculated
// columns plant_count, rating_avg and rating_count (see $mock_users). Never email or phone: this is a public card.
// Optional match_count in the row: how many of the member's public plants match the search ("2 matching plants").
// Optional $compact = true: the short card of Explore's "Plant lovers matching" row (wireframe p16, element 6),
// one line of "42 plants · ★ 4.8" beside a smaller avatar. Without it: the People tab card, centred, large avatar.

$compact     = !empty($compact);
$match_count = $member['match_count'] ?? 0;
$plants_text = $member['plant_count'] . ($member['plant_count'] === 1 ? ' plant' : ' plants');
?>
<article class="card person-card<?= $compact ? ' person-card-compact' : '' ?>">
  <?php partial('avatar', $compact ? ['member' => $member] : ['member' => $member, 'size' => 'lg']); ?>
  <div class="person-card-body">
    <h3 class="person-card-username">
      <?php // urlencode: a username is text the member chose, so it is made safe for an address before e() makes it safe for HTML ?>
      <a class="card-link" href="<?= e(url('user.php?u=' . urlencode($member['username']))) ?>">@<?= e($member['username']) ?></a>
    </h3>
    <?php if ($compact): ?>
      <p class="person-card-stats"><?= e($plants_text) ?> <span aria-hidden="true">·</span> <?php partial('rating', ['rating' => $member]); ?></p>
    <?php else: ?>
      <p class="person-card-name"><?= e($member['full_name']) ?></p>
      <p class="person-card-town"><?= icon('map-pin') ?> <?= e($member['location']) ?></p>
      <p class="person-card-stats"><?= e($plants_text) ?> <span aria-hidden="true">·</span> <?php partial('rating', ['rating' => $member]); ?></p>
      <?php if ($match_count > 0): ?>
        <p class="badge badge-tag person-card-match"><?= icon('leaf') ?> <?= e($match_count) ?> matching <?= $match_count === 1 ? 'plant' : 'plants' ?></p>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</article>
