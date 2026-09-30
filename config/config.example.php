<?php
/**
 * PlantFolio configuration TEMPLATE.
 *
 * 1. Copy this file to config/config.php (same folder).
 * 2. Fill in your own local values.
 * config/config.php is in .gitignore — never commit real passwords.
 */

return [
    // MySQL (XAMPP defaults: user "root", empty password)
    'db_host' => '127.0.0.1',
    'db_name' => 'plantfolio',
    'db_user' => 'root',
    'db_pass' => '',

    // Base URL of the site on your machine
    'base_url' => 'http://localhost/plantfolio/public',

    // Flask ML service (Phase 3). Pages fall back to manual entry if it is offline.
    'ml_service_url' => 'http://127.0.0.1:5000',

    // Uploads
    'upload_dir'      => __DIR__ . '/../public/uploads',
    'max_upload_mb'   => 5,
];
