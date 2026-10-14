<?php
// Account Settings (P15, wireframe p22): public profile, password, privacy defaults and reminder preferences
// in one place (CORE-26, UI half). Members only.
// Each card is its own <form>, so saving one card never touches, or throws away, what was typed in another (p22, UX notes).

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mock-data.php';

// Stage F stand-in for require_login(): a visitor (?guest) is sent to Log in, exactly as stage B will do.
if (!$current_user) {
    header('Location: ' . url('login.php'));
    exit;
}

// ── Saving a card ───────────────────────────────────────
// Every form sends a hidden field "form" naming its card. Stage F stores nothing: a card that passes its checks
// comes back to Settings with a green message in that card (Post/Redirect/Get), like Save on the plant form.
// Stage B, before any of this: check the CSRF token, then update the users row (password: password_verify() the
// current one, then password_hash() the new one).

$saved_messages = [
    'profile'   => 'Profile saved.',
    'email'     => 'Email updated.',
    'password'  => 'Password updated.',
    'privacy'   => 'Privacy saved.',
    'reminders' => 'Reminder settings saved.',
];
$card_of = ['profile' => 'profile', 'email' => 'account', 'password' => 'account',
            'privacy' => 'privacy', 'reminders' => 'reminders'];

$errors = [];   // field name => message, printed under that field

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = $_POST['form'] ?? '';

    if (array_key_exists($form, $saved_messages)) {
        if ($form === 'password') {
            // The same rules the browser checks, checked again here: the browser's checks can be switched off.
            // Stage B also adds: password_verify() of the current password, else "Current password is incorrect".
            $new     = (string) ($_POST['new_password'] ?? '');
            $confirm = (string) ($_POST['confirm_password'] ?? '');

            if (strlen($new) < 8 || !preg_match('/\d/', $new)) {
                $errors['new_password'] = 'Use at least 8 characters, including a number.';
            }
            if ($new !== $confirm) {
                $errors['confirm_password'] = "Passwords don't match";
            }
        }

        if (!$errors) {
            header('Location: ' . url('settings.php?saved=' . $form . '#' . $card_of[$form]));
            exit;
        }
    }
}

// Which card just saved, from the redirect above; anything else in ?saved= is ignored
$saved = $_GET['saved'] ?? '';
if (!array_key_exists($saved, $saved_messages)) {
    $saved = '';
}

/** The green "saved" message for one card, or nothing. role="status" makes a screen reader announce it. */
function saved_message($forms, $saved, $saved_messages)
{
    if (!in_array($saved, $forms, true)) {
        return '';
    }
    return '<p class="alert alert-success settings-alert" role="status">' . icon('check') . ' ' . e($saved_messages[$saved]) . '</p>';
}

// Section menu (p22, element 1): jump links to each card. The ids are the cards' id attributes.
$sections = [
    'profile'   => ['label' => 'Profile',              'icon' => 'user'],
    'account'   => ['label' => 'Account and security', 'icon' => 'lock'],
    'privacy'   => ['label' => 'Privacy',              'icon' => 'eye'],
    'reminders' => ['label' => 'Reminders',            'icon' => 'bell'],
];

$bio_limit = 160;   // p22: "92 / 160"

$page_title = 'Settings';
$page_css   = 'settings.css';

include __DIR__ . '/../includes/header.php';
?>
<div class="container page">
  <h1 class="settings-title">Settings</h1>

  <div class="settings-grid">
    <nav class="settings-menu" aria-label="Settings sections">
      <ul>
        <?php foreach ($sections as $id => $section): ?>
          <li><a href="#<?= e($id) ?>"><?= icon($section['icon']) ?> <?= e($section['label']) ?></a></li>
        <?php endforeach; ?>
        <li class="settings-menu-danger"><a href="#delete"><?= icon('trash-2') ?> Delete account</a></li>
      </ul>
    </nav>

    <div class="settings-cards">
      <?php // Profile (p22, element 2): what other members see. multipart/form-data because it can carry a photo. ?>
      <section class="settings-card" id="profile" aria-labelledby="profile-title">
        <h2 class="settings-card-title" id="profile-title">Profile</h2>
        <p class="settings-card-intro">What other members see on your profile.</p>
        <?= saved_message(['profile'], $saved, $saved_messages) ?>

        <form method="post" action="<?= e(url('settings.php#profile')) ?>" enctype="multipart/form-data">
          <input type="hidden" name="form" value="profile">

          <div class="settings-photo">
            <?php partial('avatar', ['member' => $current_user, 'size' => 'lg']); ?>
            <?php // The label opens the hidden file input, like the photo area on the plant form ?>
            <label class="btn btn-secondary btn-sm" for="avatar-file"><?= icon('upload') ?> Change photo</label>
            <input class="visually-hidden" type="file" id="avatar-file" name="avatar" accept="image/jpeg,image/png">
            <?php // Remove only appears when there is a photo to remove; without one the initials are shown ?>
            <?php if ($current_user['avatar']): ?>
              <button type="submit" class="btn btn-ghost btn-sm" name="remove_avatar" value="1">Remove</button>
            <?php endif; ?>
          </div>

          <div class="field-row">
            <div class="field">
              <label class="field-label" for="full-name">Display name</label>
              <input class="input" type="text" id="full-name" name="full_name" value="<?= e($current_user['full_name']) ?>"
                     autocomplete="name" maxlength="100" required>
            </div>

            <?php // Same rule as on Sign up. Stage B keeps the old @username redirecting to the new one (p22, UX notes). ?>
            <div class="field">
              <label class="field-label" for="username">Username</label>
              <div class="input-icon">
                <span class="input-prefix" aria-hidden="true">@</span>
                <input class="input" type="text" id="username" name="username" value="<?= e($current_user['username']) ?>"
                       autocomplete="username" autocapitalize="none" spellcheck="false"
                       minlength="3" maxlength="30" pattern="[a-z0-9._]+" required aria-describedby="username-hint">
              </div>
              <span class="field-hint" id="username-hint">Your old @link keeps working after a change.</span>
            </div>
          </div>

          <div class="field">
            <label class="field-label" for="bio">Bio</label>
            <textarea class="input" id="bio" name="bio" maxlength="<?= e($bio_limit) ?>" rows="3"
                      aria-describedby="bio-count"><?= e($current_user['bio']) ?></textarea>
            <?php // The count is worked out once here; week 6 JavaScript updates it while typing ?>
            <span class="field-hint bio-count" id="bio-count"><?= e(mb_strlen($current_user['bio'] ?? '')) ?> / <?= e($bio_limit) ?></span>
          </div>

          <div class="field">
            <label class="field-label" for="location">Town</label>
            <div class="input-icon">
              <?= icon('map-pin') ?>
              <input class="input" type="text" id="location" name="location" value="<?= e($current_user['location']) ?>"
                     autocomplete="address-level2" maxlength="100">
            </div>
          </div>

          <button class="btn btn-primary settings-save" type="submit">Save profile</button>
        </form>
      </section>

      <div class="settings-column">
        <?php // Account and security (p22, element 3): a new password needs the current one ?>
        <section class="settings-card" id="account" aria-labelledby="account-title">
          <h2 class="settings-card-title" id="account-title">Account and security</h2>
          <?= saved_message(['email', 'password'], $saved, $saved_messages) ?>

          <?php // "Change email" opens a small form in place: <details> needs no JavaScript ?>
          <details class="settings-email">
            <summary class="settings-card-intro"><?= e($current_user['email']) ?> · <span class="settings-link">Change email</span></summary>
            <form method="post" action="<?= e(url('settings.php#account')) ?>" class="settings-email-form">
              <input type="hidden" name="form" value="email">
              <div class="field">
                <label class="field-label" for="new-email">New email</label>
                <input class="input" type="email" id="new-email" name="email" autocomplete="email" maxlength="255" required>
              </div>
              <div class="field">
                <label class="field-label" for="email-password">Current password</label>
                <input class="input" type="password" id="email-password" name="current_password" autocomplete="current-password" required>
              </div>
              <button class="btn btn-secondary settings-save" type="submit">Update email</button>
            </form>
          </details>

          <form method="post" action="<?= e(url('settings.php#account')) ?>">
            <input type="hidden" name="form" value="password">
            <div class="field">
              <label class="field-label" for="current-password">Current password</label>
              <input class="input" type="password" id="current-password" name="current_password" autocomplete="current-password" required>
            </div>
            <?php // An error is printed under its field and linked to it with aria-describedby, so a screen reader reads it
                  // with the field. aria-invalid turns the border red. Passwords are never printed back into the form. ?>
            <div class="field">
              <label class="field-label" for="new-password">New password</label>
              <input class="input" type="password" id="new-password" name="new_password" autocomplete="new-password"
                     minlength="8" pattern="(?=.*\d).{8,}" required
                     aria-describedby="new-password-hint<?= isset($errors['new_password']) ? ' new-password-error' : '' ?>"<?= isset($errors['new_password']) ? ' aria-invalid="true"' : '' ?>>
              <span class="field-hint" id="new-password-hint">At least 8 characters, including a number.</span>
              <?php if (isset($errors['new_password'])): ?>
                <span class="field-error" id="new-password-error"><?= e($errors['new_password']) ?></span>
              <?php endif; ?>
            </div>
            <div class="field">
              <label class="field-label" for="confirm-password">Confirm new password</label>
              <input class="input" type="password" id="confirm-password" name="confirm_password" autocomplete="new-password" required
                     <?= isset($errors['confirm_password']) ? 'aria-invalid="true" aria-describedby="confirm-password-error"' : '' ?>>
              <?php if (isset($errors['confirm_password'])): ?>
                <span class="field-error" id="confirm-password-error"><?= e($errors['confirm_password']) ?></span>
              <?php endif; ?>
            </div>
            <button class="btn btn-secondary settings-save" type="submit">Update password</button>
          </form>
        </section>

        <?php // Privacy defaults (p22, element 4). A switch is a checkbox: an unticked one is not sent at all,
              // so stage B reads a missing value as 0. ?>
        <section class="settings-card" id="privacy" aria-labelledby="privacy-title">
          <h2 class="settings-card-title" id="privacy-title">Privacy</h2>
          <?= saved_message(['privacy'], $saved, $saved_messages) ?>
          <form method="post" action="<?= e(url('settings.php#privacy')) ?>">
            <input type="hidden" name="form" value="privacy">
            <label class="toggle settings-toggle">
              <span>New plants are public <span class="field-hint">Shown in Explore. You can still make one plant private.</span></span>
              <input type="checkbox" name="new_plants_public" value="1"<?= $current_user['new_plants_public'] ? ' checked' : '' ?>>
            </label>
            <label class="toggle settings-toggle">
              <span>Show approximate area on listings <span class="field-hint">Your town only, never your address.</span></span>
              <input type="checkbox" name="show_listing_area" value="1"<?= $current_user['show_listing_area'] ? ' checked' : '' ?>>
            </label>
            <button class="btn btn-secondary btn-sm settings-save" type="submit">Save privacy</button>
          </form>
        </section>
      </div>

      <?php // Reminder preferences (p22, element 5). Email reminders are a later feature: shown, disabled and labelled,
            // never hidden and never faked (p22, UX notes). ?>
      <section class="settings-card" id="reminders" aria-labelledby="reminders-title">
        <h2 class="settings-card-title" id="reminders-title">Reminders</h2>
        <?= saved_message(['reminders'], $saved, $saved_messages) ?>
        <form method="post" action="<?= e(url('settings.php#reminders')) ?>">
          <input type="hidden" name="form" value="reminders">
          <label class="toggle settings-toggle">
            <span>Show care tasks on Dashboard</span>
            <input type="checkbox" name="dashboard_reminders" value="1"<?= $current_user['dashboard_reminders'] ? ' checked' : '' ?>>
          </label>
          <label class="toggle settings-toggle is-disabled">
            <span>Email reminders <span class="badge badge-neutral settings-later">Coming later</span></span>
            <input type="checkbox" name="email_reminders" value="1" disabled>
          </label>
          <button class="btn btn-secondary btn-sm settings-save" type="submit">Save reminders</button>
        </form>
      </section>

      <?php // Delete account (p22, element 6): kept apart in a red card, and always confirmed in a dialog (S6, week 5) ?>
      <section class="settings-card settings-card-danger" id="delete" aria-labelledby="delete-title">
        <h2 class="settings-card-title" id="delete-title">Delete account</h2>
        <p class="settings-card-intro">Removes your plants, listings and ratings for good.</p>
        <?php // Stage F: does nothing yet. Week 5 opens the confirmation; stage B deletes the account and its files. ?>
        <button type="button" class="btn btn-danger btn-sm"><?= icon('trash-2') ?> Delete account</button>
      </section>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
