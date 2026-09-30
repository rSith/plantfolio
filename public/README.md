# public/  — the web root

Only this folder is meant to be served by Apache. One PHP file per page (see Wireframes p25):
`index.php`, `register.php`, `login.php`, `logout.php`, `dashboard.php`, `garden.php`, `plant.php`,
`plant-form.php`, `identify.php`, `health-check.php`, `explore.php`, `exchange.php`, `listing.php`,
`listing-form.php`, `my-exchanges.php`, `user.php`, `settings.php`.

- `assets/css`, `assets/js`, `assets/img` — design tokens, styles, scripts, static images
- `uploads/` — user plant photos (git-ignored; only `.gitkeep` is tracked)
