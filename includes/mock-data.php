<?php
// Stage F only: arrays shaped like future database rows (MOD-01 foundation, week 1).
// Each array is replaced by a PDO query in stage B, then removed from this file.
// Sample content comes from the wireframes so pages can be compared with the PDF and Figma.

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
