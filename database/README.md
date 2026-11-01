# database/

| File | Purpose | Task | When |
|---|---|---|---|
| `erd.png` | Entity-relationship diagram (keys, cardinalities, ON DELETE rules) — also exported to `docs/` | DES-06 | Week 1–2 |
| `data-dictionary.md` | Every column: type, null, default, constraint, index | DES-08 | Week 1–2 |
| `species.csv` | Care data for ≥ 30 species (light, water, humidity, pet safety, default reminder intervals) — must include every class the recognition model predicts | DES-13 | Week 1–2 |
| `schema.sql` | Creates every table, key and index (InnoDB, utf8mb4); re-runs cleanly on an empty database | CORE-01 | Week 8 |
| `seed.sql` | Demo data built from `includes/mock-data.php` (~10 users, 40 plants, due and overdue reminders, listings, interests, ratings) plus a reset script | CORE-02 | Week 8 |

The full design (ERD, flowcharts, draft SQL, key queries) is in
`docs/PlantFolio — Database Design Document.pdf`. Where the PDF and this file disagree, **this file wins**: the
seven changes under [Decisions](#decisions-des-07-31-oct-2026) came after the PDF and still have to go into it.

Load in phpMyAdmin (Import) or:
```bash
mysql -u root plantfolio < database/schema.sql
mysql -u root plantfolio < database/seed.sql
```

> **Design these early.** The database tasks DES-06/07/08/13 finish in weeks 1–2 even though the tables are only created
> in week 8, because the frontend's mock data uses the same column names.

## Table plan (DES-07, accepted 31 Oct 2026)

**16 tables, 25 foreign keys.** The proposal's 10 tables, plus 4 that the built pages rely on (`plant_photos`,
`plant_likes`, `listing_photos`, `disease_guides`) and 2 added by the decisions below (`towns`, `remember_tokens`).
Column types, nulls, defaults and keys are in [`data-dictionary.md`](data-dictionary.md).

| # | Table | Purpose | Columns |
|---|---|---|---|
| 1 | `users` | Accounts, public profiles, Settings choices | id, username, full_name, email, phone, password_hash, bio, town_id, avatar, new_plants_public, dashboard_reminders, email_reminders, failed_logins, locked_until, created_at |
| 2 | `towns` | Towns with coordinates, for "3 km" on listing cards and the P10 distance filter | id, name, latitude, longitude |
| 3 | `remember_tokens` | Secure "Remember me" logins (P02) | id, user_id, selector, token_hash, expires_at |
| 4 | `collections` | Named groups of plants per member | id, user_id, name, is_public, created_at |
| 5 | `plant_species` | Reference data: the care guide for each species, loaded from `species.csv` | id, common_name, scientific_name, photo, light, water, humidity, pet_safe, water_interval_days, fertilise_interval_days, notes |
| 6 | `plants` | Each plant profile | id, user_id, collection_id, species_id, custom_species, nickname, photo, acquired_on, health_status, is_public, created_at |
| 7 | `plant_photos` | Extra photos for the P05 gallery | id, plant_id, path, created_at |
| 8 | `health_logs` | The dated plant story; each row is a manual update or a saved AI result | id, plant_id, prediction_id, status, source, note, photo, logged_at |
| 9 | `care_reminders` | Care tasks: `water` and `fertilise` repeat, `recheck` happens once | id, plant_id, task, interval_days, last_done, next_due |
| 10 | `plant_likes` | Likes on public plants (COM-15) | id, plant_id, user_id, created_at |
| 11 | `ml_predictions` | Every recognition or diagnosis, saved by the member or not | id, user_id, plant_id, type, predicted_label, confidence, alternatives, image_path, upload_name, upload_size, model_version, created_at |
| 12 | `disease_guides` | What P08 says about each disease class; matched to a result by `class_label`, no foreign key | id, class_label, crop, cause, symptoms, steps, status |
| 13 | `exchange_listings` | Plants offered for swap or free | id, user_id, plant_id, species_id, title, description, type, looking_for, town_id, size, plant_condition, photo, share_email, share_phone, status, exchanged_with, exchanged_at, created_at |
| 14 | `listing_photos` | Extra photos for the P11 gallery, after the listing's own `photo` | id, listing_id, path, created_at |
| 15 | `listing_interests` | "I'm interested" requests, one row per member and listing | id, listing_id, user_id, message, status, created_at |
| 16 | `user_ratings` | Trust ratings after a finished exchange | id, rater_id, rated_id, listing_id, score, comment, created_at |

**Pages keep their mock keys.** Queries return `towns.name AS location`, so `location` in `includes/mock-data.php`
stays as it is. Counts and averages (`plant_count`, `rating_avg`, `interest_count`, `like_count`, `distance_km`) are
never stored: queries calculate them.

## Decisions (DES-07, 31 Oct 2026)

Accepted from the stage F proposals: `exchange_listings.exchanged_at`, `plant_species.photo`,
`ml_predictions.alternatives`, the `recheck` reminder task, and `disease_guides` as a table (not a JSON file: Flowchart 4
joins it, and it is seeded from a CSV like `plant_species`). Seven changes to the design document:

| # | Change | Why |
|---|---|---|
| 1 | `exchange_listings.species_id` is **always** filled: copied from the plant when the listing offers one, picked directly for cuttings and seedlings. The rule "plant_id or species_id, not both" is dropped | `plant_id` is SET NULL when the owner deletes the plant. Without its own species the listing would drop out of the P10 Species filter. A listing is a public advert and should still say what it offers |
| 2 | New `plants.custom_species VARCHAR(150) NULL` | P06's species field accepts any name, but `plant_species` holds about 30. A typed name that matches no row is kept here instead of being lost. Pages show `COALESCE(plant_species.common_name, plants.custom_species)` |
| 3 | Mark done **deletes** a `recheck` reminder and moves `water`/`fertilise` forward. A second "Remind me to re-check" uses `INSERT … ON DUPLICATE KEY UPDATE next_due` | Makes the key query match Flowchart 3. UNIQUE (plant_id, task) means one pending re-check per plant, so a second Health Check moves its date instead of failing |
| 4 | New `towns` table. `users.town_id` (NULL) and `exchange_listings.town_id` (NOT NULL) replace the two `location` text columns. ON DELETE RESTRICT | P10 already filters by distance and every card shows one. Coordinates matched by typed text would break on every typo. Distance is worked out in the query (Haversine formula). In stage B the town fields on P02, P12 and P15 become a list |
| 5 | New `remember_tokens` table: the cookie holds `selector:validator`, the table stores only a hash of the validator, 30 days, deleted on logout, replaced on each use | A longer session cookie alone does not work: PHP deletes idle sessions on the server after 24 minutes (`session.gc_maxlifetime`) |
| 6 | `user_ratings.rater_id` and `user_ratings.listing_id` become NULL with ON DELETE SET NULL. `rated_id` stays CASCADE | A review stays when its writer leaves (shown as "Former member") or a listing is deleted, so other members' averages don't change. Ratings *about* a deleted member have no one to belong to |
| 7 | `exchange_listings.approx_area` and `users.show_listing_area` are removed | The site never stores an address, so a listing always shows the town only and the checkbox changed nothing. The P12 checkbox, the P15 toggle and their mock keys go in a later stage F step |

Still to do in the PDF: these seven changes, the nearly blank pages (21, 23, 24, 26, 27), and the 2NF wording
("every non-key column depends on the whole of every candidate key").

## Rules
- `plants.health_status`, `health_logs.status` and `disease_guides.status` use exactly: `thriving`, `stable`, `needs_attention`, `sick`.
- Reminders are calculated from `next_due` (`next_due = last_done + interval_days`): no background scheduler is needed for the Dashboard.
- A plant has `species_id`, or `custom_species`, or neither (unknown species), never both.
- UNIQUE on `users.username` and `users.email`; indexes on every foreign key, `care_reminders.next_due` and `plants.health_status`; `user_ratings.score` limited to 1–5 (DES-08).
- Photos are stored as files in `public/uploads/`; the database stores only the path.
- `schema.sql` and `seed.sql` **are** committed; database dumps (`*.sql.gz`, `database/dumps/`) are not.
