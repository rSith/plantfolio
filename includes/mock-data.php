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

    // Not columns of `users`: stage B calculates these with COUNT() and AVG() on other tables.
    'plant_count'      => 18,
    'collection_count' => 3,
    'swap_count'       => 12,
    'rating_avg'       => 4.9,
    'rating_count'     => 23,
    'new_interest_count' => 4,   // people newly interested in her listings (p10): the dot on the header bell
];

// Preview switch: add ?guest to any address to see the page as a visitor who is not logged in.
// A visitor has no users row, so $current_user is null — exactly what the session will give in stage B.
if (isset($_GET['guest'])) {
    $current_user = null;
}

// Nimali's plants — rows of `plants` (wireframe p11), each joined with:
//   plant_species  → scientific_name, common_name
//   collections    → collection_name
//   care_reminders → task, interval_days, next_due of the plant's soonest reminder
$mock_plants = [
    ['id' => 42, 'user_id' => 1, 'collection_id' => 1, 'species_id' => 1, 'nickname' => 'Monty',
     'photo' => 'mock/monty.svg', 'acquired_on' => '2025-03-20', 'health_status' => 'thriving', 'is_public' => 1,
     'scientific_name' => 'Monstera deliciosa', 'common_name' => 'Swiss cheese plant',
     'collection_name' => 'Living room',
     'task' => 'water', 'interval_days' => 7, 'next_due' => mock_date(0)],

    ['id' => 43, 'user_id' => 1, 'collection_id' => 1, 'species_id' => 2, 'nickname' => 'Goldie',
     'photo' => 'mock/goldie.svg', 'acquired_on' => '2025-04-02', 'health_status' => 'needs_attention', 'is_public' => 1,
     'scientific_name' => 'Epipremnum aureum', 'common_name' => 'Golden pothos',
     'collection_name' => 'Living room',
     'task' => 'water', 'interval_days' => 5, 'next_due' => mock_date(-2)],

    ['id' => 44, 'user_id' => 1, 'collection_id' => 1, 'species_id' => 3, 'nickname' => 'Sergeant',
     'photo' => 'mock/sergeant.svg', 'acquired_on' => '2025-05-11', 'health_status' => 'thriving', 'is_public' => 1,
     'scientific_name' => 'Dracaena trifasciata', 'common_name' => 'Snake plant',
     'collection_name' => 'Living room',
     'task' => 'fertilise', 'interval_days' => 30, 'next_due' => mock_date(0)],

    ['id' => 45, 'user_id' => 1, 'collection_id' => 1, 'species_id' => 4, 'nickname' => 'Figgy',
     'photo' => 'mock/figgy.svg', 'acquired_on' => '2025-06-18', 'health_status' => 'stable', 'is_public' => 1,
     'scientific_name' => 'Ficus lyrata', 'common_name' => 'Fiddle-leaf fig',
     'collection_name' => 'Living room',
     'task' => 'water', 'interval_days' => 7, 'next_due' => mock_date(3)],

    ['id' => 46, 'user_id' => 1, 'collection_id' => 3, 'species_id' => 5, 'nickname' => 'Vera',
     'photo' => 'mock/vera.svg', 'acquired_on' => '2025-07-06', 'health_status' => 'thriving', 'is_public' => 0,
     'scientific_name' => 'Aloe vera', 'common_name' => 'Aloe vera',
     'collection_name' => 'Succulents',
     'task' => 'water', 'interval_days' => 14, 'next_due' => mock_date(9)],

    ['id' => 47, 'user_id' => 1, 'collection_id' => 2, 'species_id' => 6, 'nickname' => 'Tommy',
     'photo' => 'mock/tommy.svg', 'acquired_on' => '2025-08-23', 'health_status' => 'sick', 'is_public' => 1,
     'scientific_name' => 'Solanum lycopersicum', 'common_name' => 'Tomato',
     'collection_name' => 'Balcony',
     'task' => 'water', 'interval_days' => 2, 'next_due' => mock_date(1)],
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

    ['id' => 8, 'user_id' => 5, 'plant_id' => null, 'title' => 'Aloe vera pups (4)',
     'type' => 'free', 'looking_for' => 'nothing, free to a good home', 'location' => 'Kurunegala',
     'status' => 'open', 'photo' => 'mock/vera.svg', 'created_at' => mock_date(-3) . ' 17:40:00',
     'username' => 'ruwan.roots', 'full_name' => 'Ruwan Rathnayake', 'avatar' => null,
     'rating_avg' => 4.6, 'rating_count' => 9, 'interest_count' => 2, 'distance_km' => 5],

    ['id' => 9, 'user_id' => 4, 'plant_id' => null, 'title' => "Snake plant 'Laurentii'",
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

// Preview switch: add ?empty to a member page to see Nimali's garden before her first plant (state S1).
// It empties only her own rows. The Landing page ignores it: its collage borrows Monty as a public sample.
if (isset($_GET['empty']) && $current_user && current_page() !== 'index.php') {
    $current_user['plant_count']        = 0;
    $current_user['collection_count']   = 0;
    $current_user['new_interest_count'] = 0;

    $mock_plants        = [];
    $mock_reminders     = [];
    $mock_attention     = [];
    $mock_new_interests = [];
    $mock_garden_stats  = [
        'health_counts'  => ['thriving' => 0, 'stable' => 0, 'needs_attention' => 0, 'sick' => 0],
        'upcoming_count' => 0,
    ];
}
