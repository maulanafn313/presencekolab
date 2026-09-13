<?php

// Extracted from ajax_handler.php — action group: member
// Variables in scope: $pdo, $action, $archivedExcludeQuery, $archivedExcludeUsersQuery

if ($action === 'get_members') {
    $light = ($_GET['light'] ?? '0') === '1';
    $noEmbeddings = ($_GET['no_embeddings'] ?? '0') === '1';

    $fields = "id, role, email, nim, nama, prodi, startup, (CASE WHEN foto_base64 IS NOT NULL AND foto_base64 != '' THEN 1 ELSE 0 END) as has_foto";
    if (! $light) {
        $fields .= ', foto_base64';
    } else {
        // Optimization: Only return photo if embedding is missing OR seems incompatible (e.g. 512-dim)
        // A 128-dim JSON array is usually < 3000 chars. 512-dim is much larger (> 7000 chars).
        // Optimization: Only return photo if 128-dim embedding is missing
        $fields .= ", (CASE WHEN face_embedding_128 IS NULL OR face_embedding_128 = '' THEN foto_base64 ELSE NULL END) as foto_base64";
    }

    if (! $noEmbeddings) {
        // Prefer the 128-dim embedding for frontend performance
        $fields .= ', face_embedding_128 as face_embedding';
    }

    $selfFilter = isAdmin() ? '' : ' AND id = '.(int) $_SESSION['user']['id'];
    $stmt = $pdo->query("SELECT $fields FROM users WHERE role='pegawai' AND $archivedExcludeUsersQuery $selfFilter");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ensure foto_base64 is a valid URL or base64 data + Pre-crop for 20x speed
    foreach ($rows as &$row) {
        if (! empty($row['foto_base64']) && strpos($row['foto_base64'], 'data:') !== 0 && strpos($row['foto_base64'], 'http') !== 0) {
            $filename = $row['foto_base64'];
            $photoPath = '';
            $paths = array_filter([\App\Services\PrivateMedia::path($filename)]);
            foreach ($paths as $p) {
                if (file_exists($p)) {
                    $photoPath = $p;
                    break;
                }
            }
            if ($photoPath && file_exists($photoPath)) {
                try {
                    $img = null;
                    $ext = strtolower(pathinfo($photoPath, PATHINFO_EXTENSION));
                    if ($ext == 'jpg' || $ext == 'jpeg') {
                        $img = @imagecreatefromjpeg($photoPath);
                    } elseif ($ext == 'png') {
                        $img = @imagecreatefrompng($photoPath);
                    }
                    if ($img) {
                        $w = imagesx($img);
                        $h = imagesy($img);
                        $size = min($w, $h);
                        $crop = imagecreatetruecolor(160, 160);
                        imagecopyresampled($crop, $img, 0, 0, ($w - $size) / 2, ($h - $size) / 2, 160, 160, $size, $size);
                        ob_start();
                        imagejpeg($crop, null, 60);
                        $row['foto_base64'] = 'data:image/jpeg;base64,'.base64_encode(ob_get_clean());
                        imagedestroy($img);
                        imagedestroy($crop);
                    } else {
                        $row['foto_base64'] = 'data:image/'.$ext.';base64,'.base64_encode(file_get_contents($photoPath));
                    }
                } catch (Exception $e) {
                }
            }
        }
    }
    jsonResponse(['ok' => true, 'data' => $rows]);

    return; // Skip old loop

    // Ensure foto_base64 is a valid URL or base64 data
    foreach ($rows as &$row) {
        if (! empty($row['foto_base64']) && strpos($row['foto_base64'], 'data:') !== 0 && strpos($row['foto_base64'], 'http') !== 0) {
            // If it's just a filename, try to convert to base64 for reliability
            $filename = $row['foto_base64'];
            $found = false;

            // Try absolute paths via Laravel helpers first
            $paths = [];
            if (function_exists('storage_path')) {
                $paths[] = storage_path('app/public/users/'.$filename);
            }
            if (function_exists('public_path')) {
                $paths[] = public_path('storage/users/'.$filename);
            }

            // Fallback to relative path from this file
            $paths[] = storage_path('app/public/users/'.$filename);

            foreach ($paths as $filePath) {
                if (file_exists($filePath)) {
                    $type = pathinfo($filePath, PATHINFO_EXTENSION);
                    $data = @file_get_contents($filePath);
                    if ($data) {
                        $row['foto_base64'] = 'data:image/'.($type === 'jpg' ? 'jpeg' : $type).';base64,'.base64_encode($data);
                        $found = true;
                        break;
                    }
                }
            }

            if (! $found) {
                // Final fallback to URL if file not found locally
                $row['foto_base64'] = '/storage/users/'.$filename;
            }
        }
    }

    jsonResponse(['ok' => true, 'data' => $rows]);
}

if ($action === 'get_member_photo') {
    // API Protection: Restrict public access to same-origin requests
    if (empty($_SESSION['user']['id'])) {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (empty($referer) || strpos($referer, $host) === false) {
            jsonResponse(['error' => 'Forbidden access to member photo. Protected API.'], 403);
        }
    }

    $id = (int) ($_GET['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT foto_base64, nama FROM users WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();

    if ($row) {
        $img = getAvatarUrl($row['foto_base64'], $row['nama']);
        jsonResponse(['ok' => true, 'image' => $img]);
    } else {
        jsonResponse(['ok' => false]);
    }
}

if ($action === 'save_member' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    try {
        $id = $_POST['id'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $nim = trim($_POST['nim'] ?? '');
        $nama = trim($_POST['nama'] ?? '');
        $prodi = trim($_POST['prodi'] ?? '');
        $startup = trim($_POST['startup'] ?? '');
        $foto = $_POST['foto'] ?? null;

        if ($id) {
            // Update existing by id
            $user = $pdo->prepare("SELECT id, email, nim FROM users WHERE id=:id AND role='pegawai'");
            $user->execute([':id' => $id]);
            $currentUser = $user->fetch();
            if (! $currentUser) {
                jsonResponse(['ok' => false, 'message' => 'Member tidak ditemukan'], 404);
            }

            // Check if email is being changed and if it's unique
            if ($email && $email !== $currentUser['email']) {
                $checkEmail = $pdo->prepare('SELECT id FROM users WHERE email=:email AND id!=:id LIMIT 1');
                $checkEmail->execute([':email' => $email, ':id' => $id]);
                if ($checkEmail->fetch()) {
                    jsonResponse(['ok' => false, 'message' => 'Email sudah digunakan oleh member lain'], 400);
                }
            }

            // Check if nim is being changed and if it's unique
            if ($nim && $nim !== $currentUser['nim']) {
                $checkNim = $pdo->prepare('SELECT id FROM users WHERE nim=:nim AND id!=:id LIMIT 1');
                $checkNim->execute([':nim' => $nim, ':id' => $id]);
                if ($checkNim->fetch()) {
                    jsonResponse(['ok' => false, 'message' => 'NIM sudah digunakan oleh member lain'], 400);
                }
            }

            // Check image size if updating photo (max 1MB)
            if ($foto && ! checkImageSize($foto, 1)) {
                jsonResponse(['ok' => false, 'message' => 'Ukuran foto terlalu besar. Maksimal 1MB. Silakan kompres foto atau gunakan foto dengan resolusi lebih kecil.'], 400);
            }

            // Build update query with email and nim
            $params = [':nama' => $nama, ':prodi' => $prodi, ':startup' => $startup ?: null, ':id' => $id];
            $setParts = ['nama=:nama', 'prodi=:prodi', 'startup=:startup'];

            if ($email) {
                $setParts[] = 'email=:email';
                $params[':email'] = $email;
            }

            if ($nim) {
                $setParts[] = 'nim=:nim';
                $params[':nim'] = $nim;
            }

            if ($foto) {
                $foto = saveBase64Image($foto, 'users');
                $setParts[] = 'foto_base64=:foto';
                $params[':foto'] = $foto;

                $embedding = $_POST['embedding'] ?? null;
                $landmarks = $_POST['landmarks'] ?? null;
                if ($embedding) {
                    $setParts[] = 'face_embedding_128=:embedding';
                    $params[':embedding'] = $embedding;
                    if ($landmarks) {
                        $setParts[] = 'face_landmarks=:landmarks';
                        $params[':landmarks'] = $landmarks;
                    }
                } else {
                    $setParts[] = 'face_embedding_128=NULL';
                    $setParts[] = 'face_landmarks=NULL';
                }
            }

            $sql = 'UPDATE users SET '.implode(', ', $setParts).' WHERE id=:id';
            $pdo->prepare($sql)->execute($params);

            // OPTIMIZED: Backup trigger removed from frequent operations
            // triggerDatabaseBackup(); // Backup happens on schedule instead

            jsonResponse(['ok' => true]);
        } else {
            // Create new
            if (! $nim || ! $nama || ! $prodi || ! $foto) {
                jsonResponse(['ok' => false, 'message' => 'Field wajib belum lengkap'], 400);
            }

            // Check image size (max 1MB)
            if (! checkImageSize($foto, 1)) {
                jsonResponse(['ok' => false, 'message' => 'Ukuran foto terlalu besar. Maksimal 1MB. Silakan kompres foto atau gunakan foto dengan resolusi lebih kecil.'], 400);
            }
            $check = $pdo->prepare('SELECT id FROM users WHERE email=:email OR nim=:nim LIMIT 1');
            $email = trim($_POST['email'] ?? '');
            $check->execute([':email' => $email, ':nim' => $nim]);
            if ($check->fetch()) {
                jsonResponse(['ok' => false, 'message' => 'Email atau NIM sudah terdaftar'], 400);
            }
            $password = $_POST['password'] ?? '';
            if (! $email || ! $password) {
                jsonResponse(['ok' => false, 'message' => 'Email dan password wajib untuk member baru'], 400);
            }
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $foto = saveBase64Image($foto, 'users');

            $embedding = $_POST['embedding'] ?? null;
            $landmarks = $_POST['landmarks'] ?? null;

            $stmt = $pdo->prepare("INSERT INTO users (role, email, nim, nama, prodi, startup, foto_base64, password, face_embedding_128, face_landmarks) VALUES ('pegawai', :email, :nim, :nama, :prodi, :startup, :foto, :hash, :embedding, :landmarks)");
            $stmt->execute([
                ':email' => $email,
                ':nim' => $nim,
                ':nama' => $nama,
                ':prodi' => $prodi,
                ':startup' => $startup ?: null,
                ':foto' => $foto,
                ':hash' => $hash,
                ':embedding' => $embedding ?: null,
                ':landmarks' => $landmarks ?: null,
            ]);

            // Trigger backup setelah menambah user baru
            triggerDatabaseBackup();

            jsonResponse(['ok' => true]);
        }
    } catch (PDOException $e) {
        error_log('Database error in save_member: '.$e->getMessage());
        jsonResponse(['error' => 'Gagal menyimpan data member'], 500);
    } catch (Exception $e) {
        error_log('Error in save_member: '.$e->getMessage());
        jsonResponse(['error' => 'Terjadi kesalahan'], 500);
    }
}

if ($action === 'delete_member' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }
    $id = (int) ($_POST['id'] ?? 0);
    $pdo->prepare("DELETE FROM users WHERE id=:id AND role='pegawai'")->execute([':id' => $id]);

    // Trigger backup setelah menghapus user
    triggerDatabaseBackup();

    jsonResponse(['ok' => true]);
}

if ($action === 'get_user_info') {
    if (! isset($_SESSION['user'])) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }
    $uid = (int) $_SESSION['user']['id'];
    $stmt = $pdo->prepare('SELECT id, nim, nama, prodi, startup FROM users WHERE id=:id');
    $stmt->execute([':id' => $uid]);
    jsonResponse(['ok' => true, 'data' => $stmt->fetch()]);
}

if ($action === 'get_current_user_descriptor') {
    $nim = $_SESSION['user']['nim'] ?? null;
    if (! $nim) {
        jsonResponse(['error' => 'Not authenticated'], 401);
    }
    $stmt = $pdo->prepare('SELECT nim, nama, foto_base64 FROM users WHERE nim = :nim LIMIT 1');
    $stmt->execute([':nim' => $nim]);
    $user = $stmt->fetch();
    if (! $user) {
        jsonResponse(['error' => 'User not found'], 404);
    }
    jsonResponse(['ok' => true, 'data' => $user]);
}

// Admin/Employee/Public: get settings (Read-only access for UI rendering and face recognition config)
