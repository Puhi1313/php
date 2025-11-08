<?php
session_start();
require_once 'povezava.php';
if (!isset($_SESSION['user_id']) || ($_SESSION['vloga'] !== 'ucitelj' && $_SESSION['vloga'] !== 'admin')) {
    header('Location: login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$message = '';
$error = '';

try {
    $stmt = $pdo->prepare("SELECT * FROM uporabnik WHERE id_uporabnik = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) { throw new Exception('Uporabnik ni najden.'); }
} catch (Exception $e) {
    die('Napaka: ' . $e->getMessage());
}

// Handle POST (only password change and profile picture upload)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $updates = [];
    $params = [];

    // Password change logic
    if ($new_password !== '' || $confirm_password !== '') {
        if ($new_password !== $confirm_password) {
            $error = 'Novo geslo in potrditev se ne ujemata.';
        } else {
            if (empty($current_password)) {
                $error = 'Za menjavo gesla vnesite trenutno geslo.';
            } else {
                if (!password_verify($current_password, $user['geslo'])) {
                    $error = 'Trenutno geslo ni pravilno.';
                } else {
                    $hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $updates[] = "geslo = ?";
                    $params[] = $hash;
                }
            }
        }
    }

    // Profile picture upload -> save to 'slike' and store path in icona_profila
    $profileColumnExists = array_key_exists('icona_profila', $user);
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] !== UPLOAD_ERR_NO_FILE) {
        if (!$profileColumnExists) {
            $error = $error ?: 'Profilna slika ni podprta v bazi (stik z adminom).';
        } else {
            $file = $_FILES['profile_pic'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error = $error ?: 'Napaka pri nalaganju datoteke.';
            } else {
                $maxSize = 2 * 1024 * 1024; // 2MB
                $allowedTypes = ['image/jpeg','image/png','image/gif'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                if (!in_array($mime, $allowedTypes)) {
                    $error = $error ?: 'Dovoljene so samo JPEG/PNG/GIF slike.';
                } elseif ($file['size'] > $maxSize) {
                    $error = $error ?: 'Datoteka je prevelika (maks. 2MB).';
                } else {
                    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'slike';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    if ($ext === '') {
                        $ext = $mime === 'image/png' ? 'png' : ($mime === 'image/gif' ? 'gif' : 'jpg');
                    }
                    $filename = 'icona_' . $user_id . '_' . time() . '.' . $ext;
                    $target = $uploadDir . DIRECTORY_SEPARATOR . $filename;
                    if (move_uploaded_file($file['tmp_name'], $target)) {
                        $relative = 'slike/' . $filename;
                        $updates[] = "icona_profila = ?";
                        $params[] = $relative;
                        if (!empty($user['icona_profila'])) {
                            $old = __DIR__ . DIRECTORY_SEPARATOR . $user['icona_profila'];
                            if (file_exists($old) && strpos(realpath($old), realpath(__DIR__)) === 0) {
                                @unlink($old);
                            }
                        }
                    } else {
                        $error = $error ?: 'Prenos datoteke ni uspel.';
                    }
                }
            }
        }
    }

    if ($error === '') {
        try {
            if (count($updates) > 0) {
                $sql = "UPDATE uporabnik SET " . implode(', ', $updates) . " WHERE id_uporabnik = ?";
                $params[] = $user_id;
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $message = 'Podatki posodobljeni.';
                $stmt = $pdo->prepare("SELECT * FROM uporabnik WHERE id_uporabnik = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $message = 'Ni sprememb za shraniti.';
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $error = 'E‑mail naslov že obstaja.';
            } else {
                $error = 'Napaka baze: ' . $e->getMessage();
                error_log('ucitelj_profile update error: ' . $e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="utf-8">
    <title>Moj profil - Učitelj</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Momo+Trust+Display&family=Raleway:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f7f8f3;
            --card: #ffffff;
            --accent: #596235; /* olive green matching teacher classroom */
            --accent-soft: #cdcdb6;
            --muted: #6b6b6b;
        }
        body{font-family:"Raleway", sans-serif;background:var(--bg);padding:24px;color:#596235;}
        .card{max-width:820px;margin:24px auto;background:var(--card);padding:22px;border-radius:12px;box-shadow:0 6px 20px rgba(89,98,53,0.08);border:1px solid var(--accent-soft);}
        .top{display:flex;gap:18px;align-items:center}
        .profile-pic{width:120px;height:120px;border-radius:50%;object-fit:cover;border:4px solid var(--accent-soft);background:var(--accent-soft)}
        h2{margin:0 0 6px 0;color:var(--accent)}
        .sub{color:var(--muted);margin:6px 0 0 0}
        .controls{margin-top:18px;display:flex;gap:18px;flex-wrap:wrap}
        .box{flex:1;min-width:260px;background:#f8f8f0;border:1px solid var(--accent-soft);padding:14px;border-radius:10px}
        label{display:block;margin-top:8px;font-weight:600;color:#596235}
        input[type="password"], input[type="file"]{width:100%;padding:10px;margin-top:8px;border:1px solid var(--accent-soft); border-radius:8px;background:#fff;font-family:"Raleway", sans-serif;color:#596235}
        .help{font-size:0.9rem;color:var(--muted);margin-top:6px}
        .btn{display:inline-block;padding:10px 14px;background:var(--accent);color:#fff;border-radius:8px;border:none;cursor:pointer;margin-top:12px;font-family:"Raleway", sans-serif;font-weight:500;transition:background 0.3s}
        .btn:hover{background:#4a5230}
        .msg{padding:10px;border-radius:8px;margin-bottom:12px}
        .success{background:#e9f8ef;color:#0b603a;border:1px solid #bfe6cc}
        .error{background:#fff1f1;color:#8a1a1a;border:1px solid #ffcccc}
        .readonly-field{font-weight:600;padding:8px 10px;border-radius:8px;background:#f8f8f0;border:1px solid var(--accent-soft);display:inline-block}
        a.back{color:var(--accent);text-decoration:none;font-weight:600}
        a.back:hover{text-decoration:underline}
        @media(max-width:720px){ .top{flex-direction:column;align-items:flex-start} .controls{flex-direction:column} }
    </style>
</head>
<body>
<div class="card">
    <div class="top">
        <div>
            <?php if (!empty($user['icona_profila']) && file_exists(__DIR__ . DIRECTORY_SEPARATOR . $user['icona_profila'])): ?>
                <img src="<?php echo htmlspecialchars($user['icona_profila']); ?>" class="profile-pic" alt="Profilna slika">
            <?php else: ?>
                <div class="profile-pic" style="display:flex;align-items:center;justify-content:center;color:#fff;background:var(--accent);font-weight:700;font-size:2em">
                    <?php echo strtoupper(substr($user['ime'],0,1) . substr($user['priimek'],0,1)); ?>
                </div>
            <?php endif; ?>
        </div>
        <div>
            <h2><?php echo htmlspecialchars($user['ime'] . ' ' . $user['priimek']); ?></h2>
            <div class="sub"><?php echo htmlspecialchars($user['email']); ?></div>
            <div class="help" style="margin-top:8px">Vidite svoje podatke. Imena in e‑mail ne morete spreminjati tukaj. Za spremembe kontaktirajte administracijo.</div>
        </div>
    </div>

    <?php if ($message): ?><div class="msg success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="msg error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="controls">
        <div class="box">
            <h3 style="margin:0;color:var(--accent)">Profilna slika</h3>
            <p class="help">Naložite novo profilno sliko (JPEG/PNG/GIF, max 2MB). Slika se shrani v mapo "slike".</p>
            <?php if (array_key_exists('icona_profila', $user)): ?>
                <form method="post" enctype="multipart/form-data" id="picForm">
                    <label for="profile_pic">Izberi datoteko</label>
                    <input type="file" name="profile_pic" id="profile_pic" accept="image/*">
                    <div style="display:flex;gap:10px;align-items:center;">
                        <button type="submit" class="btn">Naloži sliko</button>
                        <button type="button" class="btn" style="background:#80852f" onclick="document.getElementById('profile_pic').value=''">Prekliči</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="help">Profilna slika ni na voljo (baza nima stolpca icona_profila).</div>
            <?php endif; ?>
        </div>

        <div class="box">
            <h3 style="margin:0;color:var(--accent)">Spremeni geslo</h3>
            <p class="help">Za spremembo gesla vnesite trenutno in novo geslo.</p>
            <form method="post" id="pwdForm">
                <label>Trenutno geslo</label>
                <input type="password" name="current_password" autocomplete="current-password" required>
                <label>Novo geslo</label>
                <input type="password" name="new_password" autocomplete="new-password" required>
                <label>Potrdi novo geslo</label>
                <input type="password" name="confirm_password" autocomplete="new-password" required>
                <button type="submit" class="btn">Shrani geslo</button>
            </form>
        </div>
    </div>

    <div style="margin-top:18px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;">
        <div class="help">Če želite spremeniti ime ali e‑mail, se obrnite na administracijo.</div>
        <div><a class="back" href="ucitelj_ucilnica.php">← Nazaj v učilnico</a></div>
    </div>
</div>

<script>
    // Keep forms separate so user can upload picture or change password independently.
    // No JS required beyond basic; page posts full form. If both fields filled, server will process both.
</script>
</body>
</html>

