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
  <!-- Fonts and tokens: these links move into includes/header.php in CORE-06 -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,600;1,400&family=Poppins:wght@400;600&display=swap">
  <link rel="stylesheet" href="<?= e($config['base_url']) ?>/assets/css/variables.css">
  <style>
    /* Every value comes from a design token in variables.css (CORE-04) */
    body { font-family: var(--font-body); background: var(--color-cream); color: var(--color-ink);
           display: grid; place-items: center; min-height: 100vh; margin: 0; }
    main { text-align: center; padding: var(--space-16); }
    h1 { font-family: var(--font-display); font-weight: var(--weight-semibold);
         color: var(--color-forest); margin-bottom: var(--space-4); }
    small { color: var(--color-muted); }
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
