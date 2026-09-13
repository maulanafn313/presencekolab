<?php

// Extracted from core.php — do not edit the original functions here without updating core.php require.

/**
 * Robust HTTP request helper that tries cURL first and file_get_contents as fallback.
 */
function httpRequest(string $url, array $headers = [], int $timeout = 10): ?string
{
    // Try cURL
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        if (! empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $resp) {
            return $resp;
        }
    }

    // Try file_get_contents as fallback
    if (ini_get('allow_url_fopen')) {
        $headerStr = '';
        foreach ($headers as $h) {
            $headerStr .= $h."\r\n";
        }

        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => $headerStr ?: "User-Agent: AbsenApp/1.0\r\n",
                'timeout' => $timeout,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ];
        $context = stream_context_create($opts);
        $resp = @file_get_contents($url, false, $context);
        if ($resp) {
            return $resp;
        }
    }

    return null;
}

/**
 * Search for addresses using Google Geocoding API.
 * Returns an array of results with display_name, lat, and lon.
 */
function searchAddressGoogle(string $query): array
{
    $apiKey = 'AIzaSyCTdOHXg5hSu_2fneyBP9mItCLyG5VQ-x0';
    $url = 'https://maps.googleapis.com/maps/api/geocode/json?address='.urlencode($query)."&key={$apiKey}&language=id&region=id";

    $resp = httpRequest($url);

    if (! $resp) {
        // Fallback to Nominatim if Google fails
        return searchAddressNominatim($query);
    }

    $data = json_decode($resp, true);
    if (! isset($data['status']) || ($data['status'] !== 'OK' && $data['status'] !== 'ZERO_RESULTS') || empty($data['results'])) {
        return searchAddressNominatim($query);
    }

    $results = [];
    foreach ($data['results'] as $res) {
        $results[] = [
            'display_name' => $res['formatted_address'],
            'lat' => $res['geometry']['location']['lat'],
            'lon' => $res['geometry']['location']['lng'],
            'place_id' => $res['place_id'],
            'type' => 'google',
        ];
    }

    return $results;
}

/**
 * Search for addresses using Nominatim (fallback).
 */
function searchAddressNominatim(string $query): array
{
    $url = 'https://nominatim.openstreetmap.org/search?format=json&limit=5&addressdetails=1&countrycodes=id&q='.urlencode($query);
    $headers = ['User-Agent: AbsenApp/1.0 (XAMPP PHP)'];

    $resp = httpRequest($url, $headers, 5);

    if (! $resp) {
        return [];
    }

    $data = json_decode($resp, true);
    if (! is_array($data)) {
        return [];
    }

    $results = [];
    foreach ($data as $res) {
        $results[] = [
            'display_name' => $res['display_name'],
            'lat' => $res['lat'],
            'lon' => $res['lon'],
            'place_id' => $res['place_id'],
            'type' => 'nominatim',
        ];
    }

    return $results;
}

/**
 * Geocode a free-form address string to [lat, lng] using OpenStreetMap Nominatim.
 * Returns ['lat' => float, 'lng' => float] or null on failure.
 */
function geocodeAddress(string $address): ?array
{
    // Try Google first for better accuracy
    $googleResults = searchAddressGoogle($address);
    if (! empty($googleResults)) {
        return ['lat' => (float) $googleResults[0]['lat'], 'lng' => (float) $googleResults[0]['lon']];
    }

    $url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&addressdetails=0&q='.urlencode($address);
    $headers = ['User-Agent: AbsenApp/1.0 (XAMPP PHP)'];

    $resp = httpRequest($url, $headers, 4);
    if (! $resp) {
        return null;
    }

    $data = json_decode($resp, true);
    if (! is_array($data) || empty($data) || ! isset($data[0]['lat'], $data[0]['lon'])) {
        return null;
    }

    return ['lat' => (float) $data[0]['lat'], 'lng' => (float) $data[0]['lon']];
}

/**
 * Reverse geocode coordinates to address using MULTIPLE providers for maximum accuracy.
 * ENHANCED VERSION with RT/RW extraction and detailed Indonesian address parsing.
 * Returns complete address with street name, number, RT/RW, kelurahan, postal code.
 */
function reverseGeocodeAddress(float $lat, float $lng): ?string
{
    // TIER 1: Try Google Maps API (MOST ACCURATE - PRIMARY)
    $googleAddress = reverseGeocodeGoogle($lat, $lng);
    if ($googleAddress && ! isGenericAddress($googleAddress)) {
        error_log("Geocoding SUCCESS: Google Maps - $googleAddress");

        return $googleAddress;
    }

    // TIER 2: Try with zoom 18 (maximum detail)
    $detailedAddress = reverseGeocodeNominatim($lat, $lng, 18);
    if ($detailedAddress && ! isGenericAddress($detailedAddress)) {
        error_log("Geocoding SUCCESS: OSM Zoom 18 - $detailedAddress");

        return $detailedAddress;
    }

    // TIER 3: Try with zoom 17 (slightly broader, might have more data)
    $mediumAddress = reverseGeocodeNominatim($lat, $lng, 17);
    if ($mediumAddress && ! isGenericAddress($mediumAddress)) {
        error_log("Geocoding SUCCESS: OSM Zoom 17 - $mediumAddress");

        return $mediumAddress;
    }

    // TIER 4: Fallback to coordinates
    error_log("All geocoding methods failed for lat=$lat, lng=$lng, using coordinates fallback");

    return 'Koordinat: '.round($lat, 6).', '.round($lng, 6);
}

/**
 * Google Maps Geocoding API - PRIMARY PROVIDER (Most Accurate for Indonesian Addresses)
 */
function reverseGeocodeGoogle(float $lat, float $lng): ?string
{
    $apiKey = 'AIzaSyCTdOHXg5hSu_2fneyBP9mItCLyG5VQ-x0';
    $url = "https://maps.googleapis.com/maps/api/geocode/json?latlng={$lat},{$lng}&key={$apiKey}&language=id&result_type=street_address|route|sublocality|premise";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode !== 200 || ! $resp) {
        error_log("Google Geocoding API request failed: HTTP $httpCode, Error: $curlError");

        return null;
    }

    $data = json_decode($resp, true);

    if (! isset($data['status']) || $data['status'] !== 'OK') {
        $errorMsg = $data['error_message'] ?? $data['status'] ?? 'UNKNOWN';
        error_log("Google Geocoding API error: $errorMsg");

        return null;
    }

    if (empty($data['results'])) {
        error_log('Google Geocoding API: No results');

        return null;
    }

    // Get first result (most accurate)
    $result = $data['results'][0];
    $addressComponents = $result['address_components'] ?? [];
    $formattedAddress = $result['formatted_address'] ?? '';

    // Parse for Indonesian address format
    $houseNumber = '';
    $street = '';
    $rt = '';
    $rw = '';
    $kelurahan = '';
    $kecamatan = '';
    $city = '';
    $province = '';
    $postalCode = '';

    foreach ($addressComponents as $component) {
        $types = $component['types'];
        $longName = $component['long_name'];

        if (in_array('street_number', $types)) {
            $houseNumber = $longName;
        } elseif (in_array('route', $types)) {
            $street = $longName;
        } elseif (in_array('premise', $types) || in_array('establishment', $types)) {
            // Building name
            if (empty($houseNumber)) {
                $houseNumber = $longName;
            }
        } elseif (in_array('sublocality_level_4', $types) || in_array('neighborhood', $types)) {
            // Check for RT/RW
            if (preg_match('/RT[\s.]*0*([0-9]{1,3})[\s\/]*RW[\s.]*0*([0-9]{1,3})/i', $longName, $matches)) {
                $rt = str_pad($matches[1], 3, '0', STR_PAD_LEFT);
                $rw = str_pad($matches[2], 3, '0', STR_PAD_LEFT);
            } elseif (empty($kelurahan)) {
                $kelurahan = $longName;
            }
        } elseif (in_array('sublocality_level_3', $types) || in_array('administrative_area_level_4', $types)) {
            $kelurahan = $longName;
        } elseif (in_array('sublocality_level_2', $types) || in_array('administrative_area_level_3', $types)) {
            $kecamatan = $longName;
        } elseif (in_array('administrative_area_level_2', $types) || in_array('locality', $types)) {
            $city = $longName;
        } elseif (in_array('administrative_area_level_1', $types)) {
            $province = $longName;
        } elseif (in_array('postal_code', $types)) {
            $postalCode = $longName;
        }
    }

    // Build Indonesian address
    $parts = [];

    // Street with number
    if ($houseNumber && $street) {
        $parts[] = "No. $houseNumber, Jl. $street";
    } elseif ($street) {
        $parts[] = "Jl. $street";
    } elseif ($houseNumber) {
        $parts[] = $houseNumber;
    }

    // RT/RW
    if ($rt && $rw) {
        $parts[] = "RT $rt/RW $rw";
    }

    // Kelurahan
    if ($kelurahan) {
        $parts[] = $kelurahan;
    }

    // Kecamatan
    if ($kecamatan) {
        $parts[] = $kecamatan;
    }

    // City
    if ($city) {
        $parts[] = $city;
    }

    // Province
    if ($province) {
        $parts[] = $province;
    }

    // Postal code
    if ($postalCode) {
        $parts[] = $postalCode;
    }

    // Return detailed address if we have enough components
    if (! empty($parts) && count($parts) >= 3) {
        return implode(', ', $parts);
    }

    // Fallback to Google's formatted address
    if ($formattedAddress) {
        $cleanAddress = preg_replace('/,\s*Indonesia\s*$/', '', $formattedAddress);

        return $cleanAddress;
    }

    return null;
}

/**
 * Helper: Check if address is too generic (just city + postal)
 */
function isGenericAddress(string $address): bool
{
    // If address only has 1-2 components (just city and postal code), it's too generic
    $parts = explode(', ', $address);

    return count($parts) <= 2;
}

/**
 * Core reverse geocoding using Nominatim with specified zoom
 */
function reverseGeocodeNominatim(float $lat, float $lng, int $zoom): ?string
{
    $url = 'https://nominatim.openstreetmap.org/reverse?format=json&lat='.$lat.'&lon='.$lng.'&addressdetails=1&accept-language=id&zoom='.$zoom.'&extratags=1&namedetails=1';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // INCREASED from 1 to 5 seconds for better accuracy
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); // Connection timeout 3 seconds
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: AbsenApp/1.0 (XAMPP PHP)',
    ]);

    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || ! $resp) {
        // Fallback to coordinates if geocoding fails
        error_log("Reverse geocoding failed for lat=$lat, lng=$lng");

        return 'Koordinat: '.round($lat, 6).', '.round($lng, 6);
    }

    $data = json_decode($resp, true);
    if (! is_array($data) || ! isset($data['address'])) {
        return 'Koordinat: '.round($lat, 6).', '.round($lng, 6);
    }

    $address = $data['address'];
    $displayName = $data['display_name'] ?? '';

    // ENHANCED: Extract RT/RW from various address components
    $rt = '';
    $rw = '';

    // Pattern untuk mencari RT/RW dalam format: "RT 001/RW 002", "RT.01 RW.02", "RT 1 RW 2", etc
    $rtRwPattern = '/RT[\s.]*0*([0-9]{1,3})[\s\/]*RW[\s.]*0*([0-9]{1,3})/i';

    // Check dalam berbagai field yang mungkin mengandung RT/RW
    $searchFields = ['suburb', 'neighbourhood', 'hamlet', 'quarter', 'city_district', 'residential'];
    foreach ($searchFields as $field) {
        if (isset($address[$field]) && $address[$field]) {
            if (preg_match($rtRwPattern, $address[$field], $matches)) {
                $rt = str_pad($matches[1], 3, '0', STR_PAD_LEFT); // Format: 001, 002, etc
                $rw = str_pad($matches[2], 3, '0', STR_PAD_LEFT);
                break;
            }
        }
    }

    // Build DETAILED address from components with proper Indonesian order
    $parts = [];

    // 1. Building name atau house name (paling spesifik)
    if (isset($address['building']) && $address['building']) {
        $parts[] = $address['building'];
    } elseif (isset($address['house_name']) && $address['house_name']) {
        $parts[] = $address['house_name'];
    } elseif (isset($address['amenity']) && $address['amenity']) {
        $parts[] = $address['amenity'];
    }

    // 2. Road/Street dengan house number jika ada
    $roadParts = [];
    if (isset($address['house_number']) && $address['house_number']) {
        $roadParts[] = 'No. '.$address['house_number'];
    }
    if (isset($address['road']) && $address['road']) {
        $roadParts[] = 'Jl. '.$address['road'];
    } elseif (isset($address['pedestrian']) && $address['pedestrian']) {
        $roadParts[] = 'Jl. '.$address['pedestrian'];
    } elseif (isset($address['footway']) && $address['footway']) {
        $roadParts[] = 'Jl. '.$address['footway'];
    } elseif (isset($address['path']) && $address['path']) {
        $roadParts[] = $address['path'];
    }
    if (! empty($roadParts)) {
        $parts[] = implode(' ', $roadParts);
    }

    // 3. RT/RW jika ditemukan
    if ($rt && $rw) {
        $parts[] = "RT $rt/RW $rw";
    }

    // 4. Kelurahan/Desa (suburb/neighbourhood)
    if (isset($address['suburb']) && $address['suburb']) {
        // Skip jika suburb sama dengan RT/RW pattern (sudah diambil di atas)
        if (! preg_match($rtRwPattern, $address['suburb'])) {
            $parts[] = $address['suburb'];
        }
    } elseif (isset($address['neighbourhood']) && $address['neighbourhood']) {
        if (! preg_match($rtRwPattern, $address['neighbourhood'])) {
            $parts[] = $address['neighbourhood'];
        }
    } elseif (isset($address['hamlet']) && $address['hamlet']) {
        $parts[] = $address['hamlet'];
    } elseif (isset($address['village']) && $address['village']) {
        $parts[] = $address['village'];
    }

    // 5. Kecamatan (city_district)
    if (isset($address['city_district']) && $address['city_district']) {
        $parts[] = $address['city_district'];
    } elseif (isset($address['municipality']) && $address['municipality']) {
        $parts[] = $address['municipality'];
    }

    // 6. Kota/Kabupaten
    if (isset($address['city']) && $address['city']) {
        $parts[] = $address['city'];
    } elseif (isset($address['town']) && $address['town']) {
        $parts[] = $address['town'];
    } elseif (isset($address['county']) && $address['county']) {
        $parts[] = $address['county'];
    }

    // 7. Provinsi
    if (isset($address['state']) && $address['state']) {
        $parts[] = $address['state'];
    }

    // 8. Postal code (PENTING untuk alamat lengkap)
    if (isset($address['postcode']) && $address['postcode']) {
        $parts[] = $address['postcode'];
    }

    // If we have good parts, join them
    if (! empty($parts)) {
        $detailedAddress = implode(', ', $parts);

        // Log untuk debugging
        error_log("Reverse geocoding success: $detailedAddress (RT: $rt, RW: $rw)");

        return $detailedAddress;
    }

    // Fallback to display_name if no parts extracted
    if ($displayName) {
        // Clean up the display name
        $cleanName = preg_replace('/,\s*Indonesia$/', '', $displayName);

        // Try to append postal code if available
        if (isset($address['postcode']) && $address['postcode']) {
            if (strpos($cleanName, $address['postcode']) === false) {
                $cleanName .= ', '.$address['postcode'];
            }
        }

        error_log("Reverse geocoding fallback to display_name: $cleanName");

        return $cleanName;
    }

    // Final fallback to coordinates
    error_log('Reverse geocoding no address found, using coordinates');

    return 'Koordinat: '.round($lat, 6).', '.round($lng, 6);
}

/** Check if IP within CIDR */
function ipInCidr(string $ip, string $cidr): bool
{
    if (! str_contains($cidr, '/')) {
        return false;
    }
    [$subnet, $mask] = explode('/', $cidr, 2);
    $mask = (int) $mask;
    if (! filter_var($ip, FILTER_VALIDATE_IP) || ! filter_var($subnet, FILTER_VALIDATE_IP)) {
        return false;
    }
    $ipLong = ip2long($ip);
    $subnetLong = ip2long($subnet);
    $maskLong = -1 << (32 - $mask);
    $subnetBase = $subnetLong & $maskLong;

    return ($ipLong & $maskLong) === $subnetBase;
}

/**
 * Fetch public IP info from provider
 */
function fetchPublicIpInfo(string $ip, string $provider, string $token = ''): array
{
    $url = '';
    $headers = ['User-Agent: AbsenApp/1.0 (XAMPP PHP)'];
    if ($provider === 'ipinfo') {
        $url = 'https://ipinfo.io/'.urlencode($ip).'/json'.($token ? ('?token='.urlencode($token)) : '');
    } elseif ($provider === 'ipapi') {
        $url = 'https://ipapi.co/'.urlencode($ip).'/json/';
        if ($token) {
            $headers[] = 'Authorization: Bearer '.$token;
        }
    } else { // ip-api
        $url = 'http://ip-api.com/json/'.urlencode($ip).'?fields=status,message,org,as,asname,query';
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3); // Reduced from 5 to 3 seconds for faster response
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2); // Connection timeout 2 seconds
    if (! empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200 || ! $resp) {
        return [];
    }
    $data = json_decode($resp, true);
    if (! is_array($data)) {
        return [];
    }

    // Normalize fields
    $org = '';
    $asn = '';
    if ($provider === 'ipinfo') {
        $org = $data['company']['name'] ?? ($data['org'] ?? '');
        $asn = isset($data['org']) ? strtoupper(strtok($data['org'], ' ')) : '';
    } elseif ($provider === 'ipapi') {
        $org = $data['org'] ?? ($data['company'] ?? '');
        $asn = strtoupper($data['asn'] ?? ($data['as'] ?? ''));
    } else { // ip-api
        $org = $data['org'] ?? ($data['asname'] ?? '');
        $asn = strtoupper($data['as'] ?? '');
        if ($asn && ! str_starts_with($asn, 'AS')) {
            $asn = strtoupper(strtok($asn, ' '));
        }
    }

    return [
        'org' => (string) $org,
        'asn' => (string) $asn,
        'raw' => $data,
    ];
}

/**
 * Check if IP is in Telkom University private IP range
 * Telkom University uses private IP ranges: 10.x.x.x
 */
function isTelkomUniversityPrivateIp(string $ip): bool
{
    if (! filter_var($ip, FILTER_VALIDATE_IP)) {
        return false;
    }

    // Check if it's a private IP (10.x.x.x, 172.16-31.x.x, 192.168.x.x)
    $isPrivate = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;

    if (! $isPrivate) {
        return false; // Not a private IP
    }

    // Check if it's in Telkom University private IP range (10.x.x.x)
    // Based on screenshots: 10.60.43.33 (TelU-Connect) and 10.30.114.48 (TelU-Guest)
    // Telkom University uses 10.x.x.x range
    if (strpos($ip, '10.') === 0) {
        return true; // IP starts with 10. - likely Telkom University network
    }

    return false;
}
