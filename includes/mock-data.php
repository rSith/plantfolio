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
];

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

// Open listings on the marketplace — rows of `exchange_listings` (wireframe p17), each joined with:
//   users → username, full_name, avatar of the owner
// and four calculated columns: rating_avg and rating_count (from user_ratings), interest_count
// (from listing_interests) and distance_km (between the viewer's town and the listing's town).
// Columns only the detail page needs (description, share_email, share_phone …) are added with that page.
$mock_listings = [
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
