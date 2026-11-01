<?php
// Community panel beside the sign-up and log-in forms: a member's quote and a plant picture (CORE-07, wireframe p9, element 6).
// Expects $member — a users row with username, full_name, avatar, location and plant_count (see $mock_quote_member).
// It builds trust; it carries no task, so a screen reader meets it after the form (it comes second in the HTML).
?>
<aside class="auth-panel" aria-label="From our community">
  <figure class="auth-quote">
    <blockquote>
      <p>“My pothos has moved house with me three times. Now it finally has its own profile.”</p>
    </blockquote>
    <figcaption class="auth-quote-member">
      <?php partial('avatar', ['member' => $member, 'size' => 'sm']); ?>
      <span>
        <strong>@<?= e($member['username']) ?></strong>
        <span class="text-small"><?= e($member['location']) ?> · <?= e($member['plant_count']) ?> plants</span>
      </span>
    </figcaption>
  </figure>
  <img class="auth-panel-picture" src="<?= e(photo_url('mock/monty.svg')) ?>" alt="">
</aside>
