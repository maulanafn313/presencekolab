<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <script src="/assets/js/request-security.js"></script>
    <script>window.attendanceSecurity = <?= json_encode(['requireProof' => (bool)config('attendance.require_face_proof'), 'userId' => $_SESSION['user']['id'] ?? null], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
    <script src="/assets/js/api-client.js?v=2"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Aplikasi Presensi Wajah</title>
    <script src="assets/js/tailwind.js"></script>

    <script src="assets/js/face-api.min.js" defer></script>
    <script>
        // Expose model URL for optimizers
        window.FACEAPI_MODEL_URL = 'assets/face-models';
        window.USER_ROLE = '<?php echo $_SESSION['user']['role'] ?? 'guest'; ?>';
    </script>
    <script src="assets/js/performance-optimizer.js" defer></script>
    
    <!-- Cache optimization enabled -->
    
    <script src="assets/js/chart.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
    <link rel="stylesheet" href="assets/css/inter.css">
    <link rel='stylesheet' href='assets/css/uicons-solid-rounded.css'>
    <link rel='stylesheet' href='assets/css/uicons-solid-straight.css'>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#6366f1">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Presensi App">
    <link rel="stylesheet" href="assets/css/app.css">

    <script>
        // Security & Privacy: Suppress console logs to prevent information leakage
        (function() {
            if (window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
                var noop = function() {};
                console.log = noop;
                console.info = noop;
                console.warn = noop;
                console.debug = noop;
            } else {
                // If on localhost but user explicitly requested to hide logs, we suppress them unconditionally
                // as requested for privacy testing
                var noop = function() {};
                console.log = noop;
                console.info = noop;
                console.warn = noop;
                console.debug = noop;
            }
        })();
    </script>

<!-- Global Helper Script for Exports (Generated to ensure availability) -->
<script>
// Utility for quick selection
function qs(sel) { return document.querySelector(sel); }
function qsa(sel) { return document.querySelectorAll(sel); }

// Global Export Wrappers
window.openExportDailyModal = function() {
    const d = document.getElementById('export-presensi-modal');
    if(d) {
        d.classList.remove('hidden');
        d.style.display = 'flex';
        // Sync visibility of monthly options
        const range = document.getElementById('export-p-range');
        const opts = document.getElementById('export-p-monthly-opts');
        if (range && opts) {
            if (range.value === 'monthly') {
                opts.style.display = 'block';
                opts.classList.remove('hidden');
            } else {
                opts.style.display = 'none';
                opts.classList.add('hidden');
            }
        }
    } else alert('Modal not found!');
};

window.closeExportDailyModal = function() {
    const d = document.getElementById('export-presensi-modal');
    if(d) {
        d.classList.add('hidden');
        d.style.display = 'none';
    }
};

window.triggerExportMonthly = function() {
    const startup = qs('#am-startup')?.value || '';
    const month = qs('#am-month')?.value || '';
    const year = qs('#am-year')?.value || '';
    const term = qs('#am-search')?.value || '';
    
    // Default to 'per_employee' format
    const params = new URLSearchParams({
        startup: startup,
        month: month,
        year: year,
        term: term,
        format: 'per_employee'
    });
    
    window.location.href = '?ajax=export_monthly&' + params.toString();
};

window.triggerExportKPI = function() {
    // We try to find the filters. If not found, use defaults.
    const fType = qs('#kpi-filter-type');
    const fMonth = qs('#kpi-filter-month');
    const fYear = qs('#kpi-filter-year');

    const type = fType ? fType.value : 'period';
    const month = fMonth ? fMonth.value : '';
    const year = fYear ? fYear.value : '';
    
    const params = new URLSearchParams();
    params.append('filter_type', type);
    if (type === 'monthly' && month && year) {
        params.append('month', month);
        params.append('year', year);
    }
    window.location.href = '?ajax=export_kpi&' + params.toString();
};
</script>
    <script>
        window.currentUserId = '<?php echo $_SESSION["user"]["id"] ?? ""; ?>';
        window.currentUserRole = '<?php echo $_SESSION["user"]["role"] ?? ""; ?>';
    </script>
</head>
<body class="bg-gray-50 text-gray-800">

