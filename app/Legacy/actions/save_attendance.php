<?php

// Existing attendance decisions, shared by Laravel API and the legacy adapter.
$nim = trim($_POST['nim'] ?? '');
$mode = $_POST['mode'] ?? ''; // masuk/pulang
$ekspresi = $_POST['ekspresi'] ?? null;
$landmark = $_POST['landmark'] ?? $_POST['landmark_masuk'] ?? $_POST['landmark_pulang'] ?? $_POST['landmarks'] ?? null; // JSON 68 titik landmark
$screenshot = $_POST['foto_base64'] ?? $_POST['screenshot'] ?? null; // Legacy support for screenshot

// Optimize: Save screenshot to file instead of DB if provided
if ($screenshot && strpos($screenshot, 'data:image/') === 0) {
    $screenshot = saveBase64Image($screenshot, 'attendance/'.date('Y-m-d'));
}

// Landmark opsional (bisa null jika kamera tidak mendeteksi)
// Tidak lagi wajib screenshot

if (! $nim || ! in_array($mode, ['masuk', 'pulang'], true)) {
    jsonResponse(['ok' => false, 'message' => 'Bad request: NIM atau mode tidak valid'], 400);
}

error_log("save_attendance: NIM=$nim, Mode=$mode, LandmarkPresent=".(isset($_POST['landmarks']) ? 'Yes' : 'No').', ScreenshotPresent='.(isset($_POST['screenshot']) ? 'Yes' : 'No'));
// ULTRA-FAST: Optimized database query with minimal fields and no error logging
try {
    $stmt = $pdo->prepare('SELECT id, nama FROM users WHERE nim=:nim LIMIT 1');
    $stmt->execute([':nim' => $nim]);
    $u = $stmt->fetch();
    if (! $u) {
        jsonResponse(['ok' => false, 'message' => 'NIM tidak ditemukan'], 404);
    }
} catch (PDOException $e) {
    jsonResponse(['ok' => false, 'message' => 'Database error'], 500);
}

$now = $submissionTime;
$jamSekarang = $now->format('H:i:s'); // Tetap simpan dengan detik untuk database
$iso = $now->format('Y-m-d H:i:s');
$today = $now->format('Y-m-d');

// Ultra-fast processing - minimal logging
// error_log("Current date: $today, User ID: " . $u['id']);
// error_log("User data: " . print_r($u, true));
// error_log("Mode: $mode, Expression: $ekspresi");
$currentHour = (int) $now->format('H');
$currentMinute = (int) $now->format('i');
$todayStart = $today.' 00:00:00';
$todayEnd = $today.' 23:59:59';

if ($mode === 'masuk') {
    // Check if within check-in time window using settings
    $minCheckinSetting = getSetting($pdo, 'min_checkin_hour', '04:00');
    if (strpos($minCheckinSetting, ':') === false) {
        $minCheckinSetting = sprintf('%02d:00', (int) $minCheckinSetting);
    }
    $currentTimeString = sprintf('%02d:%02d', $currentHour, $currentMinute);
    
    if ($currentTimeString < $minCheckinSetting) {
        $statusText = "Presensi masuk baru dibuka pada jam {$minCheckinSetting}.";
        jsonResponse(['ok' => false, 'message' => $statusText, 'statusClass' => 'bg-red-100 text-red-700'], 400);
    }

    // Ultra-fast query - check for any attendance record today (including izin/sakit)
    $todayCheck = $pdo->prepare('
        SELECT id, jam_masuk_iso, jam_pulang_iso, ket FROM attendance 
        WHERE user_id = :uid 
        AND DATE(jam_masuk_iso) = :today 
        AND jam_masuk_iso IS NOT NULL
        ORDER BY jam_masuk_iso DESC 
        LIMIT 1
    ');
    $todayCheck->execute([
        ':uid' => $u['id'],
        ':today' => $today,
    ]);
    $todayRow = $todayCheck->fetch();

    // Ultra-fast processing - minimal logging
    // if ($todayRow) {
    //     error_log("Found existing attendance record: ID=" . $todayRow['id'] . ", jam_masuk_iso=" . $todayRow['jam_masuk_iso'] . ", jam_pulang_iso=" . $todayRow['jam_pulang_iso']);
    // } else {
    //     error_log("No existing attendance record found for user " . $u['id'] . " on date " . $today);
    // }

    if (! $todayRow) {
        // FIRST: Check if it's a working day
        $isWorkingDay = isEmployeeWorkingDay($pdo, $u['id'], $today);
        $dayOfWeek = (int) $now->format('N'); // 1=Monday, 7=Sunday
        $isWeekend = $dayOfWeek >= 6; // Saturday or Sunday
        $isManualHolidayDate = isManualHoliday($pdo, $today);

        // If NOT a working day (weekend or holiday), treat as overtime
        if (! $isWorkingDay || $isWeekend || $isManualHolidayDate) {
            // This is overtime - require overtime reason and location
            $lat = isset($_POST['lat']) ? (float) $_POST['lat'] : null;
            $lng = isset($_POST['lng']) ? (float) $_POST['lng'] : null;
            $lokasi = $_POST['lokasi'] ?? null;
            $alasanOvertime = $_POST['overtime_reason'] ?? $_POST['alasan_overtime'] ?? null;
            $lokasiOvertime = $_POST['overtime_location'] ?? $_POST['lokasi_overtime'] ?? null;

            // Strict validation: GPS location is mandatory
            if ($lat === null || $lng === null || $lat === 0 || $lng === 0) {
                jsonResponse(['ok' => false, 'need_overtime_reason' => true, 'message' => 'Lokasi GPS wajib untuk presensi overtime. Pastikan GPS aktif dan izin lokasi diberikan.'], 400);
            }

            // OPTIMIZED: Quick reverse geocoding - ensure lokasi is never empty
            if (empty($lokasi) || strpos($lokasi, 'Lokasi:') === 0) {
                if ($lat !== null && $lng !== null) {
                    // Try reverse geocoding with shorter timeout
                    $reverseGeocoded = @reverseGeocodeAddress($lat, $lng);
                    if ($reverseGeocoded && ! empty($reverseGeocoded)) {
                        $lokasi = $reverseGeocoded;
                    } else {
                        // Fallback - ensure lokasi is never empty
                        $lokasi = 'Lokasi: '.round($lat, 6).', '.round($lng, 6);
                    }
                } else {
                    // No coordinates - use default
                    $lokasi = 'Lokasi tidak tersedia';
                }
            }

            // Use lokasi as lokasi_overtime if not provided
            if (empty($lokasiOvertime)) {
                $lokasiOvertime = $lokasi;
            }

            // Require overtime reason and location
            if (! $alasanOvertime) {
                jsonResponse(['ok' => false, 'need_overtime_reason' => true, 'message' => 'Presensi di hari libur/weekend dianggap overtime. Harap isi alasan dan lokasi overtime.'], 400);
            }

            if (! $lokasiOvertime) {
                jsonResponse(['ok' => false, 'need_overtime_reason' => true, 'message' => 'Lokasi overtime wajib diisi.'], 400);
            }

            // Insert overtime attendance - requires admin approval
            $ins = $pdo->prepare("INSERT INTO admin_help_requests (user_id, request_type, tanggal, jam_masuk, bukti_presensi, lokasi_presensi, attendance_type, attendance_reason, status) VALUES (:uid, 'late_attendance', :today, :jam, :screenshot, :lokasi, 'overtime', :alasan, 'pending')");
            $ins->execute([
                ':uid' => $u['id'],
                ':today' => $today,
                ':jam' => $jamSekarang,
                ':screenshot' => $screenshot,
                ':lokasi' => $lokasi,
                ':alasan' => $alasanOvertime,
            ]);

            // Trigger backup setelah presensi overtime
            triggerDatabaseBackup();

            // Response for overtime
            $jamMasukFormat = substr($jamSekarang, 0, 5);
            $firstName = getFirstName($u['nama']);
            $statusText = "Selamat datang {$firstName}, presensi Overtime telah dikirim dan menunggu persetujuan Admin.";
            jsonResponse(['ok' => true, 'message' => $statusText, 'nama' => $u['nama'], 'jam' => $jamMasukFormat, 'statusClass' => 'bg-yellow-100 text-yellow-700']);

            return; // Exit early for overtime
        }

        // If it's a working day, continue with normal WFO/WFA check
        // Calculate if late using settings
        $maxOntimeSetting = getSetting($pdo, 'max_ontime_hour', '08:00');
        if (strpos($maxOntimeSetting, ':') === false) {
            $maxOntimeSetting = sprintf('%02d:00', (int) $maxOntimeSetting);
        }
        $currentTimeString = sprintf('%02d:%02d', $currentHour, $currentMinute);
        
        $isLate = false;
        $lateMessage = '';
        $status = 'ontime';

        if ($currentTimeString > $maxOntimeSetting) {
            $isLate = true;
            $status = 'terlambat';

            // Calculate delay time
            $deadline = new DateTime($today.' '.$maxOntimeSetting.':00', new DateTimeZone('Asia/Jakarta'));
            $delay = $now->getTimestamp() - $deadline->getTimestamp();

            if ($delay >= 3600) { // More than 1 hour
                $hours = floor($delay / 3600);
                $minutes = floor(($delay % 3600) / 60);
                $lateMessage = " (Telat {$hours} jam {$minutes} menit)";
            } elseif ($delay >= 60) { // More than 1 minute
                $minutes = floor($delay / 60);
                $lateMessage = " (Telat {$minutes} menit)";
            } else {
                $lateMessage = " (Telat {$delay} detik)";
            }
        }

        // Location and geofence handling for WFO/WFA
        $lat = isset($_POST['lat']) ? (float) $_POST['lat'] : null;
        $lng = isset($_POST['lng']) ? (float) $_POST['lng'] : null;
        $lokasi = $_POST['lokasi'] ?? null;
        $alasanWfa = null;
        $gpsAccuracy = isset($_POST['gps_accuracy']) ? (float) $_POST['gps_accuracy'] : null;
        $wifiSSID = trim($_POST['wifi_ssid'] ?? '');

        // Strict validation: GPS location is mandatory
        if ($lat === null || $lng === null || $lat === 0 || $lng === 0) {
            jsonResponse(['ok' => false, 'message' => 'Lokasi GPS wajib untuk presensi. Pastikan GPS aktif dan izin lokasi diberikan.'], 400);
        }

        // -------------------------------------------------------------------------
        // ANTI-SPOOFING: Bounding Box Check (Indonesia Only)
        // Roughly: Lat -11 to 6 (South to North), Lng 95 to 141 (West to East)
        // -------------------------------------------------------------------------
        if ($lat < -11.0 || $lat > 6.0 || $lng < 95.0 || $lng > 141.0) {
            error_log("Anti-Spoofing: Out of bounds coordinates detected ($lat, $lng)");
            jsonResponse(['ok' => false, 'message' => 'Lokasi terdeteksi di luar negara Indonesia (kemungkinan Fake GPS/VPN). Mohon matikan aplikasi pemalsu lokasi dan coba lagi.'], 400);
        }

        // -------------------------------------------------------------------------
        // ANTI-SPOOFING: GPS Accuracy Check
        // Fake GPS apps usually report unrealistically high accuracy (e.g., exactly 0m or very low)
        // But also very high values. Real GPS indoors: 20-100m. Real GPS outdoors: 3-30m.
        // Reject if accuracy is suspiciously perfect (<1m - fake GPS hallmark)
        // OR completely unusable (>150m means the device likely has no actual GPS lock)
        // -------------------------------------------------------------------------
        if ($gpsAccuracy !== null) {
            if ($gpsAccuracy < 1.0 || (float) $gpsAccuracy === 150.0) {
                error_log("Anti-Spoofing: Suspicious GPS accuracy ({$gpsAccuracy}m) - likely fake GPS or browser emulation");
                jsonResponse(['ok' => false, 'message' => 'Akurasi GPS mencurigakan ('.round($gpsAccuracy, 1).'m) - kemungkinan menggunakan aplikasi Fake GPS atau fitur simulasi lokasi di browser. Mohon matikan aplikasi/fitur tersebut dan coba lagi.'], 400);
            }
            if ($gpsAccuracy > 250) {
                error_log("Anti-Spoofing: GPS accuracy too low ({$gpsAccuracy}m) - no real GPS lock");
                jsonResponse(['ok' => false, 'message' => 'Sinyal GPS terlalu lemah (akurasi: '.round($gpsAccuracy).'m). Mohon pergi ke area terbuka dan pastikan GPS aktif.'], 400);
            }
            error_log("GPS Accuracy: {$gpsAccuracy}m - accepted");
        }

        // OPTIMIZED: Skip reverse geocoding for faster performance
        // Use coordinates directly - reverse geocoding can be slow and is not critical
        if (empty($lokasi) || strpos($lokasi, 'Lokasi:') === 0) {
            if ($lat !== null && $lng !== null) {
                // Use coordinates directly for faster response
                $lokasi = 'Lokasi: '.round($lat, 6).', '.round($lng, 6);
            } else {
                // No coordinates - use default
                $lokasi = 'Lokasi tidak tersedia';
            }
        }

        // Final validation - ensure lokasi is never empty
        if (empty($lokasi)) {
            if ($lat !== null && $lng !== null) {
                $lokasi = 'Lokasi: '.round($lat, 6).', '.round($lng, 6);
            } else {
                $lokasi = 'Lokasi tidak tersedia';
            }
        }

        // Determine WFO via API or coordinate fallback
        $wfoMode = strtolower(getSetting($pdo, 'wfo_mode', 'api'));

        // CRITICAL: IP detection strategy
        // Priority: REMOTE_ADDR first (this IS the campus private IP when user is on campus WiFi)
        // Only fall back to POST public_ip for additional context
        // Reason: frontend fetches IP via api.ipify.org which returns PUBLIC IP (NAT'd),
        //         losing the private 10.x.x.x campus WiFi IP. REMOTE_ADDR from server
        //         IS the actual direct connection IP.
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
        $forwardedFor = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        $postPublicIp = $_POST['public_ip'] ?? '';

        // Build candidate IP list: REMOTE_ADDR first, then forwarded, then POST
        $ipCandidates = [];
        if (! empty($remoteAddr) && filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
            $ipCandidates[] = $remoteAddr;
        }
        if (! empty($forwardedFor)) {
            foreach (explode(',', $forwardedFor) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    $ipCandidates[] = $ip;
                }
            }
        }
        if (! empty($postPublicIp) && filter_var($postPublicIp, FILTER_VALIDATE_IP)) {
            $ipCandidates[] = $postPublicIp;
        }

        // Pick the best IP: prefer private campus IP (10.x.x.x) over public IP
        $publicIp = '';
        $campusPrivateIp = '';
        foreach ($ipCandidates as $candidate) {
            $isLocalhost = in_array($candidate, ['127.0.0.1', '::1']) || strpos($candidate, '127.') === 0;
            if ($isLocalhost) {
                continue;
            }
            if (isTelkomUniversityPrivateIp($candidate)) {
                $campusPrivateIp = $candidate; // Found campus IP!
            }
            if (empty($publicIp)) {
                $publicIp = $candidate; // Use first non-localhost as fallback
            }
        }
        // If we found a campus private IP, prefer it for WFO detection
        if (! empty($campusPrivateIp)) {
            $publicIp = $campusPrivateIp;
        }

        // Log IP detection result
        if (empty($publicIp) || ! filter_var($publicIp, FILTER_VALIDATE_IP)) {
            error_log('WARNING: Could not detect valid IP address (skipped localhost) - will rely on WiFi/GPS validation');
        } else {
            error_log("IP Detected: $publicIp (from ".(isset($_POST['public_ip']) ? 'POST' : 'SERVER').')');
        }

        // Log IP detection for debugging
        error_log('WFO IP Detection - Public IP: '.($publicIp ?: 'NOT DETECTED').", Mode: $wfoMode");

        // -------------------------------------------------------------------------
        // ANTI-SPOOFING: IP Geolocation Check (Indonesia Only)
        // -------------------------------------------------------------------------
        $ipCountry = $_SERVER['HTTP_CF_IPCOUNTRY'] ?? null;
        $isLocalhost = ($publicIp === '127.0.0.1' || $publicIp === '::1' || strpos($publicIp, '127.') === 0);

        if ($ipCountry !== null && strtoupper($ipCountry) !== 'ID') {
            error_log("Anti-Spoofing: IP Country mismatch detected. CF_IPCOUNTRY: $ipCountry");
            jsonResponse(['ok' => false, 'message' => 'Akses dari luar negeri dilarang. Harap matikan VPN/Proxy Anda.'], 400);
        }

        // IP Geolocation API validation ALWAYS runs (if not localhost)
        if (! empty($publicIp) && filter_var($publicIp, FILTER_VALIDATE_IP) && ! $isLocalhost) {
            $isPrivateIp = ! filter_var($publicIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
            if (! isTelkomUniversityPrivateIp($publicIp) && ! $isPrivateIp) {
                try {
                    // Quick IP check using ip-api
                    $url = 'http://ip-api.com/json/'.urlencode($publicIp).'?fields=status,countryCode,lat,lon';
                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 2); // 2 second timeout so we don't block
                    $resp = curl_exec($ch);
                    curl_close($ch);

                    if ($resp) {
                        $ipData = json_decode($resp, true);

                        // Extra fallback if CF header was missing
                        if ($ipCountry === null && isset($ipData['countryCode']) && strtoupper($ipData['countryCode']) !== 'ID') {
                            error_log('Anti-Spoofing: API reported out of country IP: '.$ipData['countryCode']);
                            jsonResponse(['ok' => false, 'message' => 'Alamat IP internet Anda terdeteksi dari luar negara Republik Indonesia. Harap matikan aplikasi VPN/Proxy Anda.'], 400);
                        }

                        // IP-GPS Distance Check
                        if (isset($ipData['lat']) && isset($ipData['lon']) && $lat !== null && $lng !== null) {
                            $ipLat = (float) $ipData['lat'];
                            $ipLon = (float) $ipData['lon'];

                            // Haversine formula
                            $earthRadius = 6371; // km
                            $dLat = deg2rad($ipLat - $lat);
                            $dLon = deg2rad($ipLon - $lng);
                            $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat)) * cos(deg2rad($ipLat)) * sin($dLon / 2) * sin($dLon / 2);
                            $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
                            $distanceKm = $earthRadius * $c;

                            error_log('Anti-Spoofing: IP-GPS Distance: '.round($distanceKm, 2).' km');

                            // 1. General check (500km threshold)
                            if ($distanceKm > 500) {
                                error_log("Anti-Spoofing: IP-GPS mismatch ($distanceKm km). IP: $publicIp ($ipLat, $ipLon), GPS: $lat, $lng");
                                jsonResponse(['ok' => false, 'message' => 'Akurasi ditolak: Lokasi GPS terdeteksi terlalu jauh dari lokasi IP Internet Anda. Mohon matikan VPN / Proxy / Aplikasi Fake GPS.'], 400);
                            }

                            // 2. Strict check for campus geofence:
                            // If GPS is inside campus geofence but IP is >150km away, they are definitely spoofing GPS to campus.
                            $wfoLat = (float) getSetting($pdo, 'wfo_lat', '-6.97662');
                            $wfoLng = (float) getSetting($pdo, 'wfo_lng', '107.63273');
                            $wfoRadius = min((int) getSetting($pdo, 'wfo_radius_m', '800'), 1500);

                            // Calculate distance to FIT for local geofence check
                            $earth = 6371000; // meters
                            $dLat = deg2rad($wfoLat - $lat);
                            $dLng = deg2rad($wfoLng - $lng);
                            $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat)) * cos(deg2rad($wfoLat)) * sin($dLng / 2) * sin($dLng / 2);
                            $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
                            $dist = $earth * $c;
                            $isInsideRadius = ($dist <= $wfoRadius);

                            if ($isInsideRadius && $distanceKm > 150) {
                                error_log("Anti-Spoofing: GPS is at campus but IP is $distanceKm km away. GPS: $lat, $lng. Possible Fake GPS spoofing!");
                                jsonResponse(['ok' => false, 'message' => 'Akurasi ditolak: Terdeteksi pemalsuan lokasi. Lokasi GPS Anda berada di kampus, namun IP Internet Anda terdeteksi berada di daerah lain. Mohon matikan aplikasi Fake GPS Anda.'], 400);
                            }
                        }
                    }
                } catch (Exception $e) {
                    // Silently ignore to avoid blocking legitimate users if API fails
                }
            }
        }

        // =========================================================
        // WFO STRICT RULE: HARUS KEDUA SYARAT TERPENUHI
        // 1. IP Address terdeteksi sebagai jaringan FIT/Telkom University
        // 2. GPS dalam radius 50 meter dari gedung Fakultas Ilmu Terapan
        // =========================================================
        // Koordinat pusat gedung Fakultas Ilmu Terapan (FIT) Telkom University
        $wfoLat = (float) getSetting($pdo, 'wfo_lat', '-6.97662');
        $wfoLng = (float) getSetting($pdo, 'wfo_lng', '107.63273');
        // Radius WFO maksimal 800 meter untuk mencakup seluruh area kampus FIT
        $wfoRadius = min((int) getSetting($pdo, 'wfo_radius_m', '800'), 1500);

        // Hitung jarak GPS menggunakan Haversine formula
        $distance = null;
        $distanceToFit = null;
        $isInsideRadius = false;
        if ($lat !== null && $lng !== null) {
            $earth = 6371000; // meters
            $dLat = deg2rad($wfoLat - $lat);
            $dLng = deg2rad($wfoLng - $lng);
            $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat)) * cos(deg2rad($wfoLat)) * sin($dLng / 2) * sin($dLng / 2);
            $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
            $distance = $earth * $c;

            // Hitung jarak ke Gedung FIT (untuk backup geofencing drift)
            $fitLat = -6.97662;
            $fitLng = 107.63273;
            $dLatFit = deg2rad($fitLat - $lat);
            $dLngFit = deg2rad($fitLng - $lng);
            $aFit = sin($dLatFit / 2) * sin($dLatFit / 2) + cos(deg2rad($lat)) * cos(deg2rad($fitLat)) * sin($dLngFit / 2) * sin($dLngFit / 2);
            $cFit = 2 * atan2(sqrt($aFit), sqrt(1 - $aFit));
            $distanceToFit = $earth * $cFit;

            // STRICT: Use the exact radius setting configured by the admin dynamically
            $effectiveRadius = $wfoRadius;
            $isInsideRadius = ($distance <= $effectiveRadius);
            error_log('WFO GPS Check - Jarak ke Kantor: '.round($distance).'m, Jarak ke FIT: '.round($distanceToFit)."m, Radius WFO: {$effectiveRadius}m, Inside: ".($isInsideRadius ? 'YES' : 'NO'));
        }

        // Validasi IP Address FIT
        $isInsideTeluByApi = false;
        $isLocalhost = ($publicIp === '127.0.0.1' || $publicIp === '::1' || strpos($publicIp, '127.') === 0);
        if (! empty($publicIp) && filter_var($publicIp, FILTER_VALIDATE_IP) && ! $isLocalhost) {
            try {
                $isInsideTeluByApi = isWfoByApi($pdo, $publicIp);
                error_log("WFO IP Check - IP: $publicIp, FIT Network: ".($isInsideTeluByApi ? 'YES' : 'NO'));
            } catch (Exception $e) {
                error_log('WFO IP Check Error: '.$e->getMessage());
                $isInsideTeluByApi = false;
            }
        } else {
            error_log('WFO IP Check - Skipped (IP: '.($publicIp ?: 'EMPTY').($isLocalhost ? ' [localhost]' : '').')');
        }

        // Logging debug
        error_log('=== WFO STRICT VALIDATION ===');
        error_log('IP: '.($publicIp ?: 'EMPTY').' | FIT Network: '.($isInsideTeluByApi ? 'YES' : 'NO'));
        error_log('GPS Jarak ke FIT: '.($distance !== null ? round($distance).'m' : 'N/A').' | Inside Radius (200m cap): '.($isInsideRadius ? 'YES' : 'NO'));

        // =========================================================
        // KEPUTUSAN FINAL WFO/WFA:
        // WFO = HARUS memenuhi KEDUA syarat:
        //   1. IP terdeteksi sebagai jaringan Telkom University (WiFi/LAN kampus)
        //   2. DAN GPS dalam radius 200m dari gedung FIT
        // Jika hanya salah satu => WFA
        // Rasionalisasi: Perumahan/kos sekitar TelU bisa masuk radius GPS ~100-300m,
        //   dan beberapa kos menggunakan WiFi TelU. Dengan AND logic,
        //   orang di rumah yang pakai WiFi kampus pun akan terdeteksi WFA
        //   karena GPS mereka di luar radius 200m.
        // =========================================================
        $ketVal = 'wfa'; // Default WFA

        // =========================================================
        // KEPUTUSAN FINAL WFO/WFA — SMART CAMPUS GEOFENCING:
        //   WFO jika:
        //     a) IP terdeteksi jaringan TelU (WiFi/LAN kampus) — bukti kuat
        //     b) ATAU GPS berada dalam radius dekat Gedung FIT (sesuai setting admin)
        //     c) ATAU GPS berada dalam radius dekat Gedung FIT (<= 250m tolerance for GPS drift)
        //        DAN alamat geocode membuktikan berada di area FIT Telkom University
        //        (memiliki keyword kampus FIT dan tidak memiliki keyword kos/gang/rumah/sukabirus).
        // =========================================================
        $isInsideCampusGeofence = false;
        if ($distanceToFit !== null && $distanceToFit <= 250) {
            $lowerLokasi = strtolower($lokasi);

            // Keyword valid area internal Telkom University
            $campusKeywords = ['telkom', 'selaru', 'tokong', 'fakultas', 'gedung', 'danau galau', 'situ techno', 'situ tekno', 'monumen', 'rektorat', 'tass', 'fit', 'fik', 'fif', 'fri', 'fte', 'feb', 'fkb'];

            // Keyword penanda luar kampus / kosan / pemukiman sekitar
            $offCampusKeywords = ['kos', 'kost', 'gang', 'gg.', 'gg ', 'rumah', 'wisma', 'kontrakan', 'residence', 'cluster', 'perumahan', 'sukabirus', 'ciganitri', 'pga'];

            $hasCampusKeyword = false;
            foreach ($campusKeywords as $kw) {
                if (str_contains($lowerLokasi, $kw)) {
                    $hasCampusKeyword = true;
                    break;
                }
            }

            $hasOffCampusKeyword = false;
            foreach ($offCampusKeywords as $kw) {
                if (str_contains($lowerLokasi, $kw)) {
                    $hasOffCampusKeyword = true;
                    break;
                }
            }

            if ($hasCampusKeyword && ! $hasOffCampusKeyword) {
                $isInsideCampusGeofence = true;
                error_log("WFO Campus Geofence - Lokasi valid kampus: $lokasi");
            } else {
                error_log("WFO Campus Geofence - Lokasi ditolak (pemukiman/luar): $lokasi");
            }
        }

        if ($wfoMode === 'api') {
            // STRICT MODE (IP + Geofence): WFO if on TelU IP AND inside GPS radius/geofence
            $hasValidLocation = $isInsideRadius || $isInsideCampusGeofence;
            if ($isInsideTeluByApi && $hasValidLocation) {
                $ketVal = 'wfo';
                error_log('✓ WFO via IP & Geofence (Strict Mode) — IP: '.$publicIp.', Jarak: '.round($distance).'m');
            } else {
                $reasons = [];
                if (! $isInsideTeluByApi) {
                    $reasons[] = 'IP tidak terdeteksi di jaringan Telkom University';
                }
                if (! $hasValidLocation) {
                    $reasons[] = 'GPS di luar radius geofence kampus';
                }
                error_log('✗ WFA via Strict Mode — '.implode('; ', $reasons));
            }
        } else {
            // COORDINATE MODE (GPS Geofence Only): WFO if inside GPS radius/geofence
            if ($isInsideRadius) {
                $ketVal = 'wfo';
                error_log('✓ WFO via GPS (Coordinate Mode) — Jarak: '.round($distance).'m');
            } elseif ($isInsideCampusGeofence) {
                $ketVal = 'wfo';
                error_log('✓ WFO via Campus Geofence (Coordinate Mode) — Alamat: '.$lokasi);
            } else {
                error_log('✗ WFA via Coordinate Mode — GPS di luar radius geofence kampus.');
            }
        }

        // Final check — jika WFA, minta alasan dan alihkan ke approval
        if ($ketVal === 'wfa') {
            $alasanWfa = $_POST['wfa_reason'] ?? $_POST['alasan_wfa'] ?? null;
            if (! $alasanWfa) {
                $wfaReasons = [];
                if (! $isInsideTeluByApi) {
                    $wfaReasons[] = 'IP tidak dikenali sebagai jaringan Telkom University (IP: '.($publicIp ?: 'tidak terdeteksi').')';
                }
                if (! $isInsideRadius) {
                    $distInfo = $distance !== null ? ' (jarak: '.round($distance).'m, maks: '.$effectiveRadius.'m)' : ' (GPS tidak tersedia)';
                    $wfaReasons[] = 'Lokasi di luar area kampus'.$distInfo;
                }
                $wfaMsg = 'Presensi terdeteksi sebagai WFA: '.implode('; ', $wfaReasons).'. Harap isi alasan kerja dari luar kantor (WFA).';
                jsonResponse(['ok' => false, 'need_reason' => true, 'message' => $wfaMsg]);
            }

            // Insert pending WFA request
            $ins = $pdo->prepare("INSERT INTO admin_help_requests (user_id, request_type, tanggal, jam_masuk, bukti_presensi, lokasi_presensi, attendance_type, attendance_reason, status) VALUES (:uid, 'late_attendance', :today, :jam, :screenshot, :lokasi, 'wfa', :alasan, 'pending')");
            $ins->execute([
                ':uid' => $u['id'],
                ':today' => $today,
                ':jam' => $jamSekarang,
                ':screenshot' => $screenshot,
                ':lokasi' => $lokasi,
                ':alasan' => $alasanWfa,
            ]);

            $jamMasukFormat = substr($jamSekarang, 0, 5);
            $firstName = getFirstName($u['nama']);
            $statusText = "Selamat datang {$firstName}, presensi WFA telah dikirim dan menunggu persetujuan Admin.";
            jsonResponse(['ok' => true, 'message' => $statusText, 'nama' => $u['nama'], 'jam' => $jamMasukFormat, 'statusClass' => 'bg-yellow-100 text-yellow-700']);

            return; // Exit early for WFA
        }

        // ULTRA-FAST: Minimal insert for maximum speed
        $ins = $pdo->prepare('INSERT INTO attendance (user_id, jam_masuk, jam_masuk_iso, ekspresi_masuk, foto_masuk, landmark_masuk, lokasi_masuk, lat_masuk, lng_masuk, status, ket, alasan_wfa, alasan_overtime, lokasi_overtime) VALUES (:uid, :jam, :iso, :exp, :screenshot, :landmark, :lokasi, :lat, :lng, :status, :ket, :alasan, :alasan_ot, :lokasi_ot)');
        $ins->execute([':uid' => $u['id'], ':jam' => $jamSekarang, ':iso' => $iso, ':exp' => $ekspresi, ':screenshot' => $screenshot, ':landmark' => $landmark, ':lokasi' => $lokasi, ':lat' => $lat, ':lng' => $lng, ':status' => $status, ':ket' => $ketVal, ':alasan' => $alasanWfa, ':alasan_ot' => null, ':lokasi_ot' => null]);

        // Fallback cache clear
        clearKpiCache($pdo, $u['id'], $today);

        // OPTIMIZED: Backup trigger removed - happens on schedule
        // triggerDatabaseBackup();

        // ULTRA-FAST: Ultra-minimal response for maximum speed
        $jamMasukFormat = substr($jamSekarang, 0, 5);
        $firstName = getFirstName($u['nama']);
        if ($isLate) {
            $statusText = "Selamat datang {$firstName}, anda masuk {$jamMasukFormat}. terlambat!";
            jsonResponse(['ok' => true, 'message' => $statusText, 'nama' => $u['nama'], 'jam' => $jamMasukFormat, 'statusClass' => 'bg-yellow-100 text-yellow-700']);
        } else {
            $statusText = "Selamat datang {$firstName}, anda masuk {$jamMasukFormat}. OnTime!";
            jsonResponse(['ok' => true, 'message' => $statusText, 'nama' => $u['nama'], 'jam' => $jamMasukFormat, 'statusClass' => 'bg-green-100 text-green-700']);
        }
    } else {
        // Check if user has izin/sakit today
        if ($todayRow['ket'] === 'izin' || $todayRow['ket'] === 'sakit') {
            $statusText = "Anda sudah mengajukan {$todayRow['ket']} hari ini. Tidak bisa melakukan presensi.";
            jsonResponse(['ok' => false, 'message' => $statusText, 'statusClass' => 'bg-red-100 text-red-700']);
        } else {
            $masukTime = new DateTime($todayRow['jam_masuk_iso']);
            $statusText = 'Anda sudah presensi masuk pukul '.$masukTime->format('H:i').' dan belum pulang.';
            jsonResponse(['ok' => false, 'message' => $statusText, 'statusClass' => 'bg-yellow-100 text-yellow-700']);
        }
    }
} else {
    // Check if within check-out time window using settings
    $minCheckoutSetting = getSetting($pdo, 'min_checkout_hour', '17:00');
    if (strpos($minCheckoutSetting, ':') === false) {
        $minCheckoutSetting = sprintf('%02d:00', (int) $minCheckoutSetting);
    }
    $currentTimeString = sprintf('%02d:%02d', $currentHour, $currentMinute);

    // Check if checked in today and not yet checked out
    $todayCheck = $pdo->prepare('SELECT * FROM attendance WHERE user_id=:uid AND DATE(jam_masuk_iso)=:today AND jam_pulang_iso IS NULL ORDER BY jam_masuk_iso DESC LIMIT 1');
    $todayCheck->execute([':uid' => $u['id'], ':today' => $today]);
    $todayRow = $todayCheck->fetch();

    if (! $todayRow) {
        $statusText = 'Anda belum melakukan presensi masuk hari ini atau sudah pulang.';
        jsonResponse(['ok' => false, 'message' => $statusText, 'statusClass' => 'bg-yellow-100 text-yellow-700']);
    } else {
        // Check if pulang sebelum jam yang diizinkan
        if ($currentTimeString < $minCheckoutSetting) {
            // Minta alasan pulang awal
            $alasanPulangAwal = $_POST['alasan_pulang_awal'] ?? $_POST['early_leave_reason'] ?? null;
            if (! $alasanPulangAwal) {
                $firstName = getFirstName($u['nama']);
                $statusText = "Anda pulang sebelum jam {$minCheckoutSetting}. Harap isi alasan pulang awal.";
                jsonResponse(['ok' => false, 'need_early_leave_reason' => true, 'message' => $statusText, 'statusClass' => 'bg-orange-100 text-orange-700']);
            }
        }

        $lat = isset($_POST['lat']) ? (float) $_POST['lat'] : null;
        $lng = isset($_POST['lng']) ? (float) $_POST['lng'] : null;
        $lokasi = $_POST['lokasi'] ?? null;

        // OPTIMIZED: Quick reverse geocoding - ensure lokasi is never empty
        if (empty($lokasi) || strpos($lokasi, 'Lokasi:') === 0) {
            if ($lat !== null && $lng !== null) {
                // Try reverse geocoding with shorter timeout
                $reverseGeocoded = @reverseGeocodeAddress($lat, $lng);
                if ($reverseGeocoded && ! empty($reverseGeocoded)) {
                    $lokasi = $reverseGeocoded;
                } else {
                    // Fallback - ensure lokasi is never empty
                    $lokasi = 'Lokasi: '.round($lat, 6).', '.round($lng, 6);
                }
            } else {
                // No coordinates - use default
                $lokasi = 'Lokasi tidak tersedia';
            }
        }

        // Final validation - ensure lokasi is never empty
        if (empty($lokasi)) {
            if ($lat !== null && $lng !== null) {
                $lokasi = 'Lokasi: '.round($lat, 6).', '.round($lng, 6);
            } else {
                $lokasi = 'Lokasi tidak tersedia';
            }
        }

        // Get alasan pulang awal if provided
        $alasanPulangAwal = $_POST['alasan_pulang_awal'] ?? $_POST['early_leave_reason'] ?? null;
        $diffLocationReason = $_POST['diff_location_reason'] ?? null;

        if ($alasanPulangAwal) {
            // Requires approval - send to admin_help_requests
            // 'subtype' is encoded in attendance_reason as 'pulang_lebih_awal|<reason>' for correct label display
            $ins = $pdo->prepare("INSERT INTO admin_help_requests (user_id, request_type, tanggal, jam_masuk, jam_pulang, bukti_presensi, lokasi_presensi, lat_pulang, lng_pulang, ekspresi_pulang, attendance_type, attendance_reason, status) VALUES (:uid, 'late_attendance', :today, :jmasuk, :jam, :screenshot, :lokasi, :lat, :lng, :ekspresi, :ket, :alasan, 'pending')");
            $ins->execute([
                ':uid' => $u['id'],
                ':today' => $today,
                ':jmasuk' => $todayRow['jam_masuk'],
                ':jam' => $jamSekarang,
                ':screenshot' => $screenshot,
                ':lokasi' => $lokasi,
                ':lat' => $lat,
                ':lng' => $lng,
                ':ekspresi' => $ekspresi,
                ':ket' => $todayRow['ket'], // Keep original ket
                ':alasan' => 'pulang_lebih_awal|'.$alasanPulangAwal, // Prefixed to distinguish from manual 'lupa presensi'
            ]);

            $jamPulangFormat = substr($jamSekarang, 0, 5);
            $firstName = getFirstName($u['nama']);
            $statusText = 'Permintaan pulang lebih awal telah dikirim dan menunggu persetujuan Admin.';
            jsonResponse(['ok' => true, 'message' => $statusText, 'nama' => $u['nama'], 'jam' => $jamPulangFormat, 'statusClass' => 'bg-yellow-100 text-yellow-700']);

            return;
        }

        // NEW: If checkout location differs from checkin, require admin approval
        if ($diffLocationReason) {
            $ins = $pdo->prepare("INSERT INTO admin_help_requests (user_id, request_type, tanggal, jam_masuk, jam_pulang, bukti_presensi, lokasi_presensi, lat_pulang, lng_pulang, ekspresi_pulang, attendance_type, attendance_reason, status) VALUES (:uid, 'diff_location_checkout', :today, :jmasuk, :jam, :screenshot, :lokasi, :lat, :lng, :ekspresi, :ket, :alasan, 'pending')");
            $ins->execute([
                ':uid' => $u['id'],
                ':today' => $today,
                ':jmasuk' => $todayRow['jam_masuk'],
                ':jam' => $jamSekarang,
                ':screenshot' => $screenshot,
                ':lokasi' => $lokasi,
                ':lat' => $lat,
                ':lng' => $lng,
                ':ekspresi' => $ekspresi,
                ':ket' => $todayRow['ket'],
                ':alasan' => 'diff_location|'.$diffLocationReason, // Prefixed for identification
            ]);
            $jamPulangFormat = substr($jamSekarang, 0, 5);
            jsonResponse([
                'ok' => true,
                'message' => "Presensi pulang Anda tercatat pukul {$jamPulangFormat}, namun lokasi berbeda. Permintaan menunggu persetujuan Admin.",
                'nama' => $u['nama'],
                'jam' => $jamPulangFormat,
                'statusClass' => 'bg-yellow-100 text-yellow-700',
            ]);

            return;
        }

        $upd = $pdo->prepare('UPDATE attendance SET jam_pulang=:jam, jam_pulang_iso=:iso, ekspresi_pulang=:exp, foto_pulang=:screenshot, landmark_pulang=:landmark, lokasi_pulang=:lokasi, lat_pulang=:lat, lng_pulang=:lng, alasan_pulang_awal=:alasan, alasan_lokasi_berbeda=:diff_loc WHERE id=:id');
        $upd->execute([':jam' => $jamSekarang, ':iso' => $iso, ':exp' => $ekspresi, ':screenshot' => $screenshot, ':landmark' => $landmark, ':lokasi' => $lokasi, ':lat' => $lat, ':lng' => $lng, ':alasan' => $alasanPulangAwal, ':diff_loc' => $diffLocationReason, ':id' => $todayRow['id']]);

        // Fallback cache clear
        clearKpiCache($pdo, $u['id'], $today);

        // Trigger backup setelah presensi pulang
        triggerDatabaseBackup();
        $jamPulangFormat = substr($jamSekarang, 0, 5); // Ambil hanya jam:menit
        $firstName = getFirstName($u['nama']);
        $statusText = "Selamat jalan, {$firstName}! Anda terlihat {$ekspresi}. Jam pulang tercatat pukul {$jamPulangFormat}.";
        jsonResponse(['ok' => true, 'message' => $statusText, 'nama' => $u['nama'], 'jam' => $jamPulangFormat, 'statusClass' => 'bg-green-100 text-green-700']);
    }
}
