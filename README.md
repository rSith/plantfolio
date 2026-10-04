# PlantFolio 🌿
*Where your plants tell their story.*

A mobile-responsive web platform where plant lovers build a profile for every plant they own, track its health, trade plants with others, and diagnose problems from a photo. Built for the **Plant Care & Exchange Community** coursework brief — B.Sc. (Hons) Computer Science, Wayamba University of Sri Lanka — and extended with two machine-learning features: plant species recognition and plant disease detection.

> **Status — 4 Oct 2026:** planning and design are complete (proposal, 25-page wireframes, clickable Figma prototype, this repo).
> **Next:** frontend-first build starts **Monday 5 Oct 2026** — all 15 pages with mock data in weeks 1–7, then PHP + MySQL, then ML.
> See [`docs/ROADMAP.md`](docs/ROADMAP.md).

## Features
**Core (coursework brief)** — secure sign-up and login · plant profiles with species care information · named collections (public or private) · health tracking with a dated plant story · care reminders with a due/overdue dashboard · search and discovery (plants and `@usernames`) · exchange marketplace with interest requests and contact reveal · trader ratings · responsive design for phone, tablet and desktop.

**ML enhancements** — ✦ *Plant Identifier*: top-3 species from a photo, applied to a new plant in one click · ✦ *Health Check*: a CNN predicts leaf disease with a confidence score and an action plan. Both are helpers: below 60 % confidence they say "Not sure", and nothing is saved without the user's OK.

## Design
| | |
|---|---|
| Clickable prototype | **[PlantFolio on Figma](https://www.figma.com/design/el5WGQz2oTerXiAfFiKrPS)** — journeys A–D, 32 desktop screens, 4 mobile screens, design-system page |
| Wireframes & UX structure | [`docs/PlantFolio_Wireframes_UX_Structure.pdf`](docs/PlantFolio_Wireframes_UX_Structure.pdf) — 15 pages, design system, states, build handoff |
| Project proposal | [`docs/PlantFolio — Project Proposal.pdf`](<docs/PlantFolio — Project Proposal.pdf>) — scope, architecture, database, ML plan, risks |

To present the prototype: open the **🌿 Prototype** page → **Present** → pick a flow (D · First visit, A · Add an unknown plant, B · Diagnose a sick plant, C · Swap a plant, Mobile · 375 px). P07, P09, P12, P14 and P15 are not in the prototype yet.

## Tech stack
| Layer | Technology |
|---|---|
| Front end | HTML5, hand-written CSS with design tokens (no CSS framework), vanilla JavaScript · mobile-first from 375 px · Lora + Poppins, Lucide icons |
| Back end | Plain PHP 8+ with PDO (no framework) |
| Database | MySQL (InnoDB, utf8mb4) |
| ML service | Python 3.10+, Flask, TensorFlow/Keras — models trained on Google Colab, called from PHP over REST |
| Local server | XAMPP (Apache + PHP + MySQL) |
| Tools | VS Code, Git + GitHub (`main` ← `develop` ← `feature/*`), Figma |

## How we're building it
We build **frontend first**: every page is made to match its wireframe using mock data shaped like real database rows, then wired to PHP and MySQL, then the ML service is added. The three quality gates from the proposal stay the same — they just land later.

| Stage | Weeks | Dates | Outcome |
|---|---|---|---|
| 0 · Planning & design | 0 | to 4 Oct | ✅ Proposal, wireframes, Figma prototype, repo |
| F · Frontend with mock data | 1–7 | 5 Oct – 22 Nov | All 15 pages and shared states, responsive, no backend → **UI freeze** |
| B · Backend & database | 8–10 | 23 Nov – 13 Dec | Pages wired to PHP + MySQL → **Gate 1** (wk 9) · **Gate 2: coursework complete** (wk 10, tag `v1.0-core`) |
| M · ML features | 11–14 | 14 Dec – 10 Jan | Flask service, disease + species models, identifier and health check → **Gate 3** |
| D · Testing & delivery | 15–16 | 11 – 24 Jan | Security review, usability tests, report, slides, release `v1.0` |

Dates assume the submission is around late January 2027 — they will be re-baselined once the official date is confirmed (task PLN-04). Full plan with task IDs: [`docs/ROADMAP.md`](docs/ROADMAP.md).

## Folder structure
```
plantfolio/
├── public/          # Web root — one PHP file per page (index.php … settings.php)
│   ├── api/         #   small JSON endpoints for fetch() calls
│   ├── assets/      #   css/ (variables, components, layout) · js/ · img/
│   └── uploads/     #   user photos (git-ignored)
├── includes/        # mock-data.php (frontend stage), header/footer, helpers, db, auth, upload, ml-client
├── partials/        # plant-card.php, listing-card.php and other reusable pieces
├── config/          # config.example.php (config.php is git-ignored)
├── database/        # schema.sql, seed.sql, ERD, species care data
├── ml-service/      # Flask API, notebooks/, models/ (git-ignored)
├── docs/            # Proposal, wireframes, roadmap, conventions, report drafts
├── .github/         # Pull-request template
└── CLAUDE.md        # Guide for Claude Code (stack, rules, design tokens)
```

## Run it locally
1. Install **XAMPP** (PHP 8+), **Git** and **VS Code**.
2. Clone into the XAMPP web folder and switch to `develop`:
   ```bash
   cd C:/xampp/htdocs        # macOS: /Applications/XAMPP/htdocs
   git clone https://github.com/rSith/plantfolio.git
   cd plantfolio
   git checkout develop
   ```
   *(`develop` is created once by the leader in task PLN-09; until then use `main`.)*
3. Create your local config: `cp config/config.example.php config/config.php`, then edit it.
4. Start **Apache** in the XAMPP Control Panel and open <http://localhost/plantfolio/public/>.
5. **From week 8 (stage B):** start **MySQL**, create a database called `plantfolio` in phpMyAdmin and import `database/schema.sql`, then `database/seed.sql`.
6. **From week 11 (stage M):** start the ML service — see [`ml-service/README.md`](ml-service/README.md). The site still works without it.

During stage F no database is needed — pages read from `includes/mock-data.php`.

## How we work
Branching, commits and pull requests are described in [CONTRIBUTING.md](CONTRIBUTING.md). Tasks, the test plan, risks and the weekly review live in the **PlantFolio Task Sheet**.

## Team working agreement *(agreed in PLN-06 — fill in)*
- **Weekly review:** _day / time_ (20 minutes, logged on the Task Sheet's Weekly Review tab)
- **Communication channel:** _e.g. WhatsApp group / Discord_
- **Review turnaround:** reviewers respond to a PR within **3 days**
- **Task tracking:** PlantFolio Task Sheet — update your task status when a PR opens or merges

### Definition of Done
Each module goes through two pull requests: a **UI PR** (stage F, mock data) and a **wired PR** (stage B or M).

A **UI PR** is ready to merge when:
- [ ] The page matches its wireframe (and the Figma prototype where it exists), using the shared components
- [ ] It works at 375, 768 and 1024+ px with no horizontal scroll and no console errors
- [ ] All output is escaped, images have alt text, inputs have labels

A **task is Done** when:
- [ ] It meets the task's definition of done in the Task Sheet
- [ ] Code is on a `feature/*` branch and merged into `develop` through an approved PR
- [ ] The assigned Test Plan cases pass and results are logged
- [ ] Security checklist holds (prepared statements, escaped output, CSRF on POST, access checks)
- [ ] The page works at 375 px width
- [ ] Code is readable and commented; no secrets committed

## Team
| Member | Role |
|---|---|
| Ranshitha Attanayake | Team leader · lead developer · merges PRs |
| Member 2 | Front-end & responsive QA |
| Member 3 | Database, data content & accessibility |
| Member 4 | Security review & validation testing |
| Member 5 | QA lead · usability testing |
| Member 6 | ML reviewer · plant recognition |
| Member 7 | ML reviewer · disease detection content |
