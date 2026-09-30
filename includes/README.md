# includes/

Shared PHP logic loaded with `require_once`. Not web-accessible (`.htaccess` denies direct requests).

| File | Purpose | Module |
|---|---|---|
| `db.php` | One shared PDO connection (reads `config/config.php`) | MOD-01 |
| `header.php` / `footer.php` | Top navigation, session check, footer | MOD-01 |
| `flash.php` | Toast messages and form errors | MOD-01 |
| `auth.php` | `require_login()` for private pages, CSRF helpers | MOD-03 |
