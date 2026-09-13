<?php

// Extracted from core.php — do not edit the original functions here without updating core.php require.

/**
 * Get accurate network time via HTTP Date header from a reliable server (e.g. Google)
 * to prevent system clock tampering on the local server.
 * Fallback to system clock if offline or request fails.
 */
function getNetworkTime(): DateTime
{
    $timezone = new DateTimeZone('Asia/Jakarta');
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://www.google.com');
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response && $httpCode >= 200 && $httpCode < 400) {
            if (preg_match('/^[Dd]ate:\s*(.*?)$/m', $response, $matches)) {
                $netTimeStr = trim($matches[1]);
                $netTime = new DateTime($netTimeStr);
                $netTime->setTimezone($timezone);

                return $netTime;
            }
        }
    } catch (Exception $e) {
        // Fallback to local server time
    }

    return new DateTime('now', $timezone);
}

/**
 * Detect WFO by external IP information API or private IP range
 * Returns true if IP belongs to allowed org/ASN/CIDR list or Telkom University private IP range
 */
