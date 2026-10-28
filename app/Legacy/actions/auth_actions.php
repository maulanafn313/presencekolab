<?php

use App\Models\User;
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

// User mengisi password baru sendiri; password baru baru aktif setelah admin menyetujui
// (admin_approve_password_reset). Respons sama untuk email terdaftar maupun tidak.
if ($action === 'forgot_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    if ($email === '' || $password === '' || $password2 === '') {
        jsonResponse(['ok' => false, 'message' => 'Email dan password baru wajib diisi'], 400);
    }
    if ($password !== $password2) {
        jsonResponse(['ok' => false, 'message' => 'Konfirmasi password tidak cocok'], 400);
    }
    if (strlen($password) < 6 || strlen($password) > 72) {
        jsonResponse(['ok' => false, 'message' => 'Password harus terdiri dari 6–72 karakter.'], 422);
    }

    $stmt = $pdo->prepare('UPDATE users SET pending_password=:hash, reset_requested_at=NOW() WHERE email=:email');
    $stmt->execute([':hash' => password_hash($password, PASSWORD_BCRYPT), ':email' => $email]);

    jsonResponse(['ok' => true, 'message' => 'Jika email terdaftar, permintaan ganti password sudah dikirim. Password baru aktif setelah disetujui admin.']);
}

if (in_array($action, ['admin_approve_password_reset', 'admin_reject_password_reset'], true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'admin_approve_password_reset') {
        $stmt = $pdo->prepare('UPDATE users SET password=pending_password, pending_password=NULL, reset_requested_at=NULL WHERE id=:id AND pending_password IS NOT NULL');
        $stmt->execute([':id' => $id]);
        if ($stmt->rowCount() !== 1) {
            jsonResponse(['ok' => false, 'message' => 'Tidak ada permintaan reset password untuk member ini.'], 404);
        }
        $pdo->prepare('DELETE FROM personal_access_tokens WHERE tokenable_type = ? AND tokenable_id = ?')
            ->execute([User::class, $id]);
        jsonResponse(['ok' => true, 'message' => 'Password baru member sudah aktif.']);
    }
    $pdo->prepare('UPDATE users SET pending_password=NULL, reset_requested_at=NULL WHERE id=:id')->execute([':id' => $id]);
    jsonResponse(['ok' => true, 'message' => 'Permintaan reset password ditolak.']);
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
