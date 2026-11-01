<?php
// Vercel front controller: runs the page the address asks for (Vercel preview of the stage F UI).
// The Hobby plan allows 12 serverless functions per deployment and we have 20 pages, so vercel.json sends every
// page address here as one function, and this file runs the matching page in public/ the way Apache would.
// Locally (XAMPP) it does nothing: Apache runs each page directly.

$vercel_page = $_GET['vercel_page'] ?? '';
unset($_GET['vercel_page']);   // the page should see only its own query, e.g. ?id=42
if ($vercel_page === '') {
    $vercel_page = 'index';
}

// Only a plain file name (a-z, 0-9, dashes) of a page that exists in public/, so ../config/… can never be run
if (!getenv('VERCEL') || !is_string($vercel_page) || $vercel_page === 'vercel-router'
    || !preg_match('/^[a-z0-9-]+\z/', $vercel_page) || !is_file(__DIR__ . '/' . $vercel_page . '.php')) {
    http_response_code(404);
    exit('Not found');
}

// current_page() reads SCRIPT_NAME to light the right navigation link, so give it the page's own name
$_SERVER['SCRIPT_NAME'] = '/' . $vercel_page . '.php';
require __DIR__ . '/' . $vercel_page . '.php';
