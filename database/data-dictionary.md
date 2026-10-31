# Data dictionary (DES-08)

Every column of the 16 tables in the [table plan](README.md#table-plan-des-07-31-oct-2026). It follows
`docs/PlantFolio — Database Design Document.pdf` with the seven [DES-07 decisions](README.md#decisions-des-07-31-oct-2026)
applied; columns those decisions added or changed are marked **(DES-07)**. `schema.sql` (CORE-01, week 8) must match
this file.

**Conventions.** MySQL/MariaDB, InnoDB, `utf8mb4` with `utf8mb4_unicode_ci`. Every table has an
`id INT UNSIGNED NOT NULL AUTO_INCREMENT` primary key; foreign keys are named `<table>_id` and have an index.
Yes/no flags are `TINYINT(1)` holding 0 or 1. Photo columns hold a path relative to `public/uploads/`, never the file.
PK = primary key, FK = foreign key, UQ = unique.

## 1. users
One row per member: login details, public profile and Settings choices.

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | INT UNSIGNED | No | AUTO_INCREMENT | PK | |
| username | VARCHAR(30) | No | | UQ | Shown as @nimali.grows; `[a-z0-9._]`, 3–30 characters |
| full_name | VARCHAR(100) | No | | | |
| email | VARCHAR(255) | No | | UQ | Never shown publicly |
| phone | VARCHAR(20) | Yes | NULL | | Shared only through a listing's `share_phone` |
| password_hash | VARCHAR(255) | No | | | From `password_hash()` |
| bio | VARCHAR(500) | Yes | NULL | | |
| town_id | INT UNSIGNED | Yes | NULL | FK → towns.id | **(DES-07)** Replaces `location`. NULL = no distances shown |
| avatar | VARCHAR(255) | Yes | NULL | | NULL shows initials |
| new_plants_public | TINYINT(1) | No | 1 | | Settings: new plants start public |
| dashboard_reminders | TINYINT(1) | No | 1 | | Settings: show care tasks on the Dashboard |
| email_reminders | TINYINT(1) | No | 0 | | Settings toggle, disabled in the UI for now |
| failed_logins | TINYINT UNSIGNED | No | 0 | | Reset to 0 on a good login |
| locked_until | DATETIME | Yes | NULL | | Set for 15 minutes after the 5th failure |
| created_at | DATETIME | No | CURRENT_TIMESTAMP | | "Member since Mar 2025" |

## 2. towns **(DES-07)**
Reference data: one row per town, seeded with about 30 towns (Kurunegala district and the main cities).

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | INT UNSIGNED | No | AUTO_INCREMENT | PK | |
| name | VARCHAR(100) | No | | UQ | Kurunegala |
| latitude | DECIMAL(9,6) | No | | | 7.486500 |
| longitude | DECIMAL(9,6) | No | | | 80.362300 |

## 3. remember_tokens **(DES-07)**
"Remember me" logins. The cookie holds `selector:validator`; only a hash of the validator is stored, so a stolen
database cannot log anyone in.

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | INT UNSIGNED | No | AUTO_INCREMENT | PK | |
| user_id | INT UNSIGNED | No | | FK → users.id | |
| selector | CHAR(24) | No | | UQ | Random, used to find the row |
| token_hash | CHAR(64) | No | | | `hash('sha256', $validator)`, compared with `hash_equals()` |
| expires_at | DATETIME | No | | | 30 days after login; the row is replaced on each use and deleted on logout |

## 4. collections
A member's named groups of plants, such as Living room or Balcony.

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | INT UNSIGNED | No | AUTO_INCREMENT | PK | |
| user_id | INT UNSIGNED | No | | FK → users.id, UQ (user_id, name) | |
| name | VARCHAR(60) | No | | | No two collections with the same name per member |
| is_public | TINYINT(1) | No | 1 | | 0 shows a lock and hides it from others |
| created_at | DATETIME | No | CURRENT_TIMESTAMP | | |

## 5. plant_species
Reference data: the care guide for each species, loaded from `species.csv` (at least 30 rows, including every class
the recognition model predicts).

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | INT UNSIGNED | No | AUTO_INCREMENT | PK | |
| common_name | VARCHAR(100) | No | | Index | Swiss cheese plant |
| scientific_name | VARCHAR(150) | No | | UQ | Monstera deliciosa; matches the model's species labels |
| photo | VARCHAR(255) | Yes | NULL | | Reference picture beside the P07 top match, credited in `docs/credits.md` |
| light | VARCHAR(100) | Yes | NULL | | Bright, indirect |
| water | VARCHAR(150) | Yes | NULL | | When the top 5 cm of soil are dry |
| humidity | VARCHAR(60) | Yes | NULL | | 60%+ preferred |
| pet_safe | TINYINT(1) | Yes | NULL | | NULL = unknown |
| water_interval_days | SMALLINT UNSIGNED | No | 7 | | Pre-fills the Add Plant reminder |
| fertilise_interval_days | SMALLINT UNSIGNED | No | 30 | | Pre-fills the Add Plant reminder |
| notes | TEXT | Yes | NULL | | Extra care tips |

## 6. plants
Each plant profile.

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | INT UNSIGNED | No | AUTO_INCREMENT | PK | |
| user_id | INT UNSIGNED | No | | FK → users.id | |
| collection_id | INT UNSIGNED | Yes | NULL | FK → collections.id | Deleting a collection keeps its plants |
| species_id | INT UNSIGNED | Yes | NULL | FK → plant_species.id | Set when the typed species matches a row |
| custom_species | VARCHAR(150) | Yes | NULL | | **(DES-07)** A typed species that matches no row. Never set together with `species_id`; both NULL = unknown |
| nickname | VARCHAR(60) | No | | | Monty |
| photo | VARCHAR(255) | Yes | NULL | | Main card photo |
| acquired_on | DATE | Yes | NULL | | |
| health_status | ENUM('thriving','stable','needs_attention','sick') | No | 'stable' | Index | Copied from the newest health log |
| is_public | TINYINT(1) | No | 1 | Index | 0 = only the owner can see it |
| created_at | DATETIME | No | CURRENT_TIMESTAMP | | |

## 7. plant_photos
Extra photos for the Plant Profile gallery.

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | INT UNSIGNED | No | AUTO_INCREMENT | PK | |
| plant_id | INT UNSIGNED | No | | FK → plants.id | |
| path | VARCHAR(255) | No | | | |
| created_at | DATETIME | No | CURRENT_TIMESTAMP | | Gallery shows newest first |

## 8. health_logs
The dated plant story. Each row is a manual update or a saved AI result.

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | INT UNSIGNED | No | AUTO_INCREMENT | PK | |
| plant_id | INT UNSIGNED | No | | FK → plants.id, Index (plant_id, logged_at) | |
| prediction_id | INT UNSIGNED | Yes | NULL | FK → ml_predictions.id | Set when `source` = 'ml' |
| status | ENUM('thriving','stable','needs_attention','sick') | No | | | |
| source | ENUM('manual','ml') | No | 'manual' | | |
| note | VARCHAR(500) | Yes | NULL | | Brown rings on the lower leaves. |
| photo | VARCHAR(255) | Yes | NULL | | |
| logged_at | DATETIME | No | CURRENT_TIMESTAMP | | Story shows newest first |

## 9. care_reminders
Care tasks. A switched-off reminder on the Add Plant form simply has no row.

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | INT UNSIGNED | No | AUTO_INCREMENT | PK | |
| plant_id | INT UNSIGNED | No | | FK → plants.id, UQ (plant_id, task) | One reminder per task per plant |
| task | ENUM('water','fertilise','recheck') | No | | | **(DES-07)** `recheck` is P08's one-off "re-check in 7 days": deleted when marked done; a second one moves `next_due` (`ON DUPLICATE KEY UPDATE`) |
| interval_days | SMALLINT UNSIGNED | No | | CHECK > 0 | 7 for a recheck |
| last_done | DATE | Yes | NULL | | NULL until first done |
| next_due | DATE | No | | Index | Always `last_done + interval_days` |

## 10. plant_likes
Likes on public plants (COM-15). One like per member per plant.

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | INT UNSIGNED | No | AUTO_INCREMENT | PK | |
| plant_id | INT UNSIGNED | No | | FK → plants.id, UQ (plant_id, user_id) | |
| user_id | INT UNSIGNED | No | | FK → users.id | |
| created_at | DATETIME | No | CURRENT_TIMESTAMP | | |

## 11. ml_predictions
Every species recognition or disease diagnosis, saved or not, so model accuracy can be measured for the report.

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | INT UNSIGNED | No | AUTO_INCREMENT | PK | Used in `identify.php?prediction=5` |
| user_id | INT UNSIGNED | No | | FK → users.id | |
| plant_id | INT UNSIGNED | Yes | NULL | FK → plants.id | NULL until attached to a plant |
| type | ENUM('species','disease') | No | | | |
| predicted_label | VARCHAR(150) | No | | Index | Top result: a scientific name, or a class such as `Tomato___Early_blight` |
| confidence | DECIMAL(5,4) | No | | CHECK 0–1 | High ≥ 0.80, Medium 0.60–0.79, Not sure < 0.60 |
| alternatives | JSON | Yes | NULL | | The two runners-up of Flask's top 3, e.g. `[{"label": "…", "confidence": 0.12}]` |
| image_path | VARCHAR(255) | No | | | |
| upload_name | VARCHAR(255) | Yes | NULL | | Original file name shown on the result page |
| upload_size | INT UNSIGNED | Yes | NULL | | Bytes |
| model_version | VARCHAR(50) | No | | | species-mobilenetv2-v1 |
| created_at | DATETIME | No | CURRENT_TIMESTAMP | | |

## 12. disease_guides
What the Health Check says about each disease class. Linked to a result by matching
`ml_predictions.predicted_label` to `class_label`, not by a foreign key.

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | INT UNSIGNED | No | AUTO_INCREMENT | PK | |
| class_label | VARCHAR(100) | No | | UQ | Same text as the model's class name |
| crop | VARCHAR(50) | No | | | Tomato |
| cause | VARCHAR(255) | Yes | NULL | | Caused by the fungus Alternaria solani |
| symptoms | TEXT | Yes | NULL | | Plain-language description |
| steps | JSON | Yes | NULL | | Array of the four care steps |
| status | ENUM('thriving','stable','needs_attention','sick') | No | | | The `health_status` a saved result gives the plant |

## 13. exchange_listings
Plants offered for swap or free.

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | INT UNSIGNED | No | AUTO_INCREMENT | PK | |
| user_id | INT UNSIGNED | No | | FK → users.id | Owner |
| plant_id | INT UNSIGNED | Yes | NULL | FK → plants.id | The owner's plant on offer; NULL for cuttings and seedlings listed without one |
| species_id | INT UNSIGNED | Yes | NULL | FK → plant_species.id | **(DES-07)** Always filled when known: copied from the plant, or picked for cuttings. NULL only when the species is not in `plant_species` |
| title | VARCHAR(100) | No | | | Pothos 'Golden', 3 rooted cuttings |
| description | TEXT | Yes | NULL | | |
| type | ENUM('swap','free','either') | No | 'swap' | | |
| looking_for | VARCHAR(150) | Yes | NULL | | succulents or a snake plant pup |
| town_id | INT UNSIGNED | No | | FK → towns.id | **(DES-07)** Replaces `location`. Pick-up town; can differ from the owner's town. Only the town is ever shown |
| size | VARCHAR(60) | Yes | NULL | | 3 cuttings, 15 to 20 cm |
| plant_condition | VARCHAR(100) | Yes | NULL | | Not `condition`, a reserved word in MySQL |
| photo | VARCHAR(255) | No | | | Card photo |
| share_email | TINYINT(1) | No | 1 | | Shown only after "I'm interested" |
| share_phone | TINYINT(1) | No | 0 | | Shown only after "I'm interested" |
| status | ENUM('open','closed') | No | 'open' | Index (status, created_at) | |
| exchanged_with | INT UNSIGNED | Yes | NULL | FK → users.id | Set by Mark as exchanged |
| exchanged_at | DATETIME | Yes | NULL | | NULL while open; "Exchanged 13 Sep" |
| created_at | DATETIME | No | CURRENT_TIMESTAMP | | "2 days ago", Newest sort |

## 14. listing_photos
Extra photos for the Listing Detail gallery, shown after the listing's own photo.

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | INT UNSIGNED | No | AUTO_INCREMENT | PK | |
| listing_id | INT UNSIGNED | No | | FK → exchange_listings.id | |
| path | VARCHAR(255) | No | | | |
| created_at | DATETIME | No | CURRENT_TIMESTAMP | | Oldest first |

## 15. listing_interests
"I'm interested" requests. One row per member per listing; withdrawing changes the status instead of deleting, and
showing interest again sets it back to `new`.

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | INT UNSIGNED | No | AUTO_INCREMENT | PK | |
| listing_id | INT UNSIGNED | No | | FK → exchange_listings.id, UQ (listing_id, user_id) | |
| user_id | INT UNSIGNED | No | | FK → users.id | The requester |
| message | VARCHAR(500) | Yes | NULL | | |
| status | ENUM('new','seen','withdrawn') | No | 'new' | | Withdrawn rows are not counted |
| created_at | DATETIME | No | CURRENT_TIMESTAMP | | |

## 16. user_ratings
Trust ratings after a finished exchange. Both members may rate each other once.

| Column | Type | Null | Default | Key | Notes |
|---|---|---|---|---|---|
| id | INT UNSIGNED | No | AUTO_INCREMENT | PK | |
| rater_id | INT UNSIGNED | Yes | NULL | FK → users.id, UQ (rater_id, listing_id) | **(DES-07)** NULL after the writer deletes their account: shown as "Former member" |
| rated_id | INT UNSIGNED | No | | FK → users.id | PHP checks it differs from `rater_id` |
| listing_id | INT UNSIGNED | Yes | NULL | FK → exchange_listings.id | **(DES-07)** NULL after the listing is deleted; the rating stays |
| score | TINYINT UNSIGNED | No | | CHECK 1–5 | |
| comment | VARCHAR(500) | Yes | NULL | | |
| created_at | DATETIME | No | CURRENT_TIMESTAMP | | Newest review first |

## Foreign keys (25)

CASCADE removes the child rows with the parent; SET NULL keeps them and clears the link; RESTRICT refuses to delete
a parent that is still in use.

| Foreign key | References | ON DELETE |
|---|---|---|
| users.town_id | towns.id | RESTRICT |
| remember_tokens.user_id | users.id | CASCADE |
| collections.user_id | users.id | CASCADE |
| plants.user_id | users.id | CASCADE |
| plants.collection_id | collections.id | SET NULL |
| plants.species_id | plant_species.id | SET NULL |
| plant_photos.plant_id | plants.id | CASCADE |
| health_logs.plant_id | plants.id | CASCADE |
| health_logs.prediction_id | ml_predictions.id | SET NULL |
| care_reminders.plant_id | plants.id | CASCADE |
| plant_likes.plant_id | plants.id | CASCADE |
| plant_likes.user_id | users.id | CASCADE |
| ml_predictions.user_id | users.id | CASCADE |
| ml_predictions.plant_id | plants.id | SET NULL |
| exchange_listings.user_id | users.id | CASCADE |
| exchange_listings.plant_id | plants.id | SET NULL |
| exchange_listings.species_id | plant_species.id | SET NULL |
| exchange_listings.town_id | towns.id | RESTRICT |
| exchange_listings.exchanged_with | users.id | SET NULL |
| listing_photos.listing_id | exchange_listings.id | CASCADE |
| listing_interests.listing_id | exchange_listings.id | CASCADE |
| listing_interests.user_id | users.id | CASCADE |
| user_ratings.rater_id | users.id | SET NULL |
| user_ratings.rated_id | users.id | CASCADE |
| user_ratings.listing_id | exchange_listings.id | SET NULL |

## Unique keys and checks

| Name | Table | Rule |
|---|---|---|
| uq_users_username · uq_users_email | users | No two members share a username or an email |
| uq_towns_name | towns | One row per town |
| uq_remember_selector | remember_tokens | Selectors are unique |
| uq_collections_user_name | collections | A member's collection names are distinct |
| uq_species_scientific | plant_species | One row per scientific name |
| uq_plant_likes | plant_likes | One like per member per plant |
| uq_reminders_plant_task | care_reminders | One reminder per task per plant |
| uq_disease_guides_label | disease_guides | One guide per model class |
| uq_interests_listing_user | listing_interests | One interest row per member per listing |
| uq_ratings_rater_listing | user_ratings | One rating per member per exchange (several NULLs are allowed) |
| chk_reminders_interval | care_reminders | `interval_days > 0` |
| chk_predictions_confidence | ml_predictions | `confidence BETWEEN 0 AND 1` |
| chk_ratings_score | user_ratings | `score BETWEEN 1 AND 5` |

## Rules checked in PHP
These span rows or tables, so a column constraint cannot check them.

- Every UPDATE and DELETE checks ownership in its WHERE clause (`user_id` = the session user).
- `plants.species_id` and `plants.custom_species` are never both set.
- `plants.health_status` equals the newest health log's status (updated in the same transaction as the log INSERT).
- `exchanged_with` and `exchanged_at` are set only when `status` = 'closed'.
- A rating needs a closed listing; the rater must be its owner or `exchanged_with`, and `rated_id` differs from `rater_id`.
- Private plants and collections (`is_public` = 0) are never returned to other members.
- Contact details appear only after "I'm interested", and only the fields with `share_email` or `share_phone` = 1.
- An account locks for 15 minutes after 5 failed logins.
