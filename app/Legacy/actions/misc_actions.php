<?php

// Extracted from ajax_handler.php — action group: misc
// Variables in scope: $pdo, $action, $archivedExcludeQuery, $archivedExcludeUsersQuery

if ($action === 'search_address') {
    $q = $_REQUEST['q'] ?? '';
    if (strlen($q) < 3) {
        jsonResponse(['ok' => true, 'data' => []]);
    }
    $results = searchAddressGoogle($q);
    jsonResponse(['ok' => true, 'data' => $results]);
}

// Admin manual holidays CRUD

if ($action === 'reverse_geocode' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $lat = $_POST['lat'] ?? null;
    $lng = $_POST['lng'] ?? null;

    if (! $lat || ! $lng) {
        jsonResponse(['ok' => false, 'message' => 'Coordinates required'], 400);

        return;
    }

    $address = reverseGeocodeAddress((float) $lat, (float) $lng);

    if ($address) {
        jsonResponse([
            'ok' => true,
            'data' => [
                'display_name' => $address,
                'address' => [
                    'full' => $address,
                ],
            ],
        ]);
    } else {
        error_log("reverse_geocode failed for lat=$lat, lng=$lng");
        jsonResponse(['error' => 'Geocoding failed'], 500);
    }
}

if ($action === 'get_startups') {
    $stmt = $pdo->query("SELECT DISTINCT startup FROM users WHERE role='pegawai' AND startup IS NOT NULL AND startup != '' ORDER BY startup");
    $rows = $stmt->fetchAll();
    jsonResponse(['ok' => true, 'data' => array_column($rows, 'startup')]);
}
