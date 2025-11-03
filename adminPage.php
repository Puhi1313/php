<?php
session_start();
require_once 'povezava.php';

// 1. ZAŠČITA: Samo za admina
if (!isset($_SESSION['user_id']) || $_SESSION['vloga'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$admin_id = $_SESSION['user_id'];
$uporabniki = [];
$error_message = '';

// Priprava filtrov za iskanje in vloge (pending, ucitelj, ucenec)
$search_query = trim($_GET['s'] ?? '');
$vloga_filter = $_GET['vloga'] ?? 'all'; // all, ucitelj, ucenec, pending

// Konstrukcija SQL poizvedbe
$sql = "SELECT id_uporabnik, ime, priimek, email, vloga, status, prvi_vpis 
        FROM uporabnik 
        WHERE id_uporabnik != :admin_id"; // Ne prikažemo samega sebe
$params = ['admin_id' => $admin_id];

// DODAJANJE ISKALNEGA FILTRA - POPRAVLJENO
if (!empty($search_query)) {
    // Uporabimo edinstvena imena parametrov za vsako pojavitev v poizvedbi
    $sql .= " AND (ime LIKE :search_ime OR priimek LIKE :search_priimek OR email LIKE :search_email)";
    
    $search_param_value = '%' . $search_query . '%';
    // Vrednost parametra dodamo pod edinstvenimi ključi
    $params['search_ime'] = $search_param_value;
    $params['search_priimek'] = $search_param_value;
    $params['search_email'] = $search_param_value;
}

// Dodajanje filtra po vlogi/statusu
if ($vloga_filter === 'ucitelj' || $vloga_filter === 'ucenec') {
    $sql .= " AND vloga = :vloga_filter";
    $params['vloga_filter'] = $vloga_filter;
} elseif ($vloga_filter === 'pending') {
    $sql .= " AND status = 'pending'";
    // Pri tem filtru ne dodajamo parametra, ker je vrednost hardkodirana.
}

$sql .= " ORDER BY priimek ASC, ime ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params); // Sedaj se število in imena parametrov ujemajo
    $uporabniki = $stmt->fetchAll();
} catch (\PDOException $e) {
    $error_message = "Napaka pri pridobivanju podatkov: " . $e->getMessage();
    error_log("Database Error on adminPage: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel - Uporabniki</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
        header { background: #333; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        header a { color: #f9f9f9; text-decoration: none; margin-left: 20px; }
        .container { max-width: 1200px; margin: 20px auto; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { border-bottom: 2px solid #ccc; padding-bottom: 10px; }
        .controls { display: flex; gap: 20px; margin-bottom: 20px; align-items: center; }
        .controls input[type="text"], .controls select { padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        .controls button { padding: 8px 15px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #eee; }
        .edit-btn { background: #28a745; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; }
        .edit-btn:hover { background: #218838; }
        .status-pending { background-color: #ffc107; color: #333; padding: 3px 6px; border-radius: 3px; font-size: 0.8em; }
        .status-active { background-color: #28a745; color: white; padding: 3px 6px; border-radius: 3px; font-size: 0.8em; }
        .error-message { color: red; font-weight: bold; }

        /* Modal stil */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }
        .modal-content { background-color: #fefefe; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 500px; border-radius: 8px; }
        .close-btn { color: #aaa; float: right; font-size: 28px; font-weight: bold; }
        .close-btn:hover, .close-btn:focus { color: black; text-decoration: none; cursor: pointer; }
        .modal-content label { display: block; margin-top: 10px; font-weight: bold; }
        .modal-content input, .modal-content select { width: 100%; padding: 8px; margin-top: 5px; box-sizing: border-box; }
        .modal-content button { margin-top: 20px; padding: 10px 15px; }
    </style>
</head>
<body>

<header>
    <div class="logo">ADMIN PANEL</div>
    <nav>
        <a href="ucilnicaPage.php">Učilnica</a>
        <a href="logout.php">Odjava</a>
    </nav>
</header>

<div class="container">
    <h1>Administracija Uporabnikov</h1>

    <?php if ($error_message): ?>
        <p class="error-message"><?= htmlspecialchars($error_message) ?></p>
    <?php endif; ?>

    <div class="controls">
        <form method="GET" action="adminPage.php">
            <input type="text" name="s" placeholder="Ime, Priimek ali E-mail" value="<?= htmlspecialchars($search_query) ?>">
            <select name="vloga">
                <option value="all" <?= $vloga_filter == 'all' ? 'selected' : '' ?>>Vsi uporabniki</option>
                <option value="pending" <?= $vloga_filter == 'pending' ? 'selected' : '' ?>>Pending (čakanje)</option>
                <option value="ucitelj" <?= $vloga_filter == 'ucitelj' ? 'selected' : '' ?>>Učitelji</option>
                <option value="ucenec" <?= $vloga_filter == 'ucenec' ? 'selected' : '' ?>>Učenci</option>
            </select>
            <button type="submit">Išči / Filtriraj</button>
        </form>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Slika</th>
                <th>Ime in Priimek</th>
                <th>E-mail</th>
                <th>Vloga</th>
                <th>Status</th>
                <th>Prvi vpis</th>
                <th>Akcija</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($uporabniki)): ?>
                <tr><td colspan="8" style="text-align: center;">Ni najdenih uporabnikov po izbranem filtru.</td></tr>
            <?php else: ?>
                <?php foreach ($uporabniki as $uporabnik): ?>
                    <tr id="user-row-<?= htmlspecialchars($uporabnik['id_uporabnik']) ?>">
                        <td><?= htmlspecialchars($uporabnik['id_uporabnik']) ?></td>
                        <td style="width:80px;">
                            <?php if (!empty($uporabnik['icona_profila']) && file_exists(__DIR__ . DIRECTORY_SEPARATOR . $uporabnik['icona_profila'])): ?>
                                <img src="<?= htmlspecialchars($uporabnik['icona_profila']) ?>" alt="slika" style="width:48px;height:48px;border-radius:50%;object-fit:cover;border:1px solid #ddd">
                            <?php else: ?>
                                <div style="width:48px;height:48px;border-radius:50%;background:#eee;display:flex;align-items:center;justify-content:center;color:#666;font-weight:700;">
                                    <?= strtoupper(substr($uporabnik['ime'],0,1).substr($uporabnik['priimek'],0,1)) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($uporabnik['ime'] . ' ' . $uporabnik['priimek']) ?></td>
                        <td><?= htmlspecialchars($uporabnik['email']) ?></td>
                        <td><?= htmlspecialchars(ucfirst($uporabnik['vloga'])) ?></td>
                        <td>
                            <span class="status-<?= $uporabnik['status'] ?>">
                                <?= htmlspecialchars(ucfirst($uporabnik['status'])) ?>
                            </span>
                        </td>
                        <td><?= $uporabnik['prvi_vpis'] == 1 ? 'DA' : 'NE' ?></td>
                        <td>
                            <button class="edit-btn" onclick="openEditModal(
                                    <?= $uporabnik['id_uporabnik'] ?>, 
                                    '<?= htmlspecialchars($uporabnik['ime'], ENT_QUOTES) ?>', 
                                    '<?= htmlspecialchars($uporabnik['priimek'], ENT_QUOTES) ?>', 
                                    '<?= htmlspecialchars($uporabnik['email'], ENT_QUOTES) ?>', 
                                    '<?= htmlspecialchars($uporabnik['vloga'], ENT_QUOTES) ?>', 
                                    '<?= htmlspecialchars($uporabnik['status'], ENT_QUOTES) ?>',
                                    '<?= $uporabnik['prvi_vpis'] ?>'
                                )">Uredi</button>

                            <button style="background:#17a2b8;color:#fff;border:none;padding:5px 8px;border-radius:4px;cursor:pointer;margin-left:6px" onclick="triggerAdminUpload(<?= $uporabnik['id_uporabnik'] ?>)">Naloži sliko</button>

                            <button style="background:#dc3545;color:#fff;border:none;padding:5px 8px;border-radius:4px;cursor:pointer;margin-left:6px" onclick="deleteUserPic(<?= $uporabnik['id_uporabnik'] ?>)">Izbriši sliko</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div id="editUserModal" class="modal">
    <div class="modal-content">
        <span class="close-btn">&times;</span>
        <h2>Uredi uporabnika</h2>
        <form id="editUserForm">
            <input type="hidden" name="id_uporabnik" id="modal_id_uporabnik">

            <label for="modal_ime">Ime:</label>
            <input type="text" id="modal_ime" name="ime" required>

            <label for="modal_priimek">Priimek:</label>
            <input type="text" id="modal_priimek" name="priimek" required>

            <label for="modal_email">E-mail:</label>
            <input type="email" id="modal_email" name="email" required>

            <label for="modal_vloga">Vloga:</label>
            <select id="modal_vloga" name="vloga">
                <option value="ucitelj">Učitelj</option>
                <option value="ucenec">Učenec</option>
                <option value="admin">Admin</option>
            </select>
            
            <label for="modal_status">Status:</label>
            <select id="modal_status" name="status">
                <option value="active">Active</option>
                <option value="pending">Pending</option>
                <option value="zavrnjen">Zavrnjen</option>
            </select>

            <label for="modal_prvi_vpis">Prvi vpis (Začetna izbira predmetov):</label>
            <select id="modal_prvi_vpis" name="prvi_vpis">
                <option value="1">Da (Bo preusmerjen na predmetiPage)</option>
                <option value="0">Ne (Gre naravnost v učilnico)</option>
            </select>

            <label for="modal_novo_geslo">Novo geslo (Prazno za ohranitev starega):</label>
            <input type="password" id="modal_novo_geslo" name="novo_geslo" placeholder="Vnesi novo geslo">

            <button type="submit">Shrani spremembe</button>
        </form>
    </div>
</div>

<!-- hidden upload form -->
<form id="adminUploadForm" style="display:none" enctype="multipart/form-data" method="post">
    <input type="file" name="profile_pic" id="admin_profile_pic_input" accept="image/*">
    <input type="hidden" name="id_uporabnik" id="admin_upload_user_id">
</form>

<script>
    // JS za odpiranje/zapiranje modala in polnjenje podatkov
    const modal = document.getElementById('editUserModal');
    const span = document.getElementsByClassName('close-btn')[0];
    const form = document.getElementById('editUserForm');

    // Odpri modal in ga napolni s podatki
    function openEditModal(id, ime, priimek, email, vloga, status, prvi_vpis) {
        document.getElementById('modal_id_uporabnik').value = id;
        document.getElementById('modal_ime').value = ime;
        document.getElementById('modal_priimek').value = priimek;
        document.getElementById('modal_email').value = email;
        document.getElementById('modal_vloga').value = vloga;
        document.getElementById('modal_status').value = status;
        document.getElementById('modal_prvi_vpis').value = prvi_vpis;
        document.getElementById('modal_novo_geslo').value = ''; // Vedno zbrišemo polje za geslo
        modal.style.display = 'block';
    }

    // Zapri modal
    span.onclick = function() {
        modal.style.display = 'none';
    }
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }

    // JS za obdelavo AJAX oddaje obrazca
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());

        fetch('admin_ajax_update.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert('Podatki uspešno shranjeni!');
                modal.style.display = 'none';
                window.location.reload(); // Osveži stran, da se prikažejo spremembe
            } else {
                alert('Napaka pri shranjevanju: ' + result.message);
            }
        })
        .catch(error => {
            alert('Napaka pri komunikaciji s strežnikom.');
            console.error('Error:', error);
        });
    });

    // Admin upload/delete picture handlers
    function triggerAdminUpload(userId) {
        const input = document.getElementById('admin_profile_pic_input');
        document.getElementById('admin_upload_user_id').value = userId;
        input.value = '';
        input.onchange = function() {
            const fd = new FormData();
            fd.append('profile_pic', input.files[0]);
            fd.append('id_uporabnik', userId);

            fetch('admin_ajax_upload_pic.php', {
                method: 'POST',
                body: fd
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    alert('Slika naložena');
                    window.location.reload();
                } else {
                    alert('Napaka: ' + (res.message || 'Neznana napaka'));
                }
            }).catch(err => {
                alert('Napaka pri komunikaciji: ' + err.message);
            });
        };
        input.click();
    }

    function deleteUserPic(userId) {
        if (!confirm('Ste prepričani, da želite izbrisati profilno sliko tega uporabnika?')) return;
        fetch('admin_ajax_delete_pic.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_uporabnik: userId })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                alert('Slika izbrisana');
                window.location.reload();
            } else {
                alert('Napaka: ' + (res.message || 'Neznana napaka'));
            }
        }).catch(err => {
            alert('Napaka pri komunikaciji: ' + err.message);
        });
    }
</script>

</body>
</html>