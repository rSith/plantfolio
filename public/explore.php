<?php
// Old address of Explore (P09). Explore and Exchange are now one page, Community (community.php, COM-01).
// 301 "moved for good": browsers and search engines remember the new address. Every part of the old address is
// kept (q, species[], health, sort …), so bookmarks and shared links still open the same search. Explore's People
// tab keeps its tab, an "Available for exchange" search opens the Exchange tab, everything else the Plants tab.

$params = $_GET;
if (($params['exchange'] ?? '') === '1') {
    $params['tab'] = 'exchange';   // Explore's "Available for exchange" switch: those plants are on the Exchange tab now
    unset($params['exchange'], $params['health']);
} else {
    $params['tab'] = (($params['tab'] ?? '') === 'people') ? 'people' : 'plants';
}

// A relative address: the browser resolves it against this page, so it works on XAMPP and on the Vercel preview
header('Location: community.php?' . http_build_query($params), true, 301);
exit;
