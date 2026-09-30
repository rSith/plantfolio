<?php
// Temporary landing page — replaced by the real Landing page (P01) in MOD-10.
// Its only job right now: prove the repo is cloned and Apache/PHP serve /public.
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PlantFolio</title>
  <style>
    body { font-family: system-ui, sans-serif; background: #F7F3EA; color: #1F2A24;
           display: grid; place-items: center; min-height: 100vh; margin: 0; }
    main { text-align: center; padding: 16px; }
    h1 { color: #2F5D46; margin-bottom: .25rem; }
  </style>
</head>
<body>
  <main>
    <h1>PlantFolio 🌿</h1>
    <p>Where your plants tell their story.</p>
    <p><small>Setup works — PHP <?= htmlspecialchars(PHP_VERSION) ?> is running.</small></p>
  </main>
</body>
</html>
