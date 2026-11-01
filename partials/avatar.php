<?php
// Avatar: a member's photo, or their initials on a tinted circle when they have no photo (CORE-05).
// Expects $member — any row with username, full_name and avatar — and optionally $size: 'sm' or 'lg'.
// It is decorative: always print the member's name or @username next to it.

$avatar_class = 'avatar' . (isset($size) ? ' avatar-' . $size : '');
?>
<?php if (!empty($member['avatar'])): ?>
  <img class="<?= e($avatar_class) ?>" src="<?= e(photo_url($member['avatar'])) ?>" alt="">
<?php else: ?>
  <span class="<?= e($avatar_class) ?> avatar-tint-<?= e(avatar_tint($member['username'])) ?>" aria-hidden="true"><?= e(initials($member['full_name'])) ?></span>
<?php endif; ?>
