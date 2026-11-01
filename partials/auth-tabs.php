<?php
// The Sign up · Log in switch at the top of both forms (CORE-07, CORE-09, wireframe p9, element 1).
// Expects $active — 'register' or 'login'. The tabs are links between register.php and login.php:
// both pages share one layout, so switching looks like a tab change and needs no JavaScript.

$auth_tabs = [
    'register' => ['label' => 'Sign up', 'href' => 'register.php'],
    'login'    => ['label' => 'Log in',  'href' => 'login.php'],
];
?>
<nav class="auth-tabs" aria-label="Sign up or log in">
  <?php foreach ($auth_tabs as $key => $tab): ?>
    <a class="auth-tab<?= $key === $active ? ' is-active' : '' ?>" href="<?= e(url($tab['href'])) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>><?= e($tab['label']) ?></a>
  <?php endforeach; ?>
</nav>
