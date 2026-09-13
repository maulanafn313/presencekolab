<?php

// Extracted from core.php — do not edit the original functions here without updating core.php require.

function isWfoByApi(PDO $pdo, ?string $publicIp = null): bool
{
    // PERFORMANCE: Cache WFO check to avoid slow API calls
    $cacheKey = 'wfo_check_'.md5($publicIp ?? 'auto');
    if (isset($_SESSION[$cacheKey]) && $_SESSION[$cacheKey]['time'] > time() - 300) {
        return $_SESSION[$cacheKey]['result'];
    }

    $result = _isWfoByApiInternal($pdo, $publicIp);

    $_SESSION[$cacheKey] = ['time' => time(), 'result' => $result];

    return $result;
}

function _isWfoByApiInternal(PDO $pdo, ?string $publicIp = null): bool
{
    $provider = strtolower(trim(getSetting($pdo, 'wfo_api_provider', 'ipinfo')));
    $token = trim(getSetting($pdo, 'wfo_api_token', ''));
    $orgKeywords = array_filter(array_map('trim', explode(',', getSetting($pdo, 'wfo_api_org_keywords', 'Telkom University'))));
    $asnList = array_filter(array_map('trim', explode(',', getSetting($pdo, 'wfo_api_asn_list', ''))));
    $cidrList = array_filter(array_map('trim', explode(',', getSetting($pdo, 'wfo_api_cidr_list', ''))));

    // Determine client public IP if not provided
    if (! $publicIp) {
        $publicIp = $_POST['public_ip'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        if ($publicIp && strpos($publicIp, ',') !== false) {
            $parts = explode(',', $publicIp);
            $publicIp = trim($parts[0]);
        }
    }
    if (! $publicIp || ! filter_var($publicIp, FILTER_VALIDATE_IP)) {
        return false; // cannot determine
    }

    // CRITICAL FIX: Check private IP range first (for laptops on Telkom University network)
    // This is important because laptops often get private IP (10.x.x.x) which cannot be validated via external API
    if (isTelkomUniversityPrivateIp($publicIp)) {
        error_log("WFO Private IP Check - IP: $publicIp, Result: VALID (Telkom University private IP range)");

        return true; // Private IP in Telkom University range - valid WFO
    }

    // For public IPs, check via external API
    // Skip API check for private IPs (they won't work with external APIs anyway)
    $isPrivate = filter_var($publicIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    if ($isPrivate) {
        // Private IP but not in Telkom University range
        return false;
    }

    // Check public IP via external API
    $info = fetchPublicIpInfo($publicIp, $provider, $token);
    $org = strtolower($info['org'] ?? '');
    $asn = strtoupper($info['asn'] ?? '');

    // Match org keywords
    foreach ($orgKeywords as $kw) {
        if ($kw !== '' && str_contains($org, strtolower($kw))) {
            return true;
        }
    }

    // Match ASN
    foreach ($asnList as $a) {
        if ($a !== '' && strtoupper(trim($a)) === $asn) {
            return true;
        }
    }

    // Match CIDR ranges
    foreach ($cidrList as $cidr) {
        if ($cidr !== '' && ipInCidr($publicIp, $cidr)) {
            return true;
        }
    }

    return false;
}
