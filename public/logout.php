<?php
// Log out (P02, CORE-09, UI half): ends the member's visit and returns to the Landing page.
// Stage F: switches the preview to visitor mode. Stage B destroys the session instead (security rules):
//   $_SESSION = []; session_destroy(); then the same redirect.

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mock-data.php';

mock_become_visitor();

header('Location: ' . url('index.php'));
exit;
