<?php
// Log in (P02, wireframe p9, state B): a returning member gets back into their garden (CORE-09, UI half).
// Public page. Stage F only draws the form; stage B checks the password and starts the session.

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mock-data.php';

// Stage B, on POST: check the CSRF token, find the user by email or username, password_verify(),
//   session_regenerate_id(true), then go to the Dashboard. Every failure shows the same message,
//   "Email or password is incorrect", and 5 failures lock the account for 15 minutes (CORE-12).

// Stage F: pressing Log in "logs in" as Nimali without checking anything, so a demo can go from the Landing page
// into the member pages. The browser's own checks (required fields) still run first.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mock_log_in_and_go('dashboard.php');
}

// A logged-in member does not need this page: they go to their Dashboard, as stage B will do (wireframe p8).
if ($current_user) {
    header('Location: ' . url('dashboard.php'));
    exit;
}

$page_title = 'Log in';
$page_css   = 'auth.css';

include __DIR__ . '/../includes/header.php';
?>
<div class="container auth-page">
  <div class="auth-shell">
    <div class="auth-main">
      <?php partial('auth-tabs', ['active' => 'login']); ?>

      <h1 class="auth-title">Welcome back</h1>
      <p class="auth-intro">Log in to see what your plants need today.</p>

      <form method="post" action="<?= e(url('login.php')) ?>">
        <?php // One field for both, so nobody has to remember which one they signed up with ?>
        <div class="field">
          <label class="field-label" for="login">Email or username</label>
          <input class="input" type="text" id="login" name="login" placeholder="nimali.p@example.com"
                 autocomplete="username" autocapitalize="none" spellcheck="false" maxlength="255" required>
        </div>

        <div class="field">
          <label class="field-label" for="password">Password</label>
          <input class="input" type="password" id="password" name="password" autocomplete="current-password" required>
        </div>

        <div class="auth-row">
          <label class="check">
            <input type="checkbox" name="remember" value="1">
            <span>Remember me</span>
          </label>
          <?php // No reset page exists yet (it is not in the page list), so members are sent to Contact for now ?>
          <a href="<?= e(url('about.php#contact')) ?>">Forgot password?</a>
        </div>

        <button class="btn btn-primary btn-lg auth-submit" type="submit">Log in</button>
      </form>

      <p class="auth-switch">New to PlantFolio? <a href="<?= e(url('register.php')) ?>">Create your garden</a></p>
    </div>

    <?php partial('auth-panel', ['member' => $mock_quote_member]); ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
