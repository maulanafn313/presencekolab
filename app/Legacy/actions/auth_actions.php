<?php

use App\Models\User;
use App\Services\PasswordResetGrant;
use App\Services\SessionLifecycle;

// Extracted from ajax_handler.php — action group: auth
// Variables in scope: $pdo, $action, $archivedExcludeQuery, $archivedExcludeUsersQuery

if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email=:email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        // Check if user is archived (in archived group and NOT in any active group)
        if ($user['role'] === 'pegawai') {
            $chkActive = $pdo->prepare('SELECT 1 FROM intern_group_members igm JOIN intern_groups ig ON ig.id = igm.group_id WHERE igm.user_id = :uid AND ig.is_archived = 0 LIMIT 1');
            $chkActive->execute([':uid' => $user['id']]);
            $hasActiveGroup = (bool) $chkActive->fetchColumn();

            $chkArchived = $pdo->prepare('SELECT 1 FROM intern_group_members igm JOIN intern_groups ig ON ig.id = igm.group_id WHERE igm.user_id = :uid AND ig.is_archived = 1 LIMIT 1');
            $chkArchived->execute([':uid' => $user['id']]);
            $hasArchivedGroup = (bool) $chkArchived->fetchColumn();

            if ($hasArchivedGroup && ! $hasActiveGroup) {
                jsonResponse(['ok' => false, 'message' => 'Akun Anda telah diaktifkan/diarsipkan (lulus). Silakan hubungi admin.'], 403);
            }
        }
        app(SessionLifecycle::class)->login(request(), $user);
        jsonResponse(['ok' => true, 'role' => $user['role']]);
    }
    jsonResponse(['ok' => false, 'message' => 'Email atau password salah'], 400);
}

if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $nim = trim($_POST['nim'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $prodi = trim($_POST['prodi'] ?? '');
    $startup = trim($_POST['startup'] ?? '');
    $foto = $_POST['foto'] ?? null; // data URL
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if ($password !== $password2) {
        jsonResponse(['ok' => false, 'message' => 'Konfirmasi password tidak cocok'], 400);
    }
    if (! $email || ! $nim || ! $nama || ! $prodi || ! $password || ! $foto) {
        jsonResponse(['ok' => false, 'message' => 'Semua field wajib diisi (termasuk foto)'], 400);
    }

    // 1. Validate email format
    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['ok' => false, 'message' => 'Format alamat email tidak valid'], 400);
    }

    // 2. Validate password length (at least 6 characters)
    if (strlen($password) < 6) {
        jsonResponse(['ok' => false, 'message' => 'Password minimal harus 6 karakter'], 400);
    }

    // Check image size (max 1MB)
    if (! checkImageSize($foto, 1)) {
        jsonResponse(['ok' => false, 'message' => 'Ukuran foto terlalu besar. Maksimal 1MB. Silakan kompres foto atau gunakan foto dengan resolusi lebih kecil.'], 400);
    }

    // 3. Disallow duplicate email (checked separately)
    $checkEmail = $pdo->prepare('SELECT id FROM users WHERE email=:email LIMIT 1');
    $checkEmail->execute([':email' => $email]);
    if ($checkEmail->fetch()) {
        jsonResponse(['ok' => false, 'message' => 'Alamat email sudah terdaftar'], 400);
    }

    // 4. Disallow duplicate nim (checked separately)
    $checkNim = $pdo->prepare('SELECT id FROM users WHERE nim=:nim LIMIT 1');
    $checkNim->execute([':nim' => $nim]);
    if ($checkNim->fetch()) {
        jsonResponse(['ok' => false, 'message' => 'NIM sudah terdaftar'], 400);
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO users (role, email, nim, nama, prodi, startup, foto_base64, password) VALUES ('pegawai', :email, :nim, :nama, :prodi, :startup, :foto, :hash)");
    $stmt->execute([
        ':email' => $email,
        ':nim' => $nim,
        ':nama' => $nama,
        ':prodi' => $prodi,
        ':startup' => $startup ?: null,
        ':foto' => $foto,
        ':hash' => $hash,
    ]);

    // Trigger backup setelah menambah user baru
    triggerDatabaseBackup();

    jsonResponse(['ok' => true]);
}

// Forgot Password - Request reset

if ($action === 'forgot_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (empty($email)) {
        jsonResponse(['ok' => false, 'message' => 'Email wajib diisi'], 400);
    }

    $stmt = $pdo->prepare('SELECT id, email, google_authenticator_secret FROM users WHERE email=:email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (! $user) {
        // Don't reveal if email exists for security
        jsonResponse(['ok' => true, 'message' => 'Jika email terdaftar, link reset password telah dikirim.']);
    }

    // Check if user has Google Authenticator secret
    if (empty($user['google_authenticator_secret'])) {
        jsonResponse(['ok' => false, 'message' => 'Akun Anda belum memiliki Google Authenticator. Silakan hubungi administrator untuk mengatur QR code.'], 400);
    }

    // Generate reset token
    $resetToken = bin2hex(random_bytes(32));
    $resetExpires = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $stmt = $pdo->prepare('UPDATE users SET password_reset_token=:token, password_reset_expires=:expires WHERE id=:id');
    $stmt->execute([
        ':token' => $resetToken,
        ':expires' => $resetExpires,
        ':id' => $user['id'],
    ]);

    // Build reset URL for response (same as email)
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $basePath = dirname($scriptName);
    $basePath = rtrim($basePath, '/');
    if ($basePath === '.') {
        $basePath = '';
    }
    if (! empty($basePath) && $basePath !== '/') {
        $basePath = '/'.ltrim($basePath, '/');
    }
    $resetUrl = $protocol.'://'.$host.$basePath.'/index.php?page=verify-otp&token='.urlencode($resetToken);

    // Try to send email
    $emailSent = @sendPasswordResetEmail($email, $resetToken);

    // Always return success with reset URL for direct redirect
    // Email is optional (for production, configure SMTP properly)
    // Never log reset tokens or OTPs.

    // Return success with token for direct redirect
    jsonResponse([
        'ok' => true,
        'reset_url' => $resetUrl,
        'token' => $resetToken,
        'message' => 'Redirecting to OTP verification...',
    ]);
}

// Verify OTP - Step 2 of forgot password

if ($action === 'verify_otp' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim($_POST['token'] ?? '');
    $otp = trim($_POST['otp'] ?? '');

    if (empty($token) || empty($otp)) {
        jsonResponse(['ok' => false, 'message' => 'Token dan OTP wajib diisi'], 400);
    }

    // Find user by reset token
    $stmt = $pdo->prepare('SELECT id, email, google_authenticator_secret, password_reset_expires FROM users WHERE password_reset_token=:token LIMIT 1');
    $stmt->execute([':token' => $token]);
    $user = $stmt->fetch();

    if (! $user) {
        jsonResponse(['ok' => false, 'message' => 'Token tidak valid atau telah kedaluwarsa'], 400);
    }

    // Check if token expired
    if (strtotime($user['password_reset_expires']) < time()) {
        jsonResponse(['ok' => false, 'message' => 'Token telah kedaluwarsa. Silakan request reset password lagi.'], 400);
    }

    // Verify OTP with Google Authenticator
    if (empty($user['google_authenticator_secret'])) {
        jsonResponse(['ok' => false, 'message' => 'Akun Anda belum memiliki Google Authenticator.'], 400);
    }

    if (! verifyGoogleAuthenticatorOTP($user['google_authenticator_secret'], $otp)) {
        jsonResponse(['ok' => false, 'message' => 'Kode OTP tidak valid. Pastikan kode dari Google Authenticator masih berlaku.'], 400);
    }

    PasswordResetGrant::issue($_SESSION, $token, (int) $user['id']);
    // OTP verified successfully, redirect to reset password page
    jsonResponse(['ok' => true, 'token' => $token, 'message' => 'OTP berhasil diverifikasi. Silakan buat password baru.']);
}

// Reset Password - Step 3 of forgot password

if ($action === 'reset_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim($_POST['token'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (empty($token) || empty($password) || empty($password2)) {
        jsonResponse(['ok' => false, 'message' => 'Semua field wajib diisi'], 400);
    }

    if ($password !== $password2) {
        jsonResponse(['ok' => false, 'message' => 'Konfirmasi password tidak cocok'], 400);
    }

    // Find user by reset token
    $stmt = $pdo->prepare('SELECT id, password_reset_expires FROM users WHERE password_reset_token=:token LIMIT 1');
    $stmt->execute([':token' => $token]);
    $user = $stmt->fetch();

    if (! $user) {
        jsonResponse(['ok' => false, 'message' => 'Token tidak valid atau telah kedaluwarsa'], 400);
    }

    // Check if token expired
    if (strtotime($user['password_reset_expires']) < time()) {
        jsonResponse(['ok' => false, 'message' => 'Token telah kedaluwarsa. Silakan request reset password lagi.'], 400);
    }

    if (! PasswordResetGrant::allows($_SESSION, $token, (int) $user['id'])) {
        jsonResponse(['ok' => false, 'message' => 'Verifikasi OTP diperlukan sebelum mengganti password.'], 403);
    }
    if (strlen($password) < 12 || strlen($password) > 72) {
        jsonResponse(['ok' => false, 'message' => 'Password harus terdiri dari 12–72 karakter.'], 422);
    }
    unset($_SESSION['verified_password_reset']);
    // Update password
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare('UPDATE users SET password=:hash, password_reset_token=NULL, password_reset_expires=NULL WHERE id=:id AND password_reset_token=:token');
    $stmt->execute([
        ':hash' => $hash,
        ':token' => $token,
        ':id' => $user['id'],
    ]);
    if ($stmt->rowCount() !== 1) {
        jsonResponse(['ok' => false, 'message' => 'Token sudah digunakan.'], 400);
    }
    $pdo->prepare('DELETE FROM personal_access_tokens WHERE tokenable_type = ? AND tokenable_id = ?')
        ->execute([User::class, $user['id']]);

    jsonResponse(['ok' => true, 'message' => 'Password berhasil direset. Silakan login dengan password baru.']);
}

// Get Google Authenticator QR Code for member

if ($action === 'get_ga_qr') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    $userId = (int) ($_GET['user_id'] ?? 0);
    if (! $userId) {
        jsonResponse(['ok' => false, 'message' => 'User ID tidak valid'], 400);
    }

    $stmt = $pdo->prepare('SELECT id, email, google_authenticator_secret FROM users WHERE id=:id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();

    if (! $user) {
        jsonResponse(['ok' => false, 'message' => 'User tidak ditemukan'], 404);
    }

    // Generate secret if doesn't exist
    if (empty($user['google_authenticator_secret'])) {
        $secret = generateGoogleAuthenticatorSecret();
        if (! $secret) {
            jsonResponse(['ok' => false, 'message' => 'Gagal menghasilkan secret. Pastikan Google Authenticator library terpasang.'], 500);
        }

        $stmt = $pdo->prepare('UPDATE users SET google_authenticator_secret=:secret WHERE id=:id');
        $stmt->execute([':secret' => $secret, ':id' => $userId]);
    } else {
        $secret = $user['google_authenticator_secret'];
    }

    // Generate QR code URL
    $qrUrl = getGoogleAuthenticatorQRCode($secret, $user['email'], 'Sistem Presensi');

    if (! $qrUrl) {
        jsonResponse(['ok' => false, 'message' => 'Gagal menghasilkan QR code.'], 500);
    }

    jsonResponse(['ok' => true, 'qr_url' => $qrUrl, 'secret' => $secret, 'email' => $user['email']]);
}

if ($action === 'logout') {
    session_destroy();
    jsonResponse(['ok' => true]);
}

if ($action === 'check_session') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }
    $key = $_GET['key'] ?? ($_POST['key'] ?? '');
    if (! $key) {
        jsonResponse(['ok' => false, 'message' => 'key kosong'], 400);
    }
    $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key=:k LIMIT 1');
    $stmt->execute([':k' => $key]);
    $val = $stmt->fetchColumn();
    jsonResponse(['ok' => true, 'value' => $val]);
}
