<?php
// AI care guide: the five sections, and which species a plant's guide is for (new feature, 2026-11-11, UI half).
// Stage F uses only these plain functions. Stage B adds reading and saving the care_guides cache here, and the
// call to the local LLM goes in includes/llm-client.php.

/**
 * The five sections in the order the card shows them: key in guide_json → label and Lucide icon.
 * The model is asked for exactly these keys, and stage B saves a guide only when all five are present.
 */
function care_guide_sections()
{
    return [
        'light'             => ['label' => 'Light',                 'icon' => 'sun'],
        'soil_fertilizer'   => ['label' => 'Soil and fertiliser',   'icon' => 'shovel'],
        'watering'          => ['label' => 'Watering',              'icon' => 'droplet'],
        'disease_control'   => ['label' => 'Disease control',       'icon' => 'shield'],
        'structure_pruning' => ['label' => 'Structure and pruning', 'icon' => 'scissors'],
    ];
}

/**
 * The species name a plant's guide is written for, or null when the plant has none.
 * A species from our list gives its scientific name, so "Tomato" and "Solanum lycopersicum" share one guide;
 * a name typed by the member (custom_species) is used as it is.
 * Later the ML plant identifier may supply the name: only this function needs to change.
 */
function care_guide_species($plant)
{
    if ($plant['species_id'] !== null) {
        return $plant['scientific_name'];
    }
    $custom = trim($plant['custom_species'] ?? '');
    return $custom === '' ? null : $custom;
}

/**
 * The cache key for a species name: trimmed, lower case, single spaces.
 * "Tomato " and "tomato" give the same key, so they share one row of care_guides.
 */
function normalise_species($name)
{
    return mb_strtolower(preg_replace('/\s+/u', ' ', trim($name)));
}
