<?php
// The Community page's searches (community.php, P09 + P10 merged): one function per tab (COM-01, COM-02, COM-03, COM-07).
// Each function takes the search word and that tab's filters and returns the matching rows, unsorted.
// Stage F: they filter the mock arrays in PHP. Stage B: the first parameter becomes $pdo and each body becomes one
// prepared statement (the SQL is in the comment above each function), so community.php does not change.
// Kept apart from the page because the page runs them more than once: for the tab that is open and, with the
// default filters, for the counts on the other two tabs.

/** The filters each tab starts with: what a tab shows before anyone changes a filter. */
function community_default_filters($tab, $has_town = false)
{
    $defaults = [
        'plants'   => ['species' => [], 'health' => '', 'location' => '', 'exchange' => ''],
        'exchange' => ['type' => '', 'distance' => $has_town ? '25' : 'any', 'species' => []],
        'people'   => ['location' => '', 'rating' => ''],
    ];

    return $defaults[$tab];
}

/** Does $term appear in any of these pieces of text? mb_stripos ignores capitals and handles any language. */
function community_text_matches(array $texts, $term)
{
    return $term === '' || mb_stripos(implode(' ', $texts), $term) !== false;
}

// ── Plants tab ───────────────────────────────────────────

/**
 * Public plants matching the search and the Plants filters (species ids, health, location, and exchange = '1' for
 * only the plants that an open exchange listing offers).
 * $skip_species = true ignores the species filter: the sidebar counts each species from those rows.
 *
 * Stage B:
 *   SELECT plants.*, plant_species.common_name, plant_species.scientific_name,
 *          users.username, users.full_name, users.avatar, users.location,
 *          (SELECT COUNT(*) FROM plant_likes WHERE plant_likes.plant_id = plants.id) AS like_count,
 *          exchange_listings.id AS listing_id, exchange_listings.type AS listing_type
 *   FROM plants
 *   JOIN plant_species ON plant_species.id = plants.species_id
 *   JOIN users ON users.id = plants.user_id
 *   LEFT JOIN exchange_listings ON exchange_listings.plant_id = plants.id AND exchange_listings.status = 'open'
 *   WHERE plants.is_public = 1
 *     AND (plants.nickname LIKE ? OR plant_species.common_name LIKE ? OR plant_species.scientific_name LIKE ?
 *          OR users.username LIKE ?)
 *     AND plants.species_id IN (?, ?) AND plants.health_status = ? AND users.location = ?   -- only the filters in use
 *     AND exchange_listings.id IS NOT NULL                                                  -- "Available for exchange"
 */
function community_plants(array $public_plants, $term, array $filters, $skip_species = false)
{
    return array_filter($public_plants, function ($plant) use ($term, $filters, $skip_species) {
        if (!community_text_matches([$plant['nickname'], $plant['scientific_name'], $plant['common_name'], $plant['username']], $term)) {
            return false;
        }
        if ($filters['health'] !== '' && $plant['health_status'] !== $filters['health']) {
            return false;
        }
        if ($filters['location'] !== '' && $plant['location'] !== $filters['location']) {
            return false;
        }
        if (($filters['exchange'] ?? '') === '1' && !$plant['on_exchange']) {
            return false;
        }
        if (!$skip_species && $filters['species'] && !in_array($plant['species_id'], $filters['species'], true)) {
            return false;
        }
        return true;
    });
}

// ── Exchange tab ─────────────────────────────────────────

/**
 * Open listings matching the search and the Exchange filters (type, distance, species ids).
 * A listing of type 'either' (swap or free) belongs under both Swap and Free. distance_km is null for a visitor,
 * who has no town to measure from, so the distance filter lets every listing through for them.
 *
 * Stage B:
 *   SELECT exchange_listings.*, plant_species.common_name, plant_species.scientific_name,
 *          users.username, users.full_name, users.avatar, … AS rating_avg, … AS interest_count, … AS distance_km
 *   FROM exchange_listings
 *   JOIN users ON users.id = exchange_listings.user_id
 *   LEFT JOIN plant_species ON plant_species.id = exchange_listings.species_id
 *   WHERE exchange_listings.status = 'open'
 *     AND (exchange_listings.title LIKE ? OR exchange_listings.looking_for LIKE ? OR plant_species.common_name LIKE ?
 *          OR users.username LIKE ?)
 *     AND exchange_listings.type IN (?, 'either') AND distance_km <= ? AND exchange_listings.species_id IN (?, ?)
 */
function community_listings(array $listings, $term, array $filters, $skip_species = false)
{
    return array_filter($listings, function ($listing) use ($term, $filters, $skip_species) {
        if ($listing['status'] !== 'open') {
            return false;   // exchanged and closed listings are never shown (p17, UX notes)
        }
        if (!community_text_matches([$listing['title'], $listing['looking_for'], $listing['common_name'],
                                     $listing['scientific_name'], $listing['username']], $term)) {
            return false;
        }
        if ($filters['type'] !== '' && !in_array($listing['type'], [$filters['type'], 'either'], true)) {
            return false;
        }
        if ($filters['distance'] !== 'any' && $listing['distance_km'] !== null && $listing['distance_km'] > (int) $filters['distance']) {
            return false;
        }
        if (!$skip_species && $filters['species'] && !in_array($listing['species_id'], $filters['species'], true)) {
            return false;
        }
        return true;
    });
}

// ── People tab ───────────────────────────────────────────

/**
 * Members matching the search and the People filters (location, minimum rating), each with match_count: how many of
 * $found_plants (the public plants matching the search) they own. A member matches when the word is in their
 * username, name or town, or when they grow a matching plant (p16, element 6). An @ search looks at names only.
 *
 * Stage B:
 *   SELECT users.id, users.username, users.full_name, users.avatar, users.location, … AS plant_count,
 *          … AS rating_avg, … AS rating_count, COUNT(matching.id) AS match_count
 *   FROM users
 *   LEFT JOIN plants AS matching ON matching.user_id = users.id AND matching.is_public = 1 AND (matching.nickname LIKE ? …)
 *   WHERE users.location = ? AND rating_avg >= ?
 *   GROUP BY users.id
 *   HAVING users.username LIKE ? OR users.full_name LIKE ? OR users.location LIKE ? OR match_count > 0
 */
function community_people(array $users, array $found_plants, $term, $is_member_search, array $filters)
{
    $people = [];
    foreach ($users as $member) {
        if ($filters['location'] !== '' && $member['location'] !== $filters['location']) {
            continue;
        }
        if ($filters['rating'] !== '' && ($member['rating_avg'] ?? 0) < (float) $filters['rating']) {
            continue;
        }

        // match_count stays 0 without a search word (every plant would "match") and for an @ search (it is about names)
        $match_count = ($term === '' || $is_member_search)
            ? 0
            : count(array_filter($found_plants, fn ($plant) => $plant['user_id'] === $member['id']));
        $name_texts  = [$member['username'], $member['full_name']];
        if (!$is_member_search) {
            $name_texts[] = $member['location'];
        }

        if (community_text_matches($name_texts, $term) || $match_count > 0) {
            $people[] = $member + ['match_count' => $match_count];
        }
    }

    return $people;
}

// ── Shared by the Plants and Exchange sidebars ───────────

/**
 * The species list of a sidebar: each species in $rows with how many rows it has, the most common first.
 * $rows are the results with every filter except species, so ticking one species does not set the others to 0.
 * A ticked species stays in the list even when nothing matches it any more, so it can be unticked.
 * Stage B: SELECT species_id, COUNT(*) … GROUP BY species_id with the same WHERE minus the species condition.
 */
function community_species_list(array $rows, array $selected, array $species_table)
{
    $counts = [];
    foreach ($rows as $row) {
        if ($row['species_id'] !== null) {
            $counts[$row['species_id']] = ($counts[$row['species_id']] ?? 0) + 1;
        }
    }
    foreach ($selected as $species_id) {
        $counts[$species_id] ??= 0;
    }

    $list = [];
    foreach ($counts as $species_id => $count) {
        $list[] = [
            'id'      => $species_id,
            'name'    => $species_table[$species_id]['common_name'],
            'count'   => $count,
            'checked' => in_array($species_id, $selected, true),
        ];
    }
    usort($list, fn ($a, $b) => [$b['count'], $a['name']] <=> [$a['count'], $b['name']]);

    return $list;
}
