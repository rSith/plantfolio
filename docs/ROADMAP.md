# PlantFolio — Build Roadmap (frontend first)

*Version 2 · 4 October 2026 · replaces the September Flask roadmap*

This is the week-by-week order for building PlantFolio. It follows the **frontend-first** approach:
build every page to match its wireframe with mock data, then wire it to PHP and MySQL, then add the ML service.
Task IDs (`CORE-14`, `COM-07`, `ML-12` …) refer to the **PlantFolio Task Sheet**, which holds each task's
definition of done, dependencies, owner and reviewer. Page codes (`P01`–`P15`, states `S1`–`S6`) refer to
`PlantFolio_Wireframes_UX_Structure.pdf`.

**Why frontend first:** visible progress from week 1, the UX is settled before any database work, and each page
teaches HTML/CSS/JS fundamentals before PHP. Because mock data is shaped like real database rows, stage B swaps
the data source without redesigning pages.

---

## At a glance

| Stage | Weeks | Dates | What gets built | Ends with |
|---|---|---|---|---|
| 0 · Planning & design | 0 | 28 Sep – 4 Oct | Proposal, 25-page wireframes, Figma prototype, repo skeleton | ✅ MS-0 passed (30 Sep) |
| **F · Frontend with mock data** | 1–7 | 5 Oct – 22 Nov | Design tokens, components, all 15 pages, states S1–S6, responsive pass | MS-1 Kick-off (wk 1) · **UI freeze** (wk 7) |
| **B · Backend & database** | 8–10 | 23 Nov – 13 Dec | Schema, seed, auth, CRUD, uploads, reminders, community, exchange, ratings | **Gate 1** (wk 9) · **Gate 2 — coursework complete** (wk 10) |
| **M · ML features** | 11–14 | 14 Dec – 10 Jan | Flask service, disease CNN, species recognition, P07/P08 wired | **Gate 3** (wk 14) |
| **D · Testing & delivery** | 15–16 | 11 – 24 Jan | Security, usability and regression testing, report, slides, release | Release v1.0 · submission ~26 Jan 2027 |
| After submission | 17–20 | 25 Jan – 21 Feb | Retrospective, feedback fixes, portfolio polish | MS-7 Post-project review |

### Gates: original plan vs frontend first

The gate **criteria** on the Task Sheet's Milestones tab do not change — only **when** they land.

| Milestone | Exit criteria (short) | Original 12-week plan | Frontend first |
|---|---|---|---|
| MS-1 Kick-off | Approved; repo + branching live; team onboarded; ERD, schema, API contract, security checklist | Week 1 | Week 1 (database design may finish in week 2) |
| UI freeze *(new)* | All 15 pages + S1–S6 match the wireframes with mock data, at 375/768/1024 px | — | Week 7 |
| Gate 1 — Core foundation | Register → add plant with photo → log health → reminder on Dashboard → mark done | Week 4 | Week 9 |
| Gate 2 — Coursework complete | All nine core features end to end; Phase 2 tests pass; `v1.0-core` tagged | Week 8 | Week 10 |
| Gate 3 — ML integrated | Disease model ≥ 90 % test accuracy; top-3 measured; offline fallback verified | Week 11 | Week 14 |
| MS-5 Release candidate | Security review; journeys A–D; usability test; no open Critical/High issues | Week 12 | Week 16 |
| MS-6 Submission & presentation | Report, README, notebooks, v1.0 submitted; demo delivered | Week 12 | ~Week 17 |

> **Schedule check.** The proposal promised every coursework feature by week 8; frontend first moves that to week 10
> and stretches the plan to about 16 weeks. That only works if the official submission is **late January 2027 or later**.
> As soon as PLN-04 confirms the date:
> - **Date is late January or later →** keep this plan; update Start wk / End wk on the Task Sheet so Overdue flags stay meaningful.
> - **Date is earlier →** switch to *frontend first per phase*: UI for core pages (wk 1–2) → wire core (wk 3–4, Gate 1) →
>   UI for community pages (wk 5–6) → wire community (wk 7–8, Gate 2) → ML (wk 9–11). Gate 2 then lands on week 8 as proposed.
> - Either way: **core features before ML, always.** If stage B overruns, stage M shrinks — never the other way round.

---

## Stage F — Frontend with mock data (weeks 1–7)

**Rules for this stage**
- Every page is a real `.php` file in `public/` using `includes/header.php`, `includes/footer.php` and `partials/`.
- Data comes from `includes/mock-data.php` — arrays shaped like future query results (see [Mock data](#mock-data)).
- JavaScript is UI-only: tabs, modals, toasts, toggles, validation, previews. Nothing is saved yet.
- Each module gets a **UI pull request** (`feature/<module>-ui`) reviewed against the wireframe — see CONTRIBUTING.md.
- On the Task Sheet these tasks move to **In progress**; they become **Done** after stage B wires them.

### Week 1 · 5–11 Oct — Foundation
| Task | Build | Module |
|---|---|---|
| PLN-07 | Dev environment: XAMPP (PHP 8+), Git, VS Code, Python 3.10+; `phpinfo()` loads | — |
| PLN-09 | Create `develop`, protect `main` and `develop`, set `develop` as default branch | — |
| — | `config/config.php`, `includes/helpers.php` with `e()`, first `includes/mock-data.php` | MOD-01 |
| CORE-04 | `assets/css/variables.css` — palette, health colours, Lora + Poppins, 8-pt spacing, radii, breakpoints | MOD-01 |
| CORE-05 | `components.css` + `partials/` — buttons, form controls, badges, status chips, confidence meter, plant card, listing card, toast | MOD-01 |
| CORE-06 | `header.php` (public + logged-in), mobile bottom bar, `footer.php`, active-page highlight | MOD-01 |
| — | `public/styleguide.php` — gallery of every component (dev only) | MOD-01 |

**Learn this week:** CSS custom properties, flexbox and grid (MDN) · PHP `include`/`require`, arrays and `foreach`.
**Done when:** the style guide shows every component from wireframe p7 correctly at 375 px and 1024 px, and a blank page shows the header, bottom bar and footer.

**In parallel (planning & design tasks, due for MS-1):**
PLN-03 submit the proposal for approval · PLN-05 assign modules and reviewers · PLN-06 working agreement into README ·
PLN-10 team onboarding (weeks 1–2) · PLN-12 learning plan · DES-09 architecture note · DES-10 ML API contract ·
DES-11 PHP conventions · DES-12 security checklist ·
**DES-06/07/08 ERD + schema reconciliation + data dictionary and DES-13 species CSV — finish by the end of week 2**,
because mock data uses their column names.

### Week 2 · 12–18 Oct — Pages A: first impressions
| Page | Task(s) | Notes | Module |
|---|---|---|---|
| P01 Landing | CORE-27 | Hero, community collage, How it works (3 steps), smart-tools teaser; counts from mock data | MOD-10 |
| P02 Sign up · Log in | CORE-07, CORE-09 (UI) | One page, two tabs; password meter; optional location; community panel | MOD-03 |
| P03 Dashboard | CORE-24 (UI) | Greeting + counts, Today's care (due = terracotta, overdue = red), Needs attention, Quick actions, Garden at a glance | MOD-06 |
| S1 Empty garden | CORE-25 (UI) | Shown on Dashboard and My Garden when the mock user has no plants | MOD-06 |

**Learn:** semantic HTML, page layout with grid, forms and labels (MDN).
Also this week: DES-05 is complete — the Figma link is in the README; mark it Done.

### Week 3 · 19–25 Oct — Pages B: my plants
| Page | Task(s) | Notes | Module |
|---|---|---|---|
| P04 My Garden | CORE-14, CORE-15 (UI) | Collection tabs with counts and lock icon, search/filter/sort bar, plant cards with next-care hint, card ⋯ menu | MOD-04 |
| P05 Plant Profile | CORE-18 (UI) | Breadcrumb, gallery, identity + status, actions, care at a glance, tabs (Story, Care guide, Reminders, Photos), reminders card | MOD-05 |
| P06 Add / Edit Plant | CORE-16 (UI) | Full page (not a modal): photo upload area, details, current health, reminder defaults, visibility, sticky save bar | MOD-05 |
| P15 Settings | CORE-26 (UI) | Section menu; Profile, Account & security, Privacy, Reminders, Delete account cards | MOD-10 |

### Week 4 · 26 Oct – 1 Nov — Pages C: community
| Page | Task(s) | Notes | Module |
|---|---|---|---|
| P09 Explore | COM-01, COM-02, COM-03 (UI) | Unified search, Plants / People tabs, filter sidebar, active chips, matching people row | MOD-07 |
| P14 Public Profile | COM-05, COM-13 (UI) | Identity, stats row, tabs, collection collages, rating breakdown, latest review — no contact details | MOD-10 |
| P10 Exchange | COM-07 (UI) | How-it-works strip, filters, listing cards with type badge, distance, looking for, rating, interest count | MOD-08 |
| P11 Listing Detail | COM-08 (UI) | Gallery, summary, plant-story link, owner trust card, safety tips; state A (before interest) and state B (contact revealed) | MOD-08 |

**Learn:** reusing partials with different data; query strings (`$_GET`) to read `?id=` and `?q=` from the URL.

### Week 5 · 2–8 Nov — Pages D + interactivity A
| Page / feature | Task(s) | Notes | Module |
|---|---|---|---|
| P12 Create Listing | COM-06 (UI) | Pick-a-plant strip, Swap/Free/Either, Looking for, location and contact-sharing choices, live preview card | MOD-08 |
| P13 My Exchanges | COM-10 (UI) | Section tabs, listing selector, interest table with requester rating and New/Seen tag, rating prompt | MOD-09 |
| Tabs | — | P05 tabs, P09 Plants/People, P13 sections, P14 profile tabs — one shared script | MOD-01 |
| Modals S4, S5, S6 | COM-12, CORE-19, CORE-20 (UI) | Rate a trader, Log a health update, Delete confirmation — open/close on ×, Cancel, scrim, Esc | MOD-01 |
| Toasts with Undo | CORE-23 (UI) | "Watered Monty · next on 7 Oct — Undo"; auto-hide | MOD-06 |
| Grid / list toggle | CORE-14 (UI) | My Garden list view becomes a table | MOD-04 |

**Learn:** DOM events, `classList`, `dataset`, focus handling for modals (MDN).

### Week 6 · 9–15 Nov — Interactivity B + responsive pass
| Feature | Task(s) | Notes | Module |
|---|---|---|---|
| Client-side validation | — | Inline errors on P02, P06, P12, P15 (blur + submit); the server re-checks in stage B | all |
| Live username check | CORE-08 (UI) | `fetch('api/check-username.php')` against a mock list of taken names | MOD-03 |
| Filter chips & sort | COM-03, CORE-15 (UI) | Filters build a GET query string so URLs are shareable, as the wireframes require | MOD-07 |
| Upload preview | CORE-16 (UI) | Preview, drag-and-drop, `accept="image/*" capture="environment"`, 5 MB / type check in JS | MOD-05 |
| Responsive pass | COM-16 | Filters in a bottom sheet, My Exchanges table → stacked cards, floating List a plant, mobile + sheet (Add plant · Identify · Health check), inner pages hide the bar | MOD-01 |

**Learn:** `fetch()` and JSON, `URLSearchParams`, `FileReader`, media queries written mobile-first.

### Week 7 · 16–22 Nov — AI pages, read-only views, polish → UI freeze
| Page / feature | Task(s) | Notes | Module |
|---|---|---|---|
| P07 Plant Identifier | ML-12 (UI) | Tool switcher, photo preview, tips, top match with confidence label, care chips, alternatives, honesty note | MOD-11 |
| P08 Health Check | ML-14 (UI) | Plant link, diagnosis, plain explanation, 4-step action plan, supported-crops statement, save buttons | MOD-12 |
| S2 Analysing · S3 Not sure | ML-17 (UI) | Disabled buttons while "analysing"; below 60 % never shown as an answer | MOD-11/12 |
| AI suggestion on P06 | ML-13 (UI) | Top-3 with Use buttons, Filled-by-AI tag, manual fallback | MOD-11 |
| Public read-only views | CORE-21 (UI) | P05 for visitors: owner card and like button instead of edit controls | MOD-05 |
| Accessibility & polish | TST-07 (early) | Alt text, labels, focus states, contrast check, hover/pressed states | all |

**UI freeze (end of week 7):** every page and state matches its wireframe with mock data, passes the 375/768/1024 px
check and has no console errors. Merge `develop` into `main` and tag `v0.5-ui` so the UI-only version is preserved.

**Meanwhile, the team can start the report early:** DEP-09 (introduction, problem, requirements) and DEP-10 (design:
UX, ERD, architecture) only need the proposal and wireframes — drafting them now relieves the final weeks.

---

## Stage B — Backend & database (weeks 8–10)

Wire pages in Task Sheet dependency order. Each module gets a **wired PR** (`feature/<module>`) that must pass its Test Plan cases.
Replace one mock array at a time with a query function returning the same shape, and delete it from `mock-data.php` when done.

### Week 8 · 23–29 Nov — Database, accounts, sessions
CORE-01 `schema.sql` (InnoDB, utf8mb4, foreign keys, re-runnable) · CORE-02 `seed.sql` built from the mock data ·
CORE-03 `config.php` + `includes/db.php` (one PDO, exceptions on, emulated prepares off) ·
CORE-07 registration · CORE-08 real username check · CORE-09 login, logout, sessions ·
CORE-10 `includes/auth.php` (`require_login()`, ownership checks) · CORE-11 CSRF tokens · CORE-12 login lockout ·
CORE-26 settings saves (profile, password).
**Learn:** PDO prepared statements, `password_hash()`, sessions (PHP manual). **Tests:** TC-001 – TC-011, TC-028, TC-029.

### Week 9 · 30 Nov – 6 Dec — Core features → Gate 1
CORE-13 collections CRUD + privacy · CORE-14/15 My Garden from the database · CORE-16 plant create/edit ·
CORE-17 secure upload handler (`includes/upload.php`: `finfo`, 5 MB, random names, resize to 1600 px) ·
CORE-18 plant profile data · CORE-19 health logs + story · CORE-20 delete with files · CORE-21 public view rules ·
CORE-22 reminders · CORE-23 Mark done + Undo · CORE-24/25 Dashboard data + empty states · CORE-27 live landing counts ·
CORE-28 Phase 1 test pass · CORE-29 peer reviews MOD-01 – MOD-06.
**Gate 1 demo:** register → add plant with photo → log health → reminder appears on Dashboard → mark done.

### Week 10 · 7–13 Dec — Community & exchange → Gate 2
COM-01/02 Explore + people search · COM-03/04 filters + pagination (12 per page) · COM-05/13 public profile + rating breakdown ·
COM-06 create/edit listing · COM-07 marketplace · COM-08 listing detail · COM-09 interest + contact reveal ·
COM-10 My Exchanges · COM-11 mark exchanged / close · COM-12 trader ratings (one per person per exchange) ·
COM-14 Dashboard exchange card · COM-15 likes *(Could)* · COM-16 responsive re-check · COM-17 Phase 2 test pass ·
COM-18 peer reviews MOD-07 – MOD-10 · COM-19 merge to `main`, tag **`v1.0-core`** with a database dump.
**Gate 2:** all nine core features work end to end — the project is submittable from here.
*This is the heaviest week. If it overflows, Gate 2 moves into week 11 and stage M gives up the time.*

---

## Stage M — ML features (weeks 11–14)

The P07/P08 pages already exist from week 7, so this stage is mostly the Flask service, the models and swapping
mock results for real ones. Weeks 12–13 include the holidays — Colab training can run unattended.

| Week | Dates | Tasks |
|---|---|---|
| 11 | 14–20 Dec | ML-01 Flask skeleton with `/health` · **ML-02 PHP ↔ Flask hello-world call** (cURL multipart, 10 s timeout, offline handling) · ML-03 PlantVillage split 70/15/15 · ML-07 **decide** own MobileNetV2 vs Pl@ntNet API for recognition |
| 12 | 21–27 Dec | ML-04 baseline CNN from scratch · ML-05 fine-tune MobileNetV2 with augmentation (target ≥ 90 %) · ML-06 evaluate (test set + 20–30 phone photos) · ML-08 labelled photo set, top-1/top-3 · ML-15 guidance text per disease class |
| 13 | 28 Dec – 3 Jan | ML-09 `/predict/species` · ML-10 `/predict/disease` · ML-11 log every call in `ml_predictions` (`includes/ml-client.php`) · ML-12 and ML-14 wired |
| 14 | 4–10 Jan | ML-13 AI suggestions in Add Plant · ML-16 save diagnosis to the story + 7-day re-check reminder · ML-17 states wired · ML-18 ML test pass (incl. Flask stopped) · ML-19 reviews MOD-11/12 → **Gate 3** |

**Learn:** Flask routing and JSON, PHP cURL, Keras transfer learning, precision/recall and confusion matrices.
**Tests:** TC-044 – TC-051. ML reviewers (Members 6 and 7) can prepare datasets, photo sets and guidance text from week 8.

---

## Stage D — Testing & delivery (weeks 15–16)

| Week | Dates | Tasks |
|---|---|---|
| 15 | 11–17 Jan | TST-02 security review · TST-03 input validation · TST-04 journeys A–D · TST-05 responsive on real phones · TST-06 browsers · TST-07 accessibility · TST-08 usability test with 5–10 students · TST-09 performance · DEP-05 README + setup guide · DEP-07 code clean-up · DEP-08 notebooks + model card · DEP-11 – DEP-14 report sections |
| 16 | 18–24 Jan | TST-10 bug fixes · TST-11 final regression · DEP-02 production config · DEP-03/04 deploy *(if hosting online)* · DEP-06 fresh install from README · DEP-15 assemble report · DEP-16 slides · DEP-17 backup demo video · DEP-18 rehearse twice · DEP-19 tag `v1.0` and submit · DEP-20 present |

**Decide hosting early:** DEP-01 depends on the supervisor's answer to PLN-03 — settle it by week 10, not week 15.

## After submission (weeks 17–20)
MNT-01 retrospective → `docs/retrospective.md` · MNT-02 act on feedback · MNT-03 backups · MNT-04 dependency updates ·
MNT-05 monitor predictions · MNT-06 houseplant disease data · MNT-07 email reminders · MNT-08 portfolio polish · MNT-09 future-work backlog.

---

## Mock data

`includes/mock-data.php` holds PHP arrays that look exactly like the rows future queries will return (column names
from [`database/README.md`](../database/README.md), plus joined columns such as `scientific_name` or `collection_name`).
Use the wireframe's sample content so every page can be checked against the PDF and the Figma prototype; CORE-02's
`seed.sql` is later built from the same data.

| Kind | Sample content (from the wireframes) |
|---|---|
| Signed-in user | Nimali Perera · `@nimali.grows` · Kurunegala · member since Mar 2025 · 18 plants in 3 collections · 12 swaps · 4.9 (23) |
| Collections | Living room (7) · Balcony (6) · Succulents (5) — one marked private for testing |
| Plants | Monty *Monstera deliciosa* (Thriving) · Goldie *Epipremnum aureum* (Needs attention, water 2 days overdue) · Sergeant *Dracaena trifasciata* (Thriving) · Figgy *Ficus lyrata* (Stable) · Lily *Spathiphyllum wallisii* (Stable) · Vera *Aloe vera* (Thriving) · Tommy *Solanum lycopersicum* (Sick — early blight, 92 %) · Pearl *Echeveria elegans* (Thriving) |
| Other members | `@aroid.amaya` (Colombo, 4.8) · `@kandy.jungle` (Kandy, 5.0) · `@dinesh.greens` (Negombo, 4.7) · `@ruwan.roots` (4.6) |
| Listings | Pothos 'Golden', 3 rooted cuttings (Swap) · Aloe vera pups (4) (Free) · Snake plant 'Laurentii' (Swap) · Tomato seedlings (6) (Free) · Peace lily division (Swap) · Echeveria cuttings (5) (Swap) |

Health status values are always `thriving`, `stable`, `needs_attention` or `sick`. Use your own photos or free-licence
images in `public/assets/img/mock/` and record their sources in `docs/credits.md` for the report.

---

## Habits for every week

- **One task at a time, end to end** — finish the page (or the wiring) before starting the next.
- **Commit small and often** on a feature branch; push daily; open a PR per module and get it reviewed within 3 days.
- **Check 375 px before calling anything finished.**
- **Weekly review (20 min, every Sunday):** update the Task Sheet, log the week, re-check this roadmap. If behind,
  cut from Should/Could tasks — never from Must tasks or security.
- **Ask for code in small pieces** — e.g. "CORE-14, step 2: the collection tabs" — so every line can be explained in the viva.

## Learning resources (just in time)
- HTML, CSS layout, forms, JavaScript, `fetch()` — MDN Web Docs: <https://developer.mozilla.org/>
- PHP includes, arrays, PDO, sessions, password hashing — PHP manual: <https://www.php.net/manual/en/>
- Flask — <https://flask.palletsprojects.com/>
- Keras transfer learning — <https://keras.io/guides/transfer_learning/>
