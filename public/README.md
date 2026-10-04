# public/ — the web root

Only this folder is meant to be served by Apache: <http://localhost/plantfolio/public/>.
One PHP file per page (wireframes p25):

| Code | File | Code | File |
|---|---|---|---|
| P01 | `index.php` | P09 | `explore.php` |
| P02 | `register.php`, `login.php`, `logout.php` | P10 | `exchange.php` |
| P03 | `dashboard.php` | P11 | `listing.php` |
| P04 | `garden.php` | P12 | `listing-form.php` |
| P05 | `plant.php` | P13 | `my-exchanges.php` |
| P06 | `plant-form.php` | P14 | `user.php` |
| P07 | `identify.php` | P15 | `settings.php` |
| P08 | `health-check.php` | dev | `styleguide.php` — component gallery, removed or protected before release |

- `api/` — small JSON endpoints for `fetch()` calls (e.g. `check-username.php` for CORE-08, later Mark done and likes)
- `assets/css/` — `variables.css` (design tokens) · `components.css` (buttons, forms, badges, cards) · `layout.css` (grid, navigation, breakpoints)
- `assets/js/` — `app.js` (tabs, modals, toasts, nav) · `forms.js` (validation, upload preview) · `filters.js` (chips, sort, query strings)
- `assets/img/` — logo, icons and `mock/` photos used in the frontend stage (keep each under ~200 KB)
- `uploads/` — user plant photos (git-ignored; only `.gitkeep` is tracked)

During stage F (weeks 1–7) pages read from `includes/mock-data.php`; from stage B they read from MySQL through `includes/db.php`.
