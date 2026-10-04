# includes/

Shared PHP logic loaded with `require_once`. Not web-accessible (`.htaccess` denies direct requests).

| File | Purpose | Task · Module | Stage |
|---|---|---|---|
| `helpers.php` | `e()` output escaping, date and "due in N days" formatting, status-label helpers | MOD-01 | F (week 1) |
| `mock-data.php` | Arrays shaped like future query results (wireframe sample content). Shrinks as pages are wired; deleted at Gate 2 | MOD-01 | F only |
| `header.php` / `footer.php` | Public and logged-in top navigation, mobile bottom bar, footer; active page highlight. Session check added in stage B | CORE-06 · MOD-01 | F → B |
| `flash.php` | Toast messages (with Undo) and inline form errors | CORE-05 · MOD-01 | F → B |
| `db.php` | One shared PDO connection from `config/config.php` (exceptions on, emulated prepares off) | CORE-03 · MOD-01 | B (week 8) |
| `auth.php` | `require_login()` for private pages, ownership checks, CSRF token helpers | CORE-10, CORE-11 · MOD-03 | B (week 8) |
| `upload.php` | Secure image upload: `finfo` MIME check, 5 MB limit, random names, resize to 1600 px | CORE-17 · MOD-05 | B (week 9) |
| `ml-client.php` | cURL client for the Flask service (multipart image, 10 s timeout, offline fallback) and `ml_predictions` logging | ML-02, ML-11 · MOD-11 | M (week 11+) |

Every page follows the same pattern:
```php
<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/mock-data.php';   // stage F — replaced by db.php + auth.php in stage B
$page_title = 'My Garden';
include __DIR__ . '/../includes/header.php';
?>
<!-- page HTML -->
<?php include __DIR__ . '/../includes/footer.php'; ?>
```
