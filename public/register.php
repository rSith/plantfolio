<?php
// Sign up (P02, wireframe p9): a new member registers in under a minute (CORE-07, UI half).
// Public page. Stage F only draws the form; stage B checks it, saves the users row and logs the member in.

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mock-data.php';

// Stage B, in this order:
//   a signed-in member is sent to their Dashboard: if ($current_user) { header('Location: ' . url('dashboard.php')); exit; }
//   on POST: check the CSRF token, validate every field, check that username and email are free,
//   save the row with password_hash(), log the member in and open the empty Dashboard (wireframe p9, UX notes).

$page_title    = 'Sign up';
$page_css      = 'auth.css';
$simple_header = true;

include __DIR__ . '/../includes/header.php';
?>
<div class="container auth-page">
  <div class="auth-shell">
    <div class="auth-main">
      <?php partial('auth-tabs', ['active' => 'register']); ?>

      <h1 class="auth-title">Create your garden</h1>
      <p class="auth-intro">Free for plant lovers. Takes under a minute.</p>

      <form method="post" action="<?= e(url('register.php')) ?>">
        <div class="field">
          <label class="field-label" for="full-name">Full name</label>
          <input class="input" type="text" id="full-name" name="full_name" placeholder="Nimali Perera"
                 autocomplete="name" maxlength="100" required>
        </div>

        <?php // The "@" is drawn before the field, not typed: usernames power @mentions and profile links ?>
        <div class="field">
          <label class="field-label" for="username">Username</label>
          <div class="input-icon">
            <span class="input-prefix" aria-hidden="true">@</span>
            <input class="input" type="text" id="username" name="username" placeholder="nimali.grows"
                   autocomplete="username" autocapitalize="none" spellcheck="false"
                   minlength="3" maxlength="30" pattern="[a-z0-9._]+" required aria-describedby="username-hint">
          </div>
          <span class="field-hint" id="username-hint">Lowercase letters, numbers, dots and underscores. It becomes your profile link.</span>
        </div>

        <div class="field">
          <label class="field-label" for="email">Email</label>
          <input class="input" type="email" id="email" name="email" placeholder="nimali.p@example.com"
                 autocomplete="email" maxlength="255" required>
        </div>

        <?php // One clear rule instead of a list of requirements (p9, element 3). The meter comes alive in week 6. ?>
        <div class="field">
          <label class="field-label" for="password">Password</label>
          <input class="input" type="password" id="password" name="password"
                 autocomplete="new-password" minlength="8" pattern="(?=.*\d).{8,}" required aria-describedby="password-hint">
          <div class="strength" data-level="0" aria-hidden="true">
            <div class="strength-bars"><span></span><span></span><span></span><span></span></div>
            <span class="strength-label"></span>
          </div>
          <span class="field-hint" id="password-hint">At least 8 characters, including a number.</span>
        </div>

        <div class="field">
          <label class="field-label" for="location">Location <span class="field-optional">(optional)</span></label>
          <div class="input-icon">
            <?= icon('map-pin') ?>
            <input class="input" type="text" id="location" name="location" placeholder="Kurunegala"
                   autocomplete="address-level2" maxlength="100" aria-describedby="location-hint">
          </div>
          <span class="field-hint" id="location-hint">Helps you find exchange listings near you.</span>
        </div>

        <?php // A new tab, so reading the guidelines never loses what the visitor has typed ?>
        <label class="check auth-agree">
          <input type="checkbox" name="agree" value="1" required>
          <span>I agree to the <a href="<?= e(url('about.php#guidelines')) ?>" target="_blank" rel="noopener">community guidelines<span class="visually-hidden"> (opens in a new tab)</span></a></span>
        </label>

        <button class="btn btn-primary btn-lg auth-submit" type="submit">Create account</button>
      </form>

      <p class="auth-switch">Already have a garden? <a href="<?= e(url('login.php')) ?>">Log in</a></p>
    </div>

    <?php partial('auth-panel', ['member' => $mock_quote_member]); ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
