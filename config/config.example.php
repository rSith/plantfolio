<?php
/**
 * PlantFolio configuration TEMPLATE.
 *
 * 1. Copy this file to config/config.php (same folder).
 * 2. Fill in your own local values.
 * config/config.php is in .gitignore — never commit real passwords.
 */

return [
    // 'development' shows PHP errors on screen; 'production' hides them and logs instead (DEP-02)
    'app_env' => 'development',

    // Base URL of the site on your machine
    'base_url' => 'http://localhost/plantfolio/public',

    // MySQL — needed from stage B (week 8). XAMPP defaults: user "root", empty password.
    // Use a dedicated user with a strong password on any shared or hosted server.
    'db_host' => '127.0.0.1',
    'db_name' => 'plantfolio',
    'db_user' => 'root',
    'db_pass' => '',

    // Flask ML service — needed from stage M (week 11).
    // Pages fall back to manual entry if it is offline or slower than the timeout.
    'ml_service_url'     => 'http://127.0.0.1:5000',
    'ml_timeout_seconds' => 10,

    // Uploads (JPG/PNG only, checked on the server)
    'upload_dir'    => __DIR__ . '/../public/uploads',
    'max_upload_mb' => 5,
    'max_image_px'  => 1600,   // long side after resizing
];
