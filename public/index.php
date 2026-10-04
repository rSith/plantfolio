<?php
// Temporary placeholder — replaced by the real Landing page (P01, task CORE-27) in week 2.
// Its only job right now: prove the repo is cloned, Apache/PHP serve /public and the shared files load.

// config.php returns an array, so it uses plain require (require_once would return true the second time).
$config = require __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mock-data.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PlantFolio</title>
  <style>
    /* Colours from the design system (wireframes p6): Cream, Ink, Forest, Muted */
    body { font-family: system-ui, sans-serif; background: #F7F4EC; color: #2B2B28;
           display: grid; place-items: center; min-height: 100vh; margin: 0; }
    main { text-align: center; padding: 16px; }
    h1 { color: #2E4A2B; margin-bottom: .25rem; }
    small { color: #6E6C62; }
  </style>
</head>
<body>
  <main>
    <h1>PlantFolio 🌿</h1>
    <p>Where your plants tell their story.</p>
    <p><small>Setup works — PHP <?= e(PHP_VERSION) ?> is running.</small></p>
    <p><small>Mock data loaded — signed in as @<?= e($current_user['username']) ?> (<?= e($config['app_env']) ?>).</small></p>
  </main>
</body>
</html>
