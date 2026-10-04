# Contributing to PlantFolio

## Branches
```
main       ← protected. Always a working, demo-ready version. Only updated from develop via PR.
 └─ develop   ← protected, default branch. Integration branch — every finished module lands here first.
     ├─ feature/<module>-ui  ← stage F: the module's pages with mock data (weeks 1–7)
     ├─ feature/<module>     ← stage B / M: the same module wired to PHP, MySQL or the ML service
     ├─ fix/<short-name>     ← bug fixes found in review or testing
     └─ docs/<short-name>    ← documentation-only changes
```
`develop` is created once by the leader (task PLN-09) from `main`; after that, nobody commits to `main` or `develop` directly.

### Modules and branches
Each module is reviewed twice: a **UI PR** in stage F and a **wired PR** in stage B or M (see [`docs/ROADMAP.md`](docs/ROADMAP.md)).

| Module | Pages / scope | UI branch (stage F) | Wired branch (stage B/M) |
|---|---|---|---|
| MOD-01 Scaffold & shared components | tokens, components, header/nav/footer, style guide | `feature/scaffold-ui` | `feature/scaffold` |
| MOD-02 Database schema & seed data | ERD, schema.sql, seed.sql, species data | — | `feature/database` |
| MOD-03 Authentication & sessions | P02 | `feature/auth-ui` | `feature/auth` |
| MOD-04 My Garden & collections | P04 | `feature/garden-ui` | `feature/garden` |
| MOD-05 Plant profile & health story | P05, P06, uploads | `feature/plant-profile-ui` | `feature/plant-profile` |
| MOD-06 Care reminders & dashboard | P03, S1 | `feature/reminders-ui` | `feature/reminders` |
| MOD-07 Explore & search | P09 | `feature/explore-ui` | `feature/explore` |
| MOD-08 Exchange marketplace | P10, P11, P12 | `feature/exchange-ui` | `feature/exchange` |
| MOD-09 My Exchanges & ratings | P13, S4 | `feature/ratings-ui` | `feature/ratings` |
| MOD-10 Landing, public profile & settings | P01, P14, P15 | `feature/profiles-ui` | `feature/profiles` |
| MOD-11 ML: plant recognition | P07, AI on P06, Flask `/predict/species` | `feature/ml-species-ui` | `feature/ml-species` |
| MOD-12 ML: disease detection | P08, Flask `/predict/disease`, notebooks | `feature/ml-disease-ui` | `feature/ml-disease` |

**Never commit directly to `main` or `develop`** — GitHub will block it anyway.

## Daily workflow
```bash
# 1. Start from the latest develop
git checkout develop
git pull

# 2. Create your branch (first time only)
git checkout -b feature/garden-ui

# 3. Work, then commit small, meaningful steps
git add .
git commit -m "feat(garden): add collection tabs with mock data (CORE-14)"

# 4. Push your branch
git push -u origin feature/garden-ui   # first push
git push                               # after that

# 5. Keep your branch up to date while you work (at least daily)
git checkout develop && git pull
git checkout feature/garden-ui
git merge develop                      # fix any conflicts, then commit
```

## Commit messages
Format: `type(scope): what changed (TASK-ID)` — short, present tense. The task ID links the commit to the Task Sheet.

| type | when |
|---|---|
| `feat` | new feature or page |
| `fix` | bug fix |
| `style` | CSS / layout only |
| `refactor` | code change with no behaviour change |
| `db` | schema or seed changes |
| `docs` | README, comments, documentation |
| `test` | test cases or evidence |
| `chore` | setup, config, dependencies |

Examples: `style(scaffold): add design tokens for palette and health colours (CORE-04)`,
`feat(garden): wire My Garden to the plants table (CORE-14)`, `fix(upload): reject files over 5 MB (CORE-17)`,
`db: add plant_likes table (COM-15)`.

## Pull requests
1. Push your branch and open a PR on GitHub: **base `develop` ← compare `feature/...`**.
2. Fill in every section of the PR template (it loads automatically) and tick **UI** or **Wired**.
3. Assign the module's **reviewer** (Task Sheet → Team tab) and link the task IDs.
4. Update the Task Sheet: Status → *In progress* for a UI PR, *In review* for a wired PR; Review → *In review*; add the PR link in *Evidence*.
5. The reviewer pulls the branch, runs it locally, works through the checklist and either
   **Approves** or **Requests changes** — within 3 days.
6. Fix requested changes with new commits on the same branch (the PR updates automatically).
7. Once approved, the leader merges with **Create a merge commit** and deletes the branch.

### Releasing to main
At the UI freeze and at each gate, the leader opens a PR **`main` ← `develop`** titled
`Release: <milestone>`. It needs **1 approval** from another member. After merging, tag it:
```bash
git checkout main && git pull
git tag -a v1.0-core -m "Gate 2: coursework complete"
git push origin v1.0-core
```

| Tag | When | Roadmap week |
|---|---|---|
| `v0.5-ui` | UI freeze — all pages with mock data | 7 |
| `v0.8-gate1` | Gate 1 — core foundation | 9 |
| `v1.0-core` | Gate 2 — coursework complete (+ database dump) — COM-19 | 10 |
| `v1.0-ml` | Gate 3 — ML integrated | 14 |
| `v1.0` | Final release and submission (GitHub release with SQL dump and notebooks) — DEP-19 | 16 |

## Reviewer checklist
**UI PR (stage F)**
- Runs locally on a fresh pull of the branch (Apache only, no database needed)
- Matches its wireframe page (and the Figma prototype where it exists); uses the shared components and CSS variables
- Works at 375, 768 and 1024+ px — no horizontal scroll, touch targets ≥ 44 px, no console errors
- Output escaped with `e()`, images have alt text, inputs have labels, focus is visible

**Wired PR (stage B / M)**
- Runs locally on a fresh pull, with `schema.sql` / `seed.sql` re-imported if they changed
- Assigned Test Plan cases pass (results logged in the Task Sheet)
- Security: prepared statements, escaped output, CSRF on POST forms, login and ownership checks
- Readable, commented, follows the PHP coding conventions (`docs/conventions.md`)
- Still works at 375 px; review comments are specific and constructive

## Never commit
`config/config.php`, passwords/API keys, `venv/`, trained model files, datasets, or user uploads.
If you accidentally commit a secret, tell the leader immediately — it must be changed, not just deleted.
