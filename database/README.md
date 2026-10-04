# database/

| File | Purpose | Task | When |
|---|---|---|---|
| `erd.png` | Entity-relationship diagram (keys, cardinalities, ON DELETE rules) — also exported to `docs/` | DES-06 | Week 1–2 |
| `data-dictionary.md` | Every column: type, null, default, constraint, index | DES-08 | Week 1–2 |
| `species.csv` | Care data for ≥ 30 species (light, water, humidity, pet safety, default reminder intervals) — must include every class the recognition model predicts | DES-13 | Week 1–2 |
| `schema.sql` | Creates every table, key and index (InnoDB, utf8mb4); re-runs cleanly on an empty database | CORE-01 | Week 8 |
| `seed.sql` | Demo data built from `includes/mock-data.php` (~10 users, 40 plants, due and overdue reminders, listings, interests, ratings) plus a reset script | CORE-02 | Week 8 |

Load in phpMyAdmin (Import) or:
```bash
mysql -u root plantfolio < database/schema.sql
mysql -u root plantfolio < database/seed.sql
```

> **Design these early.** The database tasks DES-06/07/08/13 finish in weeks 1–2 even though the tables are only created
> in week 8, because the frontend's mock data uses the same column names.

## Table plan

The proposal defines **10 tables**. Task **DES-07** adds the fields the wireframes need that the proposal lacks
(marked ➕ below). Confirm or change these during DES-07 and update this file.

| Table | Purpose | Key fields (proposal) | ➕ Added by DES-07 |
|---|---|---|---|
| `users` | Accounts and public profiles | id, username, email, password_hash, bio, location, avatar, created_at | full_name, phone, failed_logins, locked_until |
| `collections` | Named groups of plants per user | id, user_id, name, is_public | — |
| `plant_species` | Reference data and care information | id, common_name, scientific_name, light, water, humidity, notes | pet safety and default water/fertilise intervals (from DES-13) |
| `plants` | Each individual plant profile | id, user_id, collection_id, species_id, nickname, photo, acquired_on, health_status | is_public |
| `health_logs` | Dated health timeline (the plant story) | id, plant_id, status, note, photo, source (manual / ML), logged_at | — |
| `care_reminders` | Recurring care tasks | id, plant_id, task (water, fertilise), interval_days, last_done, next_due | — |
| `exchange_listings` | Plants offered for exchange | id, user_id, plant_id, title, description, location, status (open, closed) | type (swap / free / either), looking_for, share_email, share_phone, approx_area, photo, exchanged_with |
| `listing_interests` | Interest expressed in a listing | id, listing_id, user_id, message, created_at | status (new / seen / withdrawn) |
| `user_ratings` | Trust ratings between traders | id, rater_id, rated_id, listing_id, score (1–5), comment | UNIQUE (rater_id, listing_id) |
| `ml_predictions` | Every recognition or diagnosis | id, user_id, plant_id, type, predicted_label, confidence, image_path, created_at | model_version (from the API contract, DES-10) |
| `plant_photos` *(optional)* | Extra photos for the P05 gallery | id, plant_id, path, created_at | ➕ new |
| `plant_likes` *(optional, COM-15)* | Likes on public plants | id, plant_id, user_id, created_at · UNIQUE (plant_id, user_id) | ➕ new |

## Rules
- `health_status` and `health_logs.status` use exactly: `thriving`, `stable`, `needs_attention`, `sick`.
- Reminders are calculated from `next_due` (`next_due = last_done + interval_days`) — no background scheduler is needed for the Dashboard.
- UNIQUE on `users.username` and `users.email`; indexes on every foreign key, `care_reminders.next_due` and `plants.health_status`; `user_ratings.score` limited to 1–5 (DES-08).
- Photos are stored as files in `public/uploads/`; the database stores only the path.
- `schema.sql` and `seed.sql` **are** committed; database dumps (`*.sql.gz`, `database/dumps/`) are not.
