# CLAUDE.md — PlantFolio project guide

PlantFolio is a mobile-responsive web platform where plant lovers build a profile for every plant,
track its health, swap plants and diagnose problems from a photo. It is our group coursework for the
**Plant Care & Exchange Community** brief (B.Sc. (Hons) Computer Science, Wayamba University of Sri Lanka),
extended with two ML features: plant species recognition and plant disease detection.

**Who you're working with:** Ranshitha, the team leader and lead developer. I build every module with
your help; six teammates (no web experience) review, test and present. I'm a beginner at web
development, so teach as you build.

---

## Where we are now

| | |
|---|---|
| **Done (week 0)** | Project proposal · 25-page UX & wireframe document · clickable Figma prototype · repo skeleton |
| **Current stage** | **F — Frontend first with mock data**, weeks 1–7 (5 Oct – 22 Nov 2026) |
| **Next stages** | B — wire to PHP + MySQL (wk 8–10) · M — ML service (wk 11–14) · D — test, report, submit (wk 15–16) |
| **Build order** | [`docs/ROADMAP.md`](docs/ROADMAP.md) — follow it week by week |
| **Task definitions** | PlantFolio Task Sheet (IDs like `CORE-14`, `COM-07`, `ML-12`) |

> Update this table when a stage finishes, so every new session starts from the right place.

---

## Tech stack (locked)

| Layer | Use | Notes |
|---|---|---|
| Pages | **Plain PHP 8+**, one `.php` file per page in `public/` | No framework (no Laravel/CodeIgniter) |
| Styling | **Hand-written CSS** with custom properties (design tokens) | No Bootstrap or Tailwind — our design system is custom |
| Scripts | **Vanilla JavaScript** (ES6, `fetch()`) | No React, Vue, jQuery or build tools |
| Fonts & icons | Lora + Poppins (Google Fonts) · Lucide icons | |
| Database | **MySQL** (InnoDB, utf8mb4) through **PDO** prepared statements | No ORM |
| Local server | **XAMPP** (Apache + PHP + MySQL) | |
| ML service | **Python 3.10+, Flask, TensorFlow/Keras**, trained on Google Colab | Separate service, called from PHP with cURL |
| Version control | Git + GitHub — `main` ← `develop` ← `feature/*` | See [CONTRIBUTING.md](CONTRIBUTING.md) |

**Do not use:** Flask/Jinja/SQLAlchemy for the website (Python is *only* for the ML service), Node.js,
npm build steps, Composer packages, Docker, MongoDB, or any CSS/JS framework — unless I approve it first.

---

## How the system fits together

```
Browser ──HTTP──▶ PHP pages (public/*.php)
                     │  require includes/  (db, auth, header, flash)  ·  render partials/
                     ├──PDO──▶ MySQL (10+ tables)
                     ├──saves──▶ public/uploads/  (resized photos)
                     └──cURL──▶ Flask ML service (localhost:5000)  /predict/species · /predict/disease
```

- The browser only ever talks to PHP. PHP sends photos to Flask, stores the JSON reply in `ml_predictions`.
- If Flask is offline, pages fall back to manual entry — the site must never break because of ML.
- Pages are **server-rendered**. Forms POST normally first; `fetch()` is added later only where the
  wireframes ask for no-reload actions (username check, Mark done, likes).

---

## Folder structure (locked)

```
PlantFolio/
├── public/                 # Web root — the only folder Apache should serve
│   ├── index.php … settings.php   # one file per page (table below)
│   ├── styleguide.php      # component gallery (dev only, week 1)
│   ├── api/                # small JSON endpoints for fetch() (e.g. check-username.php)
│   ├── assets/
│   │   ├── css/            # variables.css (tokens) · components.css · layout.css
│   │   ├── js/             # app.js · forms.js · filters.js
│   │   └── img/            # logo, icons, mock/ photos for the frontend stage
│   └── uploads/            # user photos (git-ignored)
├── includes/               # shared PHP logic — not web-accessible
│   ├── mock-data.php       # frontend stage only: arrays shaped like DB rows
│   ├── header.php · footer.php · flash.php · helpers.php
│   ├── db.php · auth.php · upload.php · ml-client.php   # backend + ML stages
├── partials/               # reusable UI pieces (plant-card.php, listing-card.php …)
├── config/                 # config.example.php (committed) · config.php (git-ignored)
├── database/               # schema.sql · seed.sql · erd.png · species.csv
├── ml-service/             # Flask app, notebooks/, models/ (git-ignored)
├── docs/                   # proposal, wireframes, ROADMAP, conventions, report drafts
└── .github/                # pull-request template
```

Don't add top-level folders or move files without asking — teammates' READMEs and PRs depend on these paths.

### Pages (from the wireframes, p8–p22)

| Code | Page | File | Access | Wireframe | Style |
|---|---|---|---|---|---|
| P01 | Landing | `index.php` | Public | p8 | Warm |
| P02 | Sign up / Log in | `register.php`, `login.php`, `logout.php` | Public | p9 | Warm |
| P03 | Dashboard | `dashboard.php` | Login | p10 | Functional |
| P04 | My Garden | `garden.php` | Login | p11 | Both |
| P05 | Plant Profile | `plant.php?id=` | Login · public read | p12 | Both |
| P06 | Add / Edit Plant | `plant-form.php` | Login | p13 | Functional |
| P07 | Plant Identifier ✦AI | `identify.php` | Login | p14 | Functional |
| P08 | Health Check ✦AI | `health-check.php` | Login | p15 | Functional |
| P09 | Explore & Search | `explore.php?q=` | Public | p16 | Both |
| P10 | Exchange Marketplace | `exchange.php` | Public | p17 | Both |
| P11 | Listing Detail | `listing.php?id=` | Public · interest needs login | p18 | Both |
| P12 | Create Listing | `listing-form.php` | Login | p19 | Functional |
| P13 | My Exchanges | `my-exchanges.php` | Login | p20 | Functional |
| P14 | Public Profile | `user.php?u=` | Public | p21 | Warm |
| P15 | Settings | `settings.php` | Login | p22 | Functional |

Shared states S1–S6 (empty garden, analysing, low confidence, rate modal, log-update modal,
confirm + toast) are on wireframe p24; mobile layouts on p23.

---

## Build approach: frontend first, then wire

1. **Stage F (weeks 1–7): every page is a real `.php` file** that uses `header.php`, `footer.php` and
   the partials, but reads its data from `includes/mock-data.php`. No database, no sessions yet.
2. **Mock data = future database rows.** Shape each array exactly like the row a future query will
   return, using the real column names from [`database/README.md`](database/README.md) — including
   joined columns — so wiring later means swapping the data source, not redesigning the page:
   ```php
   // includes/mock-data.php — replaced by PDO queries in stage B
   $mock_plants = [
     ['id' => 42, 'nickname' => 'Monty', 'health_status' => 'thriving', 'photo' => 'mock/monty.jpg',
      'scientific_name' => 'Monstera deliciosa', 'common_name' => 'Swiss cheese plant', // from plant_species
      'collection_name' => 'Living room',                                                 // from collections
      'task' => 'water', 'interval_days' => 7, 'next_due' => '2026-09-30'],              // from care_reminders
   ];
   ```
3. **Use the wireframe sample content** (Nimali / @nimali.grows, Monty, Goldie, Tommy, Kurunegala …)
   so pages can be compared side by side with the PDF and the Figma prototype.
4. **JavaScript in stage F is UI-only**: tabs, modals, toasts, grid/list toggle, client-side
   validation, upload preview. Anything that changes data waits for stage B.
5. A Task Sheet task is **"In progress"** while it is UI-only and **"Done"** only after it is wired,
   tested and merged. Say which half you are doing.
6. **Stage B order** follows the Task Sheet dependencies: schema → db.php → auth → collections →
   plants → uploads → health logs → reminders → community → exchange → ratings (see ROADMAP).

---

## Design system (wireframes p6–p7, Figma "Design system" page)

Put these in `public/assets/css/variables.css` and use the variables everywhere — never hard-code a hex value in a component.

| Token | Hex | Use |
|---|---|---|
| Forest | `#2E4A2B` | Headings, logo, selected states |
| Leaf | `#4A7043` | Primary buttons, links, bars (5.7:1 with white) |
| Sage | `#E4ECDC` | Tags, tints, highlighted areas |
| Cream | `#F7F4EC` | Page background |
| Terracotta | `#B5552F` | Accent, "due today" |
| Ink | `#2B2B28` | Body text |
| Muted | `#6E6C62` | Secondary text |
| Line | `#E6E0D2` | Borders and dividers |

**Health status** (always shown with its word, never colour alone):
Thriving `#2F6E3C` on `#E3F1E4` · Stable `#3F5F84` on `#E4ECF5` · Needs attention `#8A5A0B` on `#FBEFD5` · Sick `#9A3524` on `#F7E0DA`.
Database values: `thriving`, `stable`, `needs_attention`, `sick`. Red is used only for overdue care; terracotta for due today.

- **Type:** Lora 600 for display (46), H1 (32), H2 (24), plant names and italic scientific names ·
  Poppins for everything functional — H3 600/18, body 400/14–15 line-height 1.6, small 12–13, labels 600/12 caps.
- **Layout:** 8-point spacing (4, 8, 12, 16, 24, 32, 48) · corners: inputs 10 px, cards 14 px, badges fully round ·
  12-column grid, 1040 px content, 24 px gutters.
- **Breakpoints (mobile-first):** base styles for phones, `min-width: 768px` tablet, `min-width: 1024px` desktop.
  Mobile has a bottom bar (Home, Garden, +, Exchange, Profile); touch targets at least 44 × 44 px.
- **Images:** cards 4:3, thumbnails 1:1; uploads resized to 1600 px on the long side; JPG/PNG up to 5 MB.
- **AI confidence words are fixed:** High ≥ 80% (green) · Medium 60–79% (amber) · Not sure < 60% (grey).
  AI suggests, the user decides — nothing is saved until the user presses Save.
- **One primary button per area**; button labels are verbs; destructive actions confirm in a dialog that names the item.

---

## Coding conventions

Until `docs/conventions.md` (task DES-11) is written, follow these:

- **Files:** kebab-case (`plant-form.php`, `listing-card.php`). **PHP functions and variables:** snake_case
  (`require_login()`, `$plant_id`). **JS:** camelCase. **CSS classes:** kebab-case with simple modifiers (`.btn`, `.btn-primary`, `.badge-sick`).
- **Database:** snake_case, plural table names (`care_reminders`), `id` primary keys, `<table>_id` foreign keys.
- Every page starts with the same pattern: `require` config/helpers → (stage B: `require_login()`) →
  load data → set `$page_title` → `include header.php` → HTML → `include footer.php`.
- Escape every value printed into HTML with a helper `e()` (wraps `htmlspecialchars`) — **including mock
  data**, so the habit is there before real user input arrives.
- Keep PHP logic at the top of the file and HTML below it; no SQL inside HTML.
- Scripts load with `defer`; no inline `onclick` handlers; no `console.log` left behind.
- Comment *why*, not *what*. Each file starts with a one-line comment saying what it is and which task ID it belongs to.

---

## Security rules (check these on every change from stage B onward)

- **SQL:** PDO prepared statements only, `ATTR_EMULATE_PREPARES` off, exceptions on. Never build SQL by concatenating input.
- **Output:** escape with `e()` everywhere; never echo raw `$_GET`/`$_POST`.
- **Passwords:** `password_hash()` / `password_verify()`; one generic login error; lock for 15 min after 5 failures.
- **Sessions:** `session_regenerate_id(true)` on login; logout destroys the session.
- **CSRF:** a per-session token in every POST form, checked on every POST.
- **Access:** `require_login()` on private pages; check **ownership** before every edit or delete (try another user's ID in the URL).
- **Uploads:** check MIME with `finfo` (JPG/PNG), max 5 MB, random file names, resize to 1600 px, never execute uploaded files.
- **Privacy:** contact details appear only after "I'm interested", and only the fields the owner chose; private plants return Not found to others.
- **Secrets:** real credentials only in `config/config.php` (git-ignored). Never commit passwords, API keys, uploads, datasets or model files.

---

## Working with me (Claude Code)

1. **I'm a beginner.** Explain what each file does, why it's structured that way, and how it fits the
   architecture. Don't assume I know PHP, CSS layout or JavaScript internals.
2. **One task at a time, in roadmap order.** Work on one Task Sheet ID (or one small step of it) per
   request. Never build ahead into a later week or stage.
3. **Plan before code.** Before writing anything, tell me: the task ID, which files you'll create or change,
   and how they connect to existing code. Wait for my OK.
4. **Explain every change** in plain English: what the file does, why it's built this way, how to test it,
   and which other files it relates to.
5. **Flag uncertainty.** Point out anything you're unsure of or any place the wireframe, proposal and Task
   Sheet disagree — ask me rather than guessing.
6. **Commit after each step** on the current `feature/*` branch, using the team format from CONTRIBUTING.md:
   ```
   feat(garden): build My Garden page with mock data (CORE-14)

   Optional longer explanation of what changed and why.
   ```
   Never commit to `main` or `develop`, and never push, merge or open a PR unless I ask.
7. **The viva rule:** if you don't understand why I made a choice (a file name, a field, a layout), ask me
   before changing it. I must be able to explain every part of this project.

---

## Testing

- **Stage F:** compare each page with its wireframe page and the Figma prototype; check 375, 768 and
  1024+ px in DevTools; no horizontal scroll; no console errors; keyboard focus visible.
- **Stage B onward:** run the Test Plan cases for the task (TC-001 … TC-058), then try wrong inputs:
  empty forms, wrong password, oversized or wrong-type files, `<script>` in text fields, `' OR '1'='1` in
  search, and another user's ID in the URL. Log failures in the Task Sheet Issue Log.
- Test on a real phone before a page is called finished.

## Before you run anything

- [ ] XAMPP Apache running (MySQL too from stage B); repo cloned into `C:/xampp/htdocs/plantfolio`
- [ ] `config/config.php` created from `config/config.example.php`
- [ ] Site opens at <http://localhost/plantfolio/public/>
- [ ] Stage B: database `plantfolio` created and `database/schema.sql` + `seed.sql` imported
- [ ] Stage M: `ml-service/venv` created and `pip install -r requirements.txt` done

## If something breaks

- Paste the **full error message** (browser console, PHP error, Apache log or terminal).
- Say what you were doing when it broke and which page/task you were on.
- I'll ask questions to narrow it down before changing code.

## Key references

| Need | Where |
|---|---|
| Week-by-week build order | [`docs/ROADMAP.md`](docs/ROADMAP.md) |
| Scope, architecture, ML plan, success criteria | `docs/PlantFolio — Project Proposal.pdf` |
| Page layouts, components, states, handoff table | `docs/PlantFolio_Wireframes_UX_Structure.pdf` |
| Clickable prototype | [Figma file](https://www.figma.com/design/el5WGQz2oTerXiAfFiKrPS) |
| Tables and fields | [`database/README.md`](database/README.md) |
| ML API contract | [`ml-service/README.md`](ml-service/README.md) |
| Branches, commits, PRs | [`CONTRIBUTING.md`](CONTRIBUTING.md) |
