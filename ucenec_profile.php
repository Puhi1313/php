<?php
session_start();
require_once 'povezava.php';
if (!isset($_SESSION['user_id']) || $_SESSION['vloga'] !== 'ucenec') {
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
        } 
        // DODAN PREVERJANJE DOLŽINE
        else if (strlen($new_password) < 12) {
            $error = 'Novo geslo mora imeti vsaj 12 znakov.';
        } 
        // KONEC DODATNEGA PREVERJANJA
        else {
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
                error_log('ucenec_profile update error: ' . $e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="utf-8">
    <title>Moj profil - Učenec</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Momo+Trust+Display&family=Raleway:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <style>
        html { color: #596235; }
        body {
            margin: 0;
            padding: 24px;
            font-family: "Raleway", sans-serif;
            background:
                radial-gradient(900px 500px at 10% -10%, rgba(205, 205, 182, 0.65), rgba(205, 205, 182, 0) 70%),
                radial-gradient(900px 500px at 110% 10%, rgba(128, 133, 47, 0.18), rgba(128, 133, 47, 0) 60%),
                linear-gradient(180deg, #f7f8f3 0%, #eff1e4 45%, #e3e6d1 100%);
            background-attachment: fixed;
            color: #596235;
            min-height: 100vh;
        }
        .card {
            max-width: 900px;
            margin: 24px auto;
            background: #fff;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            border: 1px solid #cdcdb6;
        }
        .top {
            display: flex;
            gap: 24px;
            align-items: center;
            padding-bottom: 20px;
            border-bottom: 2px solid #cdcdb6;
            margin-bottom: 24px;
        }
        .profile-pic {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            background: #cdcdb6;
        }
        h2 {
            margin: 0 0 8px 0;
            color: #596235;
            font-size: 28px;
        }
        .sub {
            color: #6c7450;
            margin: 4px 0 0 0;
            font-size: 16px;
        }
        .controls {
            margin-top: 24px;
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        .box {
            flex: 1;
            min-width: 280px;
            background: #f8f8f0;
            border: 1px solid #cdcdb6;
            padding: 20px;
            border-radius: 12px;
            transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.2s ease;
        }
        .box:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.07);
            border-color: #80852f;
        }
        .box h3 {
            margin: 0 0 12px 0;
            color: #596235;
            font-size: 20px;
            border-bottom: 2px solid #cdcdb6;
            padding-bottom: 8px;
        }
        label {
            display: block;
            margin-top: 12px;
            font-weight: 600;
            color: #596235;
        }
        input[type="password"], 
        input[type="file"] {
            width: 100%;
            padding: 10px;
            margin-top: 8px;
            border: 1px solid #cdcdb6;
            border-radius: 8px;
            background: #fff;
            font-family: "Raleway", sans-serif;
            color: #596235;
            transition: border-color 0.2s, box-shadow 0.2s;
            box-sizing: border-box;
        }
        input[type="password"]:focus,
        input[type="file"]:focus {
            outline: none;
            border-color: #80852f;
            box-shadow: 0 0 0 3px rgba(128, 133, 47, 0.1);
        }
        .help {
            font-size: 0.9rem;
            color: #6c7450;
            margin-top: 8px;
            line-height: 1.5;
        }
        .btn {
            display: inline-block;
            padding: 10px 18px;
            background: #596235;
            color: #fff;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            margin-top: 12px;
            font-family: "Raleway", sans-serif;
            font-weight: 500;
            transition: background 0.3s, transform 0.15s ease, box-shadow 0.15s ease;
        }
        .btn:hover {
            background: #4a5230;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .btn-secondary {
            background: #80852f;
        }
        .btn-secondary:hover {
            background: #6a6f26;
        }
        .msg {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid;
        }
        .success {
            background: #e9f8ef;
            color: #0b603a;
            border-color: #bfe6cc;
        }
        .error {
            background: #fff1f1;
            color: #8a1a1a;
            border-color: #ffcccc;
        }
        a.back {
            color: #596235;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s, text-decoration 0.2s;
        }
        a.back:hover {
            text-decoration: underline;
            color: #80852f;
        }
        .button-group {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        /* Drag & Drop area */
        .dropzone {
            position: relative;
            border: 2px dashed #cdcdb6;
            border-radius: 14px;
            background: #f8f8f0;
            padding: 26px;
            text-align: center;
            color: #596235;
            transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
            margin-top: 8px;
            margin-bottom: 15px;
            cursor: pointer;
        }
        .dropzone:hover { 
            box-shadow: 0 6px 16px rgba(0,0,0,0.06); 
        }
        .dropzone.dragover {
            border-color: #80852f;
            background: #eef0e1;
        }
        .dropzone .dz-icon { 
            font-size: 42px; 
            margin-bottom: 10px; 
            display: block; 
        }
        .dropzone .dz-title { 
            font-weight: 700; 
            margin: 6px 0; 
            color: #596235;
        }
        .dropzone .dz-sub { 
            color: #6c7450; 
            margin-bottom: 12px; 
        }
        .dropzone .dz-browse { 
            padding: 8px 15px; 
            background: #596235; 
            color: #fff; 
            border: none; 
            border-radius: 6px; 
            cursor: pointer; 
            transition: background 0.3s, transform 0.15s ease; 
            font-family: "Raleway", sans-serif; 
            font-size: 14px; 
            font-weight: 500;
        }
        .dropzone .dz-browse:hover { 
            background: #4a5230; 
            transform: translateY(-1px); 
        }
        .dropzone .dz-file-name { 
            margin-top: 8px; 
            font-size: 14px; 
            color: #6c7450; 
        }
        input[type="file"] {
            display: none;
        }
        @media(max-width: 720px) {
            .top {
                flex-direction: column;
                align-items: flex-start;
            }
            .controls {
                flex-direction: column;
            }
            .card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
<div class="card">
    <div class="top">
        <div>
            <?php if (!empty($user['icona_profila']) && file_exists(__DIR__ . DIRECTORY_SEPARATOR . $user['icona_profila'])): ?>
                <img src="<?php echo htmlspecialchars($user['icona_profila']); ?>" class="profile-pic" alt="Profilna slika">
            <?php else: ?>
                <div class="profile-pic" style="display:flex;align-items:center;justify-content:center;color:#fff;background:#596235;font-weight:700;font-size:2.5em">
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
            <h3>Profilna slika</h3>
            <p class="help">Naložite novo profilno sliko (JPEG/PNG/GIF, max 2MB). Slika se shrani v mapo "slike".</p>
            <?php if (array_key_exists('icona_profila', $user)): ?>
                <form method="post" enctype="multipart/form-data" id="picForm">
                    <div id="dz-profile" class="dropzone">
                        <span class="dz-icon">☁️</span>
                        <div class="dz-title">Povlecite in spustite sliko sem</div>
                        <div class="dz-sub">ALI</div>
                        <button type="button" class="dz-browse">Izberite sliko</button>
                        <div class="dz-file-name" id="dz-profile-filename">Ni izbrane slike</div>
                        <input type="file" name="profile_pic" id="profile_pic" accept="image/*">
                    </div>
                    <div class="button-group">
                        <button type="submit" class="btn">Naloži sliko</button>
                        <button type="button" class="btn btn-secondary" onclick="resetProfilePic()">Prekliči</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="help">Profilna slika ni na voljo (baza nima stolpca icona_profila).</div>
            <?php endif; ?>
        </div>

        <div class="box">
            <h3>Spremeni geslo</h3>
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
        <div><a class="back" href="ucenec_ucilnica.php">← Nazaj v učilnico</a></div>
    </div>
</div>

<script>
    // Drag & Drop for profile picture
    function initDropzone({ wrapperId, inputId, fileNameId }) {
        const wrapper = document.getElementById(wrapperId);
        const input = document.getElementById(inputId);
        const fileName = document.getElementById(fileNameId);
        if (!wrapper || !input) return;

        const browseBtn = wrapper.querySelector('.dz-browse');

        // Click to open dialog
        if (browseBtn) browseBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            input.click();
        });
        wrapper.addEventListener('click', (e) => {
            if (e.target.classList.contains('dz-browse')) return;
            input.click();
        });

        ['dragenter','dragover'].forEach(evt => {
            wrapper.addEventListener(evt, (e) => {
                e.preventDefault();
                e.stopPropagation();
                wrapper.classList.add('dragover');
            });
        });
        ['dragleave','dragend','drop'].forEach(evt => {
            wrapper.addEventListener(evt, (e) => {
                e.preventDefault();
                e.stopPropagation();
                wrapper.classList.remove('dragover');
            });
        });

        wrapper.addEventListener('drop', (e) => {
            if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                updateDzFileName();
            }
        });

        input.addEventListener('change', updateDzFileName);

        function updateDzFileName() {
            if (!fileName) return;
            if (input.files && input.files.length) {
                const names = Array.from(input.files).map(f => f.name).join(', ');
                fileName.textContent = names;
            } else {
                fileName.textContent = 'Ni izbrane slike';
            }
        }
    }

    function resetProfilePic() {
        const input = document.getElementById('profile_pic');
        const fileName = document.getElementById('dz-profile-filename');
        if (input) input.value = '';
        if (fileName) fileName.textContent = 'Ni izbrane slike';
    }

    // Initialize dropzone on page load
    document.addEventListener('DOMContentLoaded', () => {
        initDropzone({
            wrapperId: 'dz-profile',
            inputId: 'profile_pic',
            fileNameId: 'dz-profile-filename'
        });
    });
</script>
</body>
</html>