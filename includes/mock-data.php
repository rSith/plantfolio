<?php
// Stage F only: arrays shaped like future database rows (MOD-01 foundation, week 1).
// Each array is replaced by a PDO query in stage B, then removed from this file.
// Sample content comes from the wireframes so pages can be compared with the PDF and Figma.

/**
 * Mock-only helper: the date N days from today (-2 is two days ago, 0 is today).
 * Reminder and listing dates are counted from today, so "Water today" and "Overdue 2 days"
 * stay true on whatever day the page is opened — including the day of the demo.
 */
function mock_date($days)
{
    return date('Y-m-d', strtotime($days . ' days'));
}

// The signed-in member — one row of the `users` table (wireframes p21, p22).
// password_hash, failed_logins and locked_until are left out: pages never display them.
$current_user = [
    'id'         => 1,
    'username'   => 'nimali.grows',
    'full_name'  => 'Nimali Perera',
    'email'      => 'nimali.p@example.com',
    'phone'      => null,                    // not shown in the wireframes yet
    'bio'        => 'Balcony gardener growing aroids and far too many pothos. Always happy to swap cuttings.',
    'location'   => 'Kurunegala',
    'avatar'     => null,                    // no photo: pages show the initials "NP" instead
    'created_at' => '2025-03-14 09:00:00',   // "Member since Mar 2025"

    // Preferences from Settings (wireframe p22, cards 4 and 5). Not in the table plan yet: proposed users
    // columns for DES-07, each a TINYINT 0/1. Names are my proposal.
    'new_plants_public'   => 1,   // Privacy: new plants start Public on the Add Plant form
    'show_listing_area'   => 1,   // Privacy: listings show the approximate area
    'dashboard_reminders' => 1,   // Reminders: care tasks appear on the Dashboard

    // Not columns of `users`: stage B calculates these with COUNT() and AVG() on other tables.
    'plant_count'      => 18,
    'collection_count' => 3,
    'swap_count'       => 12,
    'rating_avg'       => 4.9,
    'rating_count'     => 23,
    'new_interest_count' => 4,   // people newly interested in her listings (p10): the dot on the header bell
];

// Who is logged in. The preview works like a real login, kept in the session from page to page:
// a new browser starts as a visitor; Log in (or Create account) makes it Nimali; Log out makes it a visitor again.
// Nothing else changes it: opening the Landing page never logs anyone out.
// Shortcuts for testing: ?member jumps straight to Nimali, ?guest to a visitor.
// A visitor has no users row, so $current_user is null — exactly what the session will give in stage B.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['preview_visitor'])) {
    $_SESSION['preview_visitor'] = true;   // a new browser has not logged in yet
}
if (isset($_GET['guest'])) {
    $_SESSION['preview_visitor'] = true;
}
if (isset($_GET['member'])) {
    $_SESSION['preview_visitor'] = false;
    $_SESSION['preview_empty']   = false;   // ?member is always Nimali with her full garden
}
if (!empty($_SESSION['preview_visitor'])) {
    $current_user = null;
}

/** Mock-only: log out, i.e. switch the preview to visitor mode (logout.php). Returns null for $current_user. */
function mock_become_visitor()
{
    $_SESSION['preview_visitor'] = true;
    $_SESSION['preview_empty']   = false;
    return null;
}

/**
 * Mock-only: "log in" without checking anything, then open $page. Used by the Log in and Sign up forms, so a demo can
 * walk from the Landing page into the member pages. Stage B replaces it with the real checks and session_regenerate_id().
 * $new_account = true (Sign up) starts the empty-garden preview, because a brand-new account has no plants yet.
 */
function mock_log_in_and_go($page, $new_account = false)
{
    $_SESSION['preview_visitor'] = false;
    $_SESSION['preview_empty']   = $new_account;
    header('Location: ' . url($page));
    exit;
}

// Nimali's plants — rows of `plants` (wireframe p11), each joined with:
//   plant_species  → scientific_name, common_name
//   collections    → collection_name
//   care_reminders → task, interval_days, next_due of the plant's soonest reminder
//   health_logs    → last_logged_at, the date of the plant's newest log (My Garden's "Recently updated" sort)
// The order is the order of wireframe p11.
$mock_plants = [
    ['id' => 42, 'user_id' => 1, 'collection_id' => 1, 'species_id' => 1, 'nickname' => 'Monty',
     'photo' => 'mock/monty.svg', 'acquired_on' => '2025-03-20', 'health_status' => 'thriving', 'is_public' => 1,
     'scientific_name' => 'Monstera deliciosa', 'common_name' => 'Swiss cheese plant',
     'collection_name' => 'Living room',
     'task' => 'water', 'interval_days' => 7, 'next_due' => mock_date(0),
     'last_logged_at' => mock_date(-5) . ' 18:20:00'],

    ['id' => 43, 'user_id' => 1, 'collection_id' => 1, 'species_id' => 2, 'nickname' => 'Goldie',
     'photo' => 'mock/goldie.svg', 'acquired_on' => '2025-04-02', 'health_status' => 'needs_attention', 'is_public' => 1,
     'scientific_name' => 'Epipremnum aureum', 'common_name' => 'Golden pothos',
     'collection_name' => 'Living room',
     'task' => 'water', 'interval_days' => 5, 'next_due' => mock_date(-2),
     'last_logged_at' => mock_date(-3) . ' 18:10:00'],

    ['id' => 44, 'user_id' => 1, 'collection_id' => 1, 'species_id' => 3, 'nickname' => 'Sergeant',
     'photo' => 'mock/sergeant.svg', 'acquired_on' => '2025-05-11', 'health_status' => 'thriving', 'is_public' => 1,
     'scientific_name' => 'Dracaena trifasciata', 'common_name' => 'Snake plant',
     'collection_name' => 'Living room',
     'task' => 'fertilise', 'interval_days' => 30, 'next_due' => mock_date(0),
     'last_logged_at' => mock_date(-12) . ' 09:00:00'],

    ['id' => 45, 'user_id' => 1, 'collection_id' => 1, 'species_id' => 4, 'nickname' => 'Figgy',
     'photo' => 'mock/figgy.svg', 'acquired_on' => '2025-06-18', 'health_status' => 'stable', 'is_public' => 1,
     'scientific_name' => 'Ficus lyrata', 'common_name' => 'Fiddle-leaf fig',
     'collection_name' => 'Living room',
     'task' => 'water', 'interval_days' => 7, 'next_due' => mock_date(3),
     'last_logged_at' => mock_date(-9) . ' 17:30:00'],

    ['id' => 48, 'user_id' => 1, 'collection_id' => 1, 'species_id' => 7, 'nickname' => 'Lily',
     'photo' => 'mock/lily.svg', 'acquired_on' => '2025-09-02', 'health_status' => 'stable', 'is_public' => 1,
     'scientific_name' => 'Spathiphyllum wallisii', 'common_name' => 'Peace lily',
     'collection_name' => 'Living room',
     'task' => 'water', 'interval_days' => 4, 'next_due' => mock_date(1),
     'last_logged_at' => mock_date(-15) . ' 08:40:00'],

    ['id' => 46, 'user_id' => 1, 'collection_id' => 3, 'species_id' => 5, 'nickname' => 'Vera',
     'photo' => 'mock/vera.svg', 'acquired_on' => '2025-07-06', 'health_status' => 'thriving', 'is_public' => 0,
     'scientific_name' => 'Aloe vera', 'common_name' => 'Aloe vera',
     'collection_name' => 'Succulents',
     'task' => 'water', 'interval_days' => 14, 'next_due' => mock_date(9),
     'last_logged_at' => mock_date(-20) . ' 16:00:00'],

    ['id' => 47, 'user_id' => 1, 'collection_id' => 2, 'species_id' => 6, 'nickname' => 'Tommy',
     'photo' => 'mock/tommy.svg', 'acquired_on' => '2025-08-23', 'health_status' => 'sick', 'is_public' => 1,
     'scientific_name' => 'Solanum lycopersicum', 'common_name' => 'Tomato',
     'collection_name' => 'Balcony',
     'task' => 'water', 'interval_days' => 2, 'next_due' => mock_date(1),
     'last_logged_at' => mock_date(-1) . ' 07:46:00'],   // the Health Check on the Dashboard

    // Pearl is in the private Succulents collection, so she is private too, like Vera
    ['id' => 49, 'user_id' => 1, 'collection_id' => 3, 'species_id' => 8, 'nickname' => 'Pearl',
     'photo' => 'mock/pearl.svg', 'acquired_on' => '2025-09-27', 'health_status' => 'thriving', 'is_public' => 0,
     'scientific_name' => 'Echeveria elegans', 'common_name' => 'Mexican snowball',
     'collection_name' => 'Succulents',
     'task' => 'water', 'interval_days' => 10, 'next_due' => mock_date(6),
     'last_logged_at' => mock_date(-26) . ' 10:15:00'],
];

// Nimali's collections — rows of `collections` (wireframe p11, element 2), plus plant_count,
// a COUNT() on plants. A collection with is_public = 0 is private: the page shows a lock and hides it from others.
// The counts are her whole garden (18 plants, as on p11 and the Dashboard); $mock_plants holds only a sample of 8.
$mock_collections = [
    ['id' => 1, 'user_id' => 1, 'name' => 'Living room', 'is_public' => 1, 'plant_count' => 7],
    ['id' => 2, 'user_id' => 1, 'name' => 'Balcony',     'is_public' => 1, 'plant_count' => 6],
    ['id' => 3, 'user_id' => 1, 'name' => 'Succulents',  'is_public' => 0, 'plant_count' => 5],
];

// Care that is due today or overdue — rows of `care_reminders` (wireframe p10), each joined with:
//   plants → nickname, photo        plant_species → common_name
// Every row keeps the rule from database/README.md: next_due = last_done + interval_days.
$mock_reminders = [
    ['id' => 12, 'plant_id' => 43, 'task' => 'water', 'interval_days' => 5,
     'last_done' => mock_date(-7), 'next_due' => mock_date(-2),
     'nickname' => 'Goldie', 'photo' => 'mock/goldie.svg', 'common_name' => 'Golden pothos'],

    ['id' => 11, 'plant_id' => 42, 'task' => 'water', 'interval_days' => 7,
     'last_done' => mock_date(-7), 'next_due' => mock_date(0),
     'nickname' => 'Monty', 'photo' => 'mock/monty.svg', 'common_name' => 'Swiss cheese plant'],

    ['id' => 13, 'plant_id' => 44, 'task' => 'fertilise', 'interval_days' => 30,
     'last_done' => mock_date(-30), 'next_due' => mock_date(0),
     'nickname' => 'Sergeant', 'photo' => 'mock/sergeant.svg', 'common_name' => 'Snake plant'],
];

// Open listings on the marketplace, newest first — rows of `exchange_listings` (wireframe p17), each joined with:
//   users → username, full_name, avatar of the owner
// and four calculated columns: rating_avg and rating_count (from user_ratings), interest_count
// (from listing_interests) and distance_km (between the viewer's town and the listing's town).
// Columns only the detail page needs (description, share_email, share_phone …) are added with that page.
$mock_listings = [
    ['id' => 10, 'user_id' => 3, 'plant_id' => null, 'title' => 'Tomato seedlings (6)',
     'type' => 'free', 'looking_for' => 'nothing, free to a good home', 'location' => 'Kandy',
     'status' => 'open', 'photo' => 'mock/tommy.svg', 'created_at' => mock_date(-1) . ' 18:30:00',
     'username' => 'kandy.jungle', 'full_name' => 'Kavindi Jayawardena', 'avatar' => null,
     'rating_avg' => 5.0, 'rating_count' => 11, 'interest_count' => 3, 'distance_km' => 42],

    ['id' => 7, 'user_id' => 1, 'plant_id' => 43, 'title' => "Pothos 'Golden', 3 rooted cuttings",
     'type' => 'swap', 'looking_for' => 'succulents or a snake plant pup', 'location' => 'Kurunegala',
     'status' => 'open', 'photo' => 'mock/goldie.svg', 'created_at' => mock_date(-2) . ' 09:15:00',
     'username' => 'nimali.grows', 'full_name' => 'Nimali Perera', 'avatar' => null,
     'rating_avg' => 4.9, 'rating_count' => 23, 'interest_count' => 4, 'distance_km' => 3],

    ['id' => 8, 'user_id' => 5, 'plant_id' => 61, 'title' => 'Aloe vera pups (4)',
     'type' => 'free', 'looking_for' => 'nothing, free to a good home', 'location' => 'Kurunegala',
     'status' => 'open', 'photo' => 'mock/vera.svg', 'created_at' => mock_date(-3) . ' 17:40:00',
     'username' => 'ruwan.roots', 'full_name' => 'Ruwan Rathnayake', 'avatar' => null,
     'rating_avg' => 4.6, 'rating_count' => 9, 'interest_count' => 2, 'distance_km' => 5],

    ['id' => 9, 'user_id' => 4, 'plant_id' => 59, 'title' => "Snake plant 'Laurentii'",
     'type' => 'swap', 'looking_for' => 'any fern', 'location' => 'Kuliyapitiya',
     'status' => 'open', 'photo' => 'mock/sergeant.svg', 'created_at' => mock_date(-5) . ' 08:05:00',
     'username' => 'dinesh.greens', 'full_name' => 'Dinesh Gunasekara', 'avatar' => null,
     'rating_avg' => 4.7, 'rating_count' => 15, 'interest_count' => 1, 'distance_km' => 18],
];

// ── Landing page (P01, CORE-27) ─────────────────────────

// Site-wide counts for the Landing page. The wireframe says they are read live, never typed into the page.
// Stage B: SELECT COUNT(*) FROM users · SELECT COUNT(*) FROM exchange_listings WHERE status = 'open'
$mock_site_stats = [
    'member_count'       => 1248,   // shown rounded down: "1,200+"
    'open_listing_count' => 37,
];

// The newest members, for the avatar row in the hero — users rows, newest first.
// Stage B: SELECT id, username, full_name, avatar FROM users ORDER BY created_at DESC LIMIT 4
$mock_recent_members = [
    ['id' => 2, 'username' => 'aroid.amaya',   'full_name' => 'Amaya Madushani',     'avatar' => null],
    ['id' => 4, 'username' => 'dinesh.greens', 'full_name' => 'Dinesh Gunasekara',   'avatar' => null],
    ['id' => 5, 'username' => 'ruwan.roots',   'full_name' => 'Ruwan Rathnayake',    'avatar' => null],
    ['id' => 3, 'username' => 'kandy.jungle',  'full_name' => 'Kavindi Jayawardena', 'avatar' => null],
];

// A public plant from another member, for the hero collage — a plants row joined with
// plant_species (scientific_name) and users (username, full_name, avatar), plus like_count,
// a COUNT() on plant_likes (the optional COM-15 table).
$mock_featured_plant = [
    'id' => 51, 'user_id' => 2, 'nickname' => 'Pip', 'photo' => 'mock/pip.svg',
    'health_status' => 'thriving', 'is_public' => 1,
    'scientific_name' => 'Pilea peperomioides',
    'username' => 'aroid.amaya', 'full_name' => 'Amaya Madushani', 'avatar' => null,
    'like_count' => 48,
];

// Results from the ML service — rows of `ml_predictions`, one per type (wireframes p14, p15).
// For a species, predicted_label is the scientific name and common_name is joined from plant_species on it.
// For a disease, predicted_label is the model's class name, turned into words by prediction_name().
// The Landing page uses them for the collage chip and the smart tools teaser.
$mock_predictions = [
    ['id' => 3, 'user_id' => 1, 'plant_id' => 44, 'type' => 'species',
     'predicted_label' => 'Dracaena trifasciata', 'confidence' => 0.96,
     'image_path' => 'mock/sergeant.svg', 'created_at' => mock_date(-40) . ' 10:20:00',
     'common_name' => 'Snake plant'],

    ['id' => 4, 'user_id' => 1, 'plant_id' => 47, 'type' => 'disease',
     'predicted_label' => 'Tomato___Early_blight', 'confidence' => 0.92,
     'image_path' => 'mock/tommy.svg', 'created_at' => mock_date(-1) . ' 07:45:00',
     'common_name' => null],
];

// ── Sign up and Log in (P02, CORE-07 and CORE-09) ───────

// The member quoted in the community panel (wireframe p9) — a users row plus plant_count, a COUNT() on plants.
// The quote itself is page text, not data: there is no testimonials table.
$mock_quote_member = [
    'id' => 3, 'username' => 'kandy.jungle', 'full_name' => 'Kavindi Jayawardena', 'avatar' => null,
    'location' => 'Kandy',
    'plant_count' => 64,
];

// ── Dashboard (P03, CORE-24 and CORE-25) ────────────────

// Numbers for Nimali's whole garden, which the Dashboard cannot count from the short lists above.
// Stage B: SELECT health_status, COUNT(*) FROM plants WHERE user_id = ? GROUP BY health_status
//          SELECT COUNT(*) FROM care_reminders … WHERE next_due BETWEEN tomorrow AND 7 days from today
$mock_garden_stats = [
    'health_counts'  => ['thriving' => 13, 'stable' => 3, 'needs_attention' => 1, 'sick' => 1],   // adds up to 18 plants
    'upcoming_count' => 5,   // "Coming up this week · 5 tasks"
];

// Plants marked Sick or Needs attention (wireframe p10, element 4) — plants rows joined with:
//   plant_species  → common_name          collections → collection_name
//   health_logs    → log_note, log_source, logged_at of the plant's newest entry
//   ml_predictions → predicted_label, confidence, when that entry came from a Health Check (source 'ml')
//   care_reminders → task, next_due of the plant's soonest reminder
$mock_attention = [
    ['id' => 47, 'nickname' => 'Tommy', 'photo' => 'mock/tommy.svg', 'health_status' => 'sick',
     'common_name' => 'Tomato', 'collection_name' => 'Balcony',
     'log_note' => 'Brown rings on the lower leaves.', 'log_source' => 'ml', 'logged_at' => mock_date(-1) . ' 07:46:00',
     'predicted_label' => 'Tomato___Early_blight', 'confidence' => 0.92,
     'task' => 'water', 'next_due' => mock_date(1)],

    ['id' => 43, 'nickname' => 'Goldie', 'photo' => 'mock/goldie.svg', 'health_status' => 'needs_attention',
     'common_name' => 'Golden pothos', 'collection_name' => 'Living room',
     'log_note' => 'Leaves may start to droop.', 'log_source' => 'manual', 'logged_at' => mock_date(-3) . ' 18:10:00',
     'predicted_label' => null, 'confidence' => null,
     'task' => 'water', 'next_due' => mock_date(-2)],
];

// New interest in Nimali's listings (wireframe p10, element 7) — listing_interests rows with status 'new',
// joined with users (who is interested) and exchange_listings (title).
$mock_new_interests = [
    ['id' => 21, 'listing_id' => 7, 'user_id' => 4, 'status' => 'new', 'created_at' => mock_date(0) . ' 08:20:00',
     'username' => 'dinesh.greens', 'full_name' => 'Dinesh Gunasekara', 'avatar' => null,
     'title' => "Pothos 'Golden', 3 rooted cuttings"],
    ['id' => 20, 'listing_id' => 7, 'user_id' => 2, 'status' => 'new', 'created_at' => mock_date(-1) . ' 19:05:00',
     'username' => 'aroid.amaya', 'full_name' => 'Amaya Madushani', 'avatar' => null,
     'title' => "Pothos 'Golden', 3 rooted cuttings"],
    ['id' => 19, 'listing_id' => 7, 'user_id' => 5, 'status' => 'new', 'created_at' => mock_date(-1) . ' 11:30:00',
     'username' => 'ruwan.roots', 'full_name' => 'Ruwan Rathnayake', 'avatar' => null,
     'title' => "Pothos 'Golden', 3 rooted cuttings"],
    ['id' => 18, 'listing_id' => 7, 'user_id' => 3, 'status' => 'new', 'created_at' => mock_date(-2) . ' 16:45:00',
     'username' => 'kandy.jungle', 'full_name' => 'Kavindi Jayawardena', 'avatar' => null,
     'title' => "Pothos 'Golden', 3 rooted cuttings"],
];

// ── Plant Profile (P05, CORE-18 and CORE-21) ───────────

// The species guide — rows of `plant_species`, keyed by id so a plant finds its row with $mock_species[$species_id].
// light, water, humidity and notes come from the proposal; pet_safe and the two default intervals are the
// DES-07 additions (column names are my proposal until DES-13 writes database/species.csv).
// The default intervals fill in a new plant's reminders on the Add Plant form (P06).
$mock_species = [
    1 => ['id' => 1, 'common_name' => 'Swiss cheese plant', 'scientific_name' => 'Monstera deliciosa',
          'light' => 'Bright, indirect', 'water' => 'When the top 5 cm of soil are dry', 'humidity' => '60%+ preferred',
          'pet_safe' => 0, 'water_interval_days' => 7, 'fertilise_interval_days' => 30,
          'notes' => 'Give it a moss pole to climb: the leaves grow bigger and split more as it climbs. Wipe the leaves now and then so they can take in light.'],
    2 => ['id' => 2, 'common_name' => 'Golden pothos', 'scientific_name' => 'Epipremnum aureum',
          'light' => 'Low to bright, indirect', 'water' => 'When the top half of the soil is dry', 'humidity' => '40%+',
          'pet_safe' => 0, 'water_interval_days' => 5, 'fertilise_interval_days' => 30,
          'notes' => 'Drooping leaves are its way of asking for water. Cuttings root easily in a glass of water.'],
    3 => ['id' => 3, 'common_name' => 'Snake plant', 'scientific_name' => 'Dracaena trifasciata',
          'light' => 'Low to bright', 'water' => 'Only when the soil is fully dry', 'humidity' => 'Any',
          'pet_safe' => 0, 'water_interval_days' => 14, 'fertilise_interval_days' => 30,
          'notes' => 'Too much water is the usual way to lose one. Water less in the rainy season.'],
    4 => ['id' => 4, 'common_name' => 'Fiddle-leaf fig', 'scientific_name' => 'Ficus lyrata',
          'light' => 'Bright, indirect, some sun', 'water' => 'When the top 3 cm are dry', 'humidity' => '50%+',
          'pet_safe' => 0, 'water_interval_days' => 7, 'fertilise_interval_days' => 30,
          'notes' => 'Dislikes being moved: expect a dropped leaf or two after a change of place.'],
    5 => ['id' => 5, 'common_name' => 'Aloe vera', 'scientific_name' => 'Aloe vera',
          'light' => 'Bright, direct sun', 'water' => 'Only when the soil is fully dry', 'humidity' => 'Low, dry air is fine',
          'pet_safe' => 0, 'water_interval_days' => 14, 'fertilise_interval_days' => 60,
          'notes' => 'Use a gritty, fast-draining mix and a pot with a hole. Pups can be potted up once they have roots.'],
    6 => ['id' => 6, 'common_name' => 'Tomato', 'scientific_name' => 'Solanum lycopersicum',
          'light' => 'Full sun, 6+ hours', 'water' => 'Keep the soil evenly moist', 'humidity' => 'Any',
          'pet_safe' => 0, 'water_interval_days' => 2, 'fertilise_interval_days' => 14,
          'notes' => 'Water the soil, not the leaves: wet leaves invite early blight. Remove the lowest leaves as the plant grows.'],
    7 => ['id' => 7, 'common_name' => 'Peace lily', 'scientific_name' => 'Spathiphyllum wallisii',
          'light' => 'Low to medium, indirect', 'water' => 'When the leaves just begin to droop', 'humidity' => '50%+',
          'pet_safe' => 0, 'water_interval_days' => 4, 'fertilise_interval_days' => 45,
          'notes' => 'Brown leaf tips usually mean dry air or tap water with a lot of chlorine.'],
    8 => ['id' => 8, 'common_name' => 'Mexican snowball', 'scientific_name' => 'Echeveria elegans',
          'light' => 'Bright, some direct sun', 'water' => 'Only when the soil is fully dry', 'humidity' => 'Low',
          'pet_safe' => 1, 'water_interval_days' => 10, 'fertilise_interval_days' => 60,
          'notes' => 'Water the soil, never into the rosette. A stretched, leaning stem means it wants more light.'],

    // Species of other members' plants, for Explore (P09). The Thai Constellation is a cultivar of
    // Monstera deliciosa with its own row, because wireframe p16 prints its own scientific name on the card.
    9 => ['id' => 9, 'common_name' => 'Thai Constellation monstera', 'scientific_name' => "Monstera 'Thai Constellation'",
          'light' => 'Bright, indirect', 'water' => 'When the top 5 cm of soil are dry', 'humidity' => '60%+ preferred',
          'pet_safe' => 0, 'water_interval_days' => 7, 'fertilise_interval_days' => 30,
          'notes' => 'The cream patches cannot make food, so it grows slower than a green monstera and needs more light.'],
    10 => ['id' => 10, 'common_name' => 'Swiss cheese vine', 'scientific_name' => 'Monstera adansonii',
          'light' => 'Bright, indirect', 'water' => 'When the top 3 cm of soil are dry', 'humidity' => '60%+ preferred',
          'pet_safe' => 0, 'water_interval_days' => 6, 'fertilise_interval_days' => 30,
          'notes' => 'A climber or a trailer: give it a pole or let it hang. The holes appear as the leaves mature.'],
    11 => ['id' => 11, 'common_name' => 'Chinese money plant', 'scientific_name' => 'Pilea peperomioides',
          'light' => 'Bright, indirect', 'water' => 'When the top half of the soil is dry', 'humidity' => '40%+',
          'pet_safe' => 1, 'water_interval_days' => 7, 'fertilise_interval_days' => 30,
          'notes' => 'Turn the pot a quarter each week so it grows straight. Its baby plants are easy to share.'],
    12 => ['id' => 12, 'common_name' => 'Heartleaf philodendron', 'scientific_name' => 'Philodendron hederaceum',
          'light' => 'Low to bright, indirect', 'water' => 'When the top 3 cm of soil are dry', 'humidity' => '40%+',
          'pet_safe' => 0, 'water_interval_days' => 6, 'fertilise_interval_days' => 30,
          'notes' => 'Pale new leaves mean it wants more light. Pinch the tips to keep it bushy.'],
];

// Every reminder of Nimali's plants — rows of `care_reminders`. $mock_reminders above holds only today's three;
// these are all of them, with the same ids, and each keeps next_due = last_done + interval_days.
$mock_plant_reminders = [
    ['id' => 11, 'plant_id' => 42, 'task' => 'water',     'interval_days' => 7,  'last_done' => mock_date(-7),  'next_due' => mock_date(0)],
    ['id' => 14, 'plant_id' => 42, 'task' => 'fertilise', 'interval_days' => 30, 'last_done' => mock_date(-18), 'next_due' => mock_date(12)],
    ['id' => 12, 'plant_id' => 43, 'task' => 'water',     'interval_days' => 5,  'last_done' => mock_date(-7),  'next_due' => mock_date(-2)],
    ['id' => 13, 'plant_id' => 44, 'task' => 'fertilise', 'interval_days' => 30, 'last_done' => mock_date(-30), 'next_due' => mock_date(0)],
    ['id' => 15, 'plant_id' => 44, 'task' => 'water',     'interval_days' => 14, 'last_done' => mock_date(-10), 'next_due' => mock_date(4)],
    ['id' => 16, 'plant_id' => 45, 'task' => 'water',     'interval_days' => 7,  'last_done' => mock_date(-4),  'next_due' => mock_date(3)],
    ['id' => 17, 'plant_id' => 45, 'task' => 'fertilise', 'interval_days' => 30, 'last_done' => mock_date(-20), 'next_due' => mock_date(10)],
    ['id' => 18, 'plant_id' => 48, 'task' => 'water',     'interval_days' => 4,  'last_done' => mock_date(-3),  'next_due' => mock_date(1)],
    ['id' => 19, 'plant_id' => 46, 'task' => 'water',     'interval_days' => 14, 'last_done' => mock_date(-5),  'next_due' => mock_date(9)],
    ['id' => 20, 'plant_id' => 47, 'task' => 'water',     'interval_days' => 2,  'last_done' => mock_date(-1),  'next_due' => mock_date(1)],
    ['id' => 21, 'plant_id' => 47, 'task' => 'fertilise', 'interval_days' => 14, 'last_done' => mock_date(-7),  'next_due' => mock_date(7)],
    ['id' => 22, 'plant_id' => 49, 'task' => 'water',     'interval_days' => 10, 'last_done' => mock_date(-4),  'next_due' => mock_date(6)],
];

// The plant story — rows of `health_logs`, newest first (wireframe p12, element 6). An entry written by an AI tool
// has source 'ml' and is joined with its ml_predictions row: prediction_type, predicted_label, confidence.
// A plant's oldest entry is the day it was added to the garden. The newest date of each plant matches its
// last_logged_at in $mock_plants, and Tommy's and Goldie's newest notes are the ones on the Dashboard.
$mock_health_logs = [
    ['id' => 101, 'plant_id' => 42, 'status' => 'thriving', 'source' => 'manual', 'photo' => 'mock/monty.svg',
     'note' => 'New leaf unfurled, the biggest one yet, with four splits.', 'logged_at' => mock_date(-5) . ' 18:20:00',
     'prediction_type' => null, 'predicted_label' => null, 'confidence' => null],
    ['id' => 95, 'plant_id' => 42, 'status' => 'thriving', 'source' => 'manual', 'photo' => null,
     'note' => 'Repotted into a 25 cm terracotta pot with chunky aroid mix.', 'logged_at' => mock_date(-31) . ' 16:00:00',
     'prediction_type' => null, 'predicted_label' => null, 'confidence' => null],
    ['id' => 61, 'plant_id' => 42, 'status' => 'thriving', 'source' => 'ml', 'photo' => 'mock/monty.svg',
     'note' => 'Added to garden.', 'logged_at' => '2025-03-20 10:00:00',
     'prediction_type' => 'species', 'predicted_label' => 'Monstera deliciosa', 'confidence' => 0.91],

    ['id' => 99, 'plant_id' => 43, 'status' => 'needs_attention', 'source' => 'manual', 'photo' => null,
     'note' => 'Leaves may start to droop.', 'logged_at' => mock_date(-3) . ' 18:10:00',
     'prediction_type' => null, 'predicted_label' => null, 'confidence' => null],
    ['id' => 62, 'plant_id' => 43, 'status' => 'thriving', 'source' => 'manual', 'photo' => 'mock/goldie.svg',
     'note' => 'Added to garden.', 'logged_at' => '2025-04-02 11:30:00',
     'prediction_type' => null, 'predicted_label' => null, 'confidence' => null],

    ['id' => 93, 'plant_id' => 44, 'status' => 'thriving', 'source' => 'manual', 'photo' => null,
     'note' => 'Two new pups at the base.', 'logged_at' => mock_date(-12) . ' 09:00:00',
     'prediction_type' => null, 'predicted_label' => null, 'confidence' => null],
    ['id' => 88, 'plant_id' => 44, 'status' => 'thriving', 'source' => 'ml', 'photo' => 'mock/sergeant.svg',
     'note' => 'Checked the species with the Plant Identifier.', 'logged_at' => mock_date(-40) . ' 10:20:00',
     'prediction_type' => 'species', 'predicted_label' => 'Dracaena trifasciata', 'confidence' => 0.96],
    ['id' => 63, 'plant_id' => 44, 'status' => 'stable', 'source' => 'manual', 'photo' => 'mock/sergeant.svg',
     'note' => 'Added to garden.', 'logged_at' => '2025-05-11 15:00:00',
     'prediction_type' => null, 'predicted_label' => null, 'confidence' => null],

    ['id' => 96, 'plant_id' => 45, 'status' => 'stable', 'source' => 'manual', 'photo' => null,
     'note' => 'Dropped one lower leaf after the move to the window.', 'logged_at' => mock_date(-9) . ' 17:30:00',
     'prediction_type' => null, 'predicted_label' => null, 'confidence' => null],
    ['id' => 64, 'plant_id' => 45, 'status' => 'thriving', 'source' => 'manual', 'photo' => 'mock/figgy.svg',
     'note' => 'Added to garden.', 'logged_at' => '2025-06-18 12:00:00',
     'prediction_type' => null, 'predicted_label' => null, 'confidence' => null],

    ['id' => 94, 'plant_id' => 48, 'status' => 'stable', 'source' => 'manual', 'photo' => null,
     'note' => 'Drooped on Sunday, perked up an hour after watering.', 'logged_at' => mock_date(-15) . ' 08:40:00',
     'prediction_type' => null, 'predicted_label' => null, 'confidence' => null],
    ['id' => 70, 'plant_id' => 48, 'status' => 'stable', 'source' => 'manual', 'photo' => 'mock/lily.svg',
     'note' => 'Added to garden.', 'logged_at' => '2025-09-02 10:00:00',
     'prediction_type' => null, 'predicted_label' => null, 'confidence' => null],

    ['id' => 91, 'plant_id' => 46, 'status' => 'thriving', 'source' => 'manual', 'photo' => 'mock/vera.svg',
     'note' => 'Four pups ready to pot up.', 'logged_at' => mock_date(-20) . ' 16:00:00',
     'prediction_type' => null, 'predicted_label' => null, 'confidence' => null],
    ['id' => 65, 'plant_id' => 46, 'status' => 'thriving', 'source' => 'manual', 'photo' => 'mock/vera.svg',
     'note' => 'Added to garden.', 'logged_at' => '2025-07-06 09:30:00',
     'prediction_type' => null, 'predicted_label' => null, 'confidence' => null],

    ['id' => 102, 'plant_id' => 47, 'status' => 'sick', 'source' => 'ml', 'photo' => 'mock/tommy.svg',
     'note' => 'Brown rings on the lower leaves.', 'logged_at' => mock_date(-1) . ' 07:46:00',
     'prediction_type' => 'disease', 'predicted_label' => 'Tomato___Early_blight', 'confidence' => 0.92],
    ['id' => 98, 'plant_id' => 47, 'status' => 'stable', 'source' => 'manual', 'photo' => null,
     'note' => 'Fruit setting on the first truss.', 'logged_at' => mock_date(-6) . ' 17:00:00',
     'prediction_type' => null, 'predicted_label' => null, 'confidence' => null],
    ['id' => 66, 'plant_id' => 47, 'status' => 'stable', 'source' => 'manual', 'photo' => 'mock/tommy.svg',
     'note' => 'Added to garden.', 'logged_at' => '2025-08-23 08:00:00',
     'prediction_type' => null, 'predicted_label' => null, 'confidence' => null],

    ['id' => 90, 'plant_id' => 49, 'status' => 'thriving', 'source' => 'manual', 'photo' => null,
     'note' => 'Moved to the sunniest windowsill.', 'logged_at' => mock_date(-26) . ' 10:15:00',
     'prediction_type' => null, 'predicted_label' => null, 'confidence' => null],
    ['id' => 71, 'plant_id' => 49, 'status' => 'thriving', 'source' => 'manual', 'photo' => 'mock/pearl.svg',
     'note' => 'Added to garden.', 'logged_at' => '2025-09-27 11:00:00',
     'prediction_type' => null, 'predicted_label' => null, 'confidence' => null],
];

// Extra photos for the gallery — rows of the optional `plant_photos` table, newest first.
// Only placeholder drawings exist, so Monty's four photos are the same drawing; real photos replace them.
$mock_plant_photos = [
    ['id' => 204, 'plant_id' => 42, 'path' => 'mock/monty.svg', 'created_at' => mock_date(-5) . ' 18:20:00'],
    ['id' => 203, 'plant_id' => 42, 'path' => 'mock/monty.svg', 'created_at' => mock_date(-31) . ' 16:00:00'],
    ['id' => 202, 'plant_id' => 42, 'path' => 'mock/monty.svg', 'created_at' => '2026-01-10 09:00:00'],
    ['id' => 201, 'plant_id' => 42, 'path' => 'mock/monty.svg', 'created_at' => '2025-03-20 10:00:00'],
    ['id' => 210, 'plant_id' => 47, 'path' => 'mock/tommy.svg', 'created_at' => mock_date(-1) . ' 07:46:00'],
    ['id' => 211, 'plant_id' => 46, 'path' => 'mock/vera.svg',  'created_at' => mock_date(-20) . ' 16:00:00'],
];

// Members as other people see them — users rows keyed by id, public columns only (never email or phone),
// plus the counts stage B calculates (plant_count, rating_avg, rating_count). The Plant Profile joins its owner
// from here, so the owner card still shows Nimali while you preview the page as a visitor.
// Towns and the counts of Amaya, Kavindi and Dinesh are from wireframe p16; Ruwan's and every created_at are invented.
$mock_users = [
    1 => ['id' => 1, 'username' => 'nimali.grows', 'full_name' => 'Nimali Perera', 'avatar' => null,
          'location' => 'Kurunegala', 'created_at' => '2025-03-14 09:00:00',
          'plant_count' => 18, 'rating_avg' => 4.9, 'rating_count' => 23],
    2 => ['id' => 2, 'username' => 'aroid.amaya', 'full_name' => 'Amaya Madushani', 'avatar' => null,
          'location' => 'Colombo', 'created_at' => '2024-11-02 19:30:00',
          'plant_count' => 42, 'rating_avg' => 4.8, 'rating_count' => 14],
    3 => ['id' => 3, 'username' => 'kandy.jungle', 'full_name' => 'Kavindi Jayawardena', 'avatar' => null,
          'location' => 'Kandy', 'created_at' => '2024-08-21 07:15:00',
          'plant_count' => 64, 'rating_avg' => 5.0, 'rating_count' => 11],
    4 => ['id' => 4, 'username' => 'dinesh.greens', 'full_name' => 'Dinesh Gunasekara', 'avatar' => null,
          'location' => 'Negombo', 'created_at' => '2025-01-09 12:00:00',
          'plant_count' => 37, 'rating_avg' => 4.7, 'rating_count' => 15],
    5 => ['id' => 5, 'username' => 'ruwan.roots', 'full_name' => 'Ruwan Rathnayake', 'avatar' => null,
          'location' => 'Kurunegala', 'created_at' => '2025-05-27 18:45:00',
          'plant_count' => 21, 'rating_avg' => 4.6, 'rating_count' => 9],
];

// ── Explore (P09, COM-01 to COM-03) ─────────────────────

// Other members' plants — rows of `plants` joined with plant_species (scientific_name, common_name) and
// collections (collection_name), shaped like $mock_plants but without reminders: those are the owner's own business.
// Searching "monstera" finds the six monsteras of wireframe p16 (Thai Con, Big Momma, Swiss, Monty, Leafy, Holey);
// the rest give the filters something to filter. Pip is the Landing page's featured plant. Whose Leafy and Holey are,
// the collections, dates and the plants after Holey are invented. Photos are placeholder drawings.
$mock_community_plants = [
    ['id' => 51, 'user_id' => 2, 'collection_id' => 21, 'species_id' => 11, 'nickname' => 'Pip',
     'photo' => 'mock/pip.svg', 'acquired_on' => '2025-02-14', 'health_status' => 'thriving', 'is_public' => 1,
     'scientific_name' => 'Pilea peperomioides', 'common_name' => 'Chinese money plant',
     'collection_name' => 'Windowsill', 'last_logged_at' => mock_date(-4) . ' 08:30:00'],

    ['id' => 52, 'user_id' => 3, 'collection_id' => 31, 'species_id' => 9, 'nickname' => 'Thai Con',
     'photo' => 'mock/monty.svg', 'acquired_on' => '2025-01-05', 'health_status' => 'thriving', 'is_public' => 1,
     'scientific_name' => "Monstera 'Thai Constellation'", 'common_name' => 'Thai Constellation monstera',
     'collection_name' => 'Aroids', 'last_logged_at' => mock_date(-2) . ' 17:10:00'],

    ['id' => 53, 'user_id' => 2, 'collection_id' => 22, 'species_id' => 1, 'nickname' => 'Big Momma',
     'photo' => 'mock/monty.svg', 'acquired_on' => '2024-11-20', 'health_status' => 'thriving', 'is_public' => 1,
     'scientific_name' => 'Monstera deliciosa', 'common_name' => 'Swiss cheese plant',
     'collection_name' => 'Living room', 'last_logged_at' => mock_date(-6) . ' 19:00:00'],

    ['id' => 54, 'user_id' => 4, 'collection_id' => 41, 'species_id' => 10, 'nickname' => 'Swiss',
     'photo' => 'mock/monty.svg', 'acquired_on' => '2025-03-02', 'health_status' => 'thriving', 'is_public' => 1,
     'scientific_name' => 'Monstera adansonii', 'common_name' => 'Swiss cheese vine',
     'collection_name' => 'Hallway', 'last_logged_at' => mock_date(-8) . ' 07:50:00'],

    ['id' => 55, 'user_id' => 5, 'collection_id' => 51, 'species_id' => 1, 'nickname' => 'Leafy',
     'photo' => 'mock/monty.svg', 'acquired_on' => '2025-06-30', 'health_status' => 'thriving', 'is_public' => 1,
     'scientific_name' => 'Monstera deliciosa', 'common_name' => 'Swiss cheese plant',
     'collection_name' => 'Verandah', 'last_logged_at' => mock_date(-11) . ' 16:20:00'],

    ['id' => 56, 'user_id' => 2, 'collection_id' => 22, 'species_id' => 10, 'nickname' => 'Holey',
     'photo' => 'mock/monty.svg', 'acquired_on' => '2025-08-11', 'health_status' => 'thriving', 'is_public' => 1,
     'scientific_name' => 'Monstera adansonii', 'common_name' => 'Swiss cheese vine',
     'collection_name' => 'Living room', 'last_logged_at' => mock_date(-3) . ' 12:40:00'],

    ['id' => 57, 'user_id' => 3, 'collection_id' => 31, 'species_id' => 12, 'nickname' => 'Hugo',
     'photo' => 'mock/goldie.svg', 'acquired_on' => '2025-04-18', 'health_status' => 'stable', 'is_public' => 1,
     'scientific_name' => 'Philodendron hederaceum', 'common_name' => 'Heartleaf philodendron',
     'collection_name' => 'Aroids', 'last_logged_at' => mock_date(-14) . ' 09:30:00'],

    ['id' => 58, 'user_id' => 5, 'collection_id' => 51, 'species_id' => 2, 'nickname' => 'Marble',
     'photo' => 'mock/goldie.svg', 'acquired_on' => '2025-07-22', 'health_status' => 'needs_attention', 'is_public' => 1,
     'scientific_name' => 'Epipremnum aureum', 'common_name' => 'Golden pothos',
     'collection_name' => 'Verandah', 'last_logged_at' => mock_date(-2) . ' 18:00:00'],

    ['id' => 59, 'user_id' => 4, 'collection_id' => 41, 'species_id' => 3, 'nickname' => 'Spike',
     'photo' => 'mock/sergeant.svg', 'acquired_on' => '2025-02-01', 'health_status' => 'thriving', 'is_public' => 1,
     'scientific_name' => 'Dracaena trifasciata', 'common_name' => 'Snake plant',
     'collection_name' => 'Hallway', 'last_logged_at' => mock_date(-5) . ' 10:10:00'],

    ['id' => 60, 'user_id' => 3, 'collection_id' => 32, 'species_id' => 8, 'nickname' => 'Rosie',
     'photo' => 'mock/pearl.svg', 'acquired_on' => '2025-05-09', 'health_status' => 'stable', 'is_public' => 1,
     'scientific_name' => 'Echeveria elegans', 'common_name' => 'Mexican snowball',
     'collection_name' => 'Succulent shelf', 'last_logged_at' => mock_date(-18) . ' 15:00:00'],

    ['id' => 61, 'user_id' => 5, 'collection_id' => 52, 'species_id' => 5, 'nickname' => 'Mother Aloe',
     'photo' => 'mock/vera.svg', 'acquired_on' => '2024-12-12', 'health_status' => 'thriving', 'is_public' => 1,
     'scientific_name' => 'Aloe vera', 'common_name' => 'Aloe vera',
     'collection_name' => 'Garden', 'last_logged_at' => mock_date(-3) . ' 17:40:00'],
];

// ── Add a plant: the AI suggestion (P06, ML-13) ─────────

// What the Flask service answers for an uploaded photo (POST /predict/species, ml-service/README.md):
// the model version and the top 3 species, most likely first. Stage M: includes/ml-client.php receives this
// from cURL, json_decode()s it into exactly this shape, and stores the top result in ml_predictions.
// upload_name and upload_size describe the photo that was sent; they come from $_FILES, not from Flask.
$mock_species_result = [
    'model_version' => 'species-mobilenetv2-v1',
    'upload_name'   => 'monty.jpg',
    'upload_size'   => 2516582,   // bytes: 2.4 MB
    'image_path'    => 'mock/monty.svg',
    'predictions'   => [
        ['label' => 'Monstera deliciosa',          'confidence' => 0.91],
        ['label' => 'Philodendron bipinnatifidum', 'confidence' => 0.06],
        ['label' => 'Epipremnum pinnatum',         'confidence' => 0.02],
    ],
];

// The same photo when the model is not sure (state S3, wireframe p24): below 60% it is never shown as an answer.
$mock_species_result_unsure = array_merge($mock_species_result, [
    'predictions' => [
        ['label' => 'Philodendron bipinnatifidum', 'confidence' => 0.48],
        ['label' => 'Monstera deliciosa',          'confidence' => 0.31],
        ['label' => 'Epipremnum pinnatum',         'confidence' => 0.12],
    ],
]);

// Likes per public plant: COUNT(*) on the optional plant_likes table (COM-15), keyed by plant id.
// Private plants (Vera, Pearl) cannot be seen by others, so they have none.
// Thai Con 87, Big Momma 48, Swiss 31, Monty 12, Leafy 9, Holey 7 are from wireframe p16; Pip's 48 from the Landing page.
$mock_like_counts = [
    42 => 12, 43 => 3, 44 => 7, 45 => 2, 48 => 4, 47 => 5,
    51 => 48, 52 => 87, 53 => 48, 54 => 31, 55 => 9, 56 => 7, 57 => 22, 58 => 5, 59 => 14, 60 => 11, 61 => 6,
];

// Preview switch: ?empty shows Nimali as a brand-new member, before her first plant (state S1).
// Like ?guest it is kept in the session, so every page agrees: the Dashboard, My Garden and her profile all
// stay empty until ?member or Log in brings her garden back. Create account turns it on.
// It empties only her own rows. The Landing page ignores it: its collage borrows Monty as a public sample.
if (isset($_GET['empty'])) {
    $_SESSION['preview_empty'] = true;
}
if (!empty($_SESSION['preview_empty']) && $current_user && current_page() !== 'index.php') {
    $current_user['plant_count']        = 0;
    $current_user['collection_count']   = 0;
    $current_user['new_interest_count'] = 0;

    // Only her plants' likes go; other members' plants keep theirs. This line must run before $mock_plants is emptied.
    $mock_like_counts   = array_diff_key($mock_like_counts, array_flip(array_column($mock_plants, 'id')));
    $mock_plants        = [];
    $mock_collections   = [];
    $mock_plant_reminders = [];
    $mock_health_logs   = [];
    $mock_plant_photos  = [];
    $mock_reminders     = [];
    $mock_attention     = [];
    $mock_new_interests = [];
    $mock_garden_stats  = [
        'health_counts'  => ['thriving' => 0, 'stable' => 0, 'needs_attention' => 0, 'sick' => 0],
        'upcoming_count' => 0,
    ];

    // A new account has no listings yet, and nothing to count on its public profile
    $mock_listings = array_values(array_filter($mock_listings, fn ($listing) => $listing['user_id'] !== $current_user['id']));
    $mock_users[$current_user['id']] = array_merge($mock_users[$current_user['id']], [
        'plant_count' => 0, 'rating_avg' => 0, 'rating_count' => 0,
    ]);
}

// Every public plant in the community, as Explore (P09) searches them: Nimali's public plants and everyone else's,
// each joined with its owner (users: username, full_name, avatar, location), plus two calculated columns:
//   like_count  — COUNT(*) on plant_likes
//   on_exchange — 1 when an open exchange listing offers this plant (EXISTS on exchange_listings)
// Built here, after the ?empty switch, so a brand-new Nimali has no plants in it. Stage B: one query with
// WHERE plants.is_public = 1, so a private plant can never reach the page.
$listed_plant_ids = array_column(array_filter($mock_listings, fn ($listing) => $listing['status'] === 'open'), 'plant_id');

$mock_public_plants = [];
foreach (array_merge($mock_plants, $mock_community_plants) as $plant) {
    if (!$plant['is_public']) {
        continue;
    }
    $owner = $mock_users[$plant['user_id']];
    $mock_public_plants[] = array_merge($plant, [
        'username'    => $owner['username'],
        'full_name'   => $owner['full_name'],
        'avatar'      => $owner['avatar'],
        'location'    => $owner['location'],
        'like_count'  => $mock_like_counts[$plant['id']] ?? 0,
        'on_exchange' => in_array($plant['id'], $listed_plant_ids, true) ? 1 : 0,
    ]);
}
