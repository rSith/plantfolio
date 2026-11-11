<?php
// Old address of the Exchange Marketplace (P10). It is now the Exchange tab of Community (community.php, COM-07).
// 301 "moved for good": browsers and search engines remember the new address. Every part of the old address is
// kept (q, type, distance, species, sort …), so bookmarks and shared links still open the same search.

$params        = $_GET;
$params['tab'] = 'exchange';
// The old distance list had "any"; the slider's far end (100) means the same now
if (($params['distance'] ?? '') === 'any') {
    $params['distance'] = '100';
}

// A relative address: the browser resolves it against this page, so it works on XAMPP and on the Vercel preview
header('Location: community.php?' . http_build_query($params), true, 301);
exit;
