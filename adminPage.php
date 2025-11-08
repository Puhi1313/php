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
$sql = "SELECT id_uporabnik, ime, priimek, email, vloga, status, prvi_vpis, icona_profila 
        FROM uporabnik 
        WHERE id_uporabnik != :admin_id"; // Ne prikažemo samega sebe
$params = ['admin_id' => $admin_id];

// DODAJANJE ISKALNEGA FILTRA
if (!empty($search_query)) {
    $sql .= " AND (ime LIKE :search_ime OR priimek LIKE :search_priimek OR email LIKE :search_email)";
    $search_param_value = '%' . $search_query . '%';
    $params['search_ime'] = $search_param_value;
    $params['search_priimek'] = $search_param_value;
    $params['search_email'] = $search_param_value;
}

// DODAJANJE FILTRA ZA VLOGO/STATUS
if ($vloga_filter === 'pending') {
    $sql .= " AND status = 'cakanje'";
} elseif ($vloga_filter === 'aktiven') {
    $sql .= " AND status = 'aktiven'";
} elseif ($vloga_filter !== 'all') {
    // Filter by role if vloga_filter is set and not equal to 'all'
    $sql .= " AND vloga = :vloga_filter";
    $params['vloga_filter'] = $vloga_filter;
}

$sql .= " ORDER BY priimek ASC, ime ASC";

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $uporabniki = $stmt->fetchAll();
} catch (\PDOException $e) {
    $error_message = "Napaka pri pridobivanju uporabnikov: " . $e->getMessage();
}

// Nastavitve za navbar (če jih imate)
$admin_ime = $_SESSION['ime'] ?? 'Admin';
$admin_priimek = $_SESSION['priimek'] ?? 'Uporabnik';

?>
<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f7f6; }
        .header { background-color: #3f51b5; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .header a { color: white; text-decoration: none; margin-left: 20px; font-weight: bold; }
        .container { padding: 20px; }
        
        /* ZAVIHKE */
        .tab-menu { display: flex; border-bottom: 2px solid #ccc; margin-bottom: 20px; }
        .tab-button { 
            padding: 10px 15px; 
            cursor: pointer; 
            border: 1px solid transparent;
            border-bottom: none;
            background-color: #eee;
            margin-right: 5px;
            border-top-left-radius: 5px;
            border-top-right-radius: 5px;
        }
        .tab-button.active { 
            background-color: white; 
            border: 1px solid #ccc; 
            border-bottom: 2px solid white; 
            font-weight: bold; 
        }
        .tab-content { background-color: white; padding: 20px; border: 1px solid #ccc; border-top: none; }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }

        /* TABELA */
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #e0e0e0; }
        tr:hover { background-color: #f1f1f1; }

        .btn { padding: 8px 12px; border: none; cursor: pointer; border-radius: 4px; margin-right: 5px; }
        .btn-green { background-color: #4CAF50; color: white; }
        .btn-red { background-color: #f44336; color: white; }
        .btn-blue { background-color: #2196F3; color: white; }
        .btn-yellow { background-color: #ffc107; color: black; }
        .btn-sm { padding: 5px 8px; font-size: 12px; }

        .profile-icon { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; vertical-align: middle; margin-right: 10px; }

        /* Modal */
        .modal {
            display: none; position: fixed; z-index: 10; left: 0; top: 0; width: 100%; height: 100%;
            overflow: auto; background-color: rgba(0,0,0,0.4);
        }
        .modal-content {
            background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888;
            width: 80%; max-width: 600px; border-radius: 8px; position: relative;
        }
        .close-btn { color: #aaa; float: right; font-size: 28px; font-weight: bold; }
        .close-btn:hover, .close-btn:focus { color: black; text-decoration: none; cursor: pointer; }
        
        /* Obrazci */
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        
        /* Za Predmete */
        .subject-list { max-height: 200px; overflow-y: auto; border: 1px solid #ccc; padding: 10px; border-radius: 4px; }
        .subject-item { 
            padding: 8px; margin-bottom: 5px; background-color: #f9f9f9; 
            border: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;
        }
        .subject-item.assigned { background-color: #d4edda; border-color: #c3e6cb; }
    </style>
</head>
<body>

<div class="header">
    <h1>Admin Panel</h1>
    <nav>
        <span>Pozdravljen, <?php echo htmlspecialchars($admin_ime . ' ' . $admin_priimek); ?></span>
        <a href="logout.php">Odjava</a>
    </nav>
</div>

<div class="container">

    <div class="tab-menu">
        <button class="tab-button active" onclick="openTab(event, 'uporabniki')">Upravljanje Uporabnikov</button>
        <button class="tab-button" onclick="openTab(event, 'dodaj-uporabnika')">Dodaj Uporabnika</button>
    </div>

    <div id="uporabniki" class="tab-pane active tab-content">
        <h2>Seznam Uporabnikov</h2>
        
        <?php if ($error_message): ?>
            <p style="color: red;"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>

        <form method="GET" action="adminPage.php" style="margin-bottom: 20px;">
            <input type="text" name="s" placeholder="Išči po imenu, priimku ali e-mailu" value="<?php echo htmlspecialchars($search_query); ?>" style="padding: 8px; width: 40%;">
            <select name="vloga" style="padding: 8px;">
                <option value="all" <?php echo $vloga_filter === 'all' ? 'selected' : ''; ?>>Vse Vloge (Aktivne)</option>
                <option value="pending" <?php echo $vloga_filter === 'pending' ? 'selected' : ''; ?>>Čakajoči na Aktivacijo</option>
                <option value="admin" <?php echo $vloga_filter === 'admin' ? 'selected' : ''; ?>>Administratorji</option>
                <option value="ucitelj" <?php echo $vloga_filter === 'ucitelj' ? 'selected' : ''; ?>>Učitelji</option>
                <option value="ucenec" <?php echo $vloga_filter === 'ucenec' ? 'selected' : ''; ?>>Učenci</option>
            </select>
            <button type="submit" class="btn btn-blue">Filtriraj</button>
        </form>

        <table>
            <thead>
                <tr>
                    <th></th>
                    <th>ID</th>
                    <th>Ime in Priimek</th>
                    <th>E-mail</th>
                    <th>Vloga / Status</th>
                    <th>Prvi Vpis</th>
                    <th>Akcije</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($uporabniki as $u): ?>
                    <tr data-id="<?php echo $u['id_uporabnik']; ?>">
                        <td>
                            <img src="<?php echo $u['icona_profila'] ?: 'slike/default_icon.png'; ?>" 
                                 alt="Profilna ikona" 
                                 class="profile-icon">
                        </td>
                        <td><?php echo $u['id_uporabnik']; ?></td>
                        <td><?php echo htmlspecialchars($u['ime'] . ' ' . $u['priimek']); ?></td>
                        <td><?php echo htmlspecialchars($u['email']); ?></td>
                        <td>
                            <span style="font-weight: bold;"><?php echo htmlspecialchars($u['vloga']); ?></span> 
                            (<?php echo htmlspecialchars($u['status']); ?>)
                        </td>
                        <td><?php echo $u['prvi_vpis'] ? 'DA' : 'NE'; ?></td>
                        <td>
                            <button class="btn btn-yellow btn-sm" 
                                    onclick="openEditModal(<?php echo htmlspecialchars(json_encode($u)); ?>)">Uredi</button>

                            <?php if ($u['vloga'] === 'ucitelj'): ?>
                                <a href="admin_ucitelj_details.php?id=<?php echo $u['id_uporabnik']; ?>" 
                                   class="btn btn-blue btn-sm" 
                                   style="text-decoration: none; display: inline-block;">
                                    Podrobnosti
                                </a>
                            <?php elseif ($u['vloga'] === 'ucenec'): ?>
                                <a href="admin_ucenec_details.php?id=<?php echo $u['id_uporabnik']; ?>" 
                                   class="btn btn-blue btn-sm" 
                                   style="text-decoration: none; display: inline-block;">
                                    Podrobnosti
                                </a>
                            <?php endif; ?>

                            <button class="btn btn-green btn-sm" 
                                    onclick="uploadUserPic(<?php echo $u['id_uporabnik']; ?>)">Naloži Sliko</button>
                            
                            <button class="btn btn-red btn-sm" 
                                    onclick="deleteUserPic(<?php echo $u['id_uporabnik']; ?>)"
                                    <?php echo empty($u['icona_profila']) ? 'disabled' : ''; ?>>Izbriši Sliko</button>
                            
                            <button class="btn btn-red btn-sm" 
                                    onclick="deleteUser(<?php echo $u['id_uporabnik']; ?>, '<?php echo htmlspecialchars($u['ime'] . ' ' . $u['priimek']); ?>')"
                                    style="background-color: #d32f2f;">Izbriši Uporabnika</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (empty($uporabniki)): ?>
            <p style="margin-top: 20px;">Ni najdenih uporabnikov po izbranem filtru.</p>
        <?php endif; ?>

    </div>

    <div id="dodaj-uporabnika" class="tab-pane tab-content">
        <h2>Dodaj Novega Uporabnika</h2>
        <div id="add-user-message" style="margin-bottom: 15px; font-weight: bold;"></div>
        
        <form id="add-user-form">
            <div class="form-group">
                <label for="new_ime">Ime:</label>
                <input type="text" id="new_ime" name="ime" required>
            </div>
            <div class="form-group">
                <label for="new_priimek">Priimek:</label>
                <input type="text" id="new_priimek" name="priimek" required>
            </div>
            <div class="form-group">
                <label for="new_vloga">Vloga:</label>
                <select id="new_vloga" name="vloga" required>
                    <option value="">Izberite vlogo</option>
                    <option value="ucenec">Učenec</option>
                    <option value="ucitelj">Učitelj</option>
                    <option value="admin">Administrator</option>
                </select>
            </div>
            <div class="form-group">
                <label for="new_mesto">Mesto bivanja:</label>
                <input type="text" id="new_mesto" name="mesto" required>
            </div>
            <div class="form-group">
                <label for="new_kontakt_email">Kontakt E-mail (osebni):</label>
                <input type="email" id="new_kontakt_email" name="kontakt_email" required>
                <small>Šolski e-mail bo generiran avtomatsko.</small>
            </div>
            
            <button type="submit" class="btn btn-green" style="width: 100%;">Dodaj Uporabnika</button>
        </form>
    </div>

</div>

<div id="editUserModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="document.getElementById('editUserModal').style.display='none';">&times;</span>
        <h2>Uredi Uporabnika: <span id="edit-ime-priimek"></span></h2>
        <div id="edit-message" style="color: green; font-weight: bold; margin-bottom: 15px;"></div>
        <form id="edit-user-form">
            <input type="hidden" id="edit_id_uporabnik" name="id_uporabnik">
            <div class="form-group">
                <label for="edit_ime">Ime:</label>
                <input type="text" id="edit_ime" name="ime" required>
            </div>
             <div class="form-group">
                <label for="edit_priimek">Priimek:</label>
                <input type="text" id="edit_priimek" name="priimek" required>
            </div>
            <div class="form-group">
                <label for="edit_email">Šolski E-mail:</label>
                <input type="email" id="edit_email" name="email" required>
            </div>
            <div class="form-group">
                <label for="edit_vloga">Vloga:</label>
                <select id="edit_vloga" name="vloga" required>
                    <option value="ucenec">Učenec</option>
                    <option value="ucitelj">Učitelj</option>
                    <option value="admin">Administrator</option>
                </select>
            </div>
            <div class="form-group">
                <label for="edit_status">Status:</label>
                <select id="edit_status" name="status" required>
                    <option value="cakanje">Čakanje</option>
                    <option value="aktiven">Aktiven</option>
                    <option value="blokiran">Blokiran</option>
                </select>
            </div>
            <div class="form-group">
                <label for="edit_prvi_vpis">Zahtevaj spremembo gesla ob 1. vpisu:</label>
                <select id="edit_prvi_vpis" name="prvi_vpis" required>
                    <option value="1">DA</option>
                    <option value="0">NE</option>
                </select>
            </div>
            <div class="form-group">
                <label for="edit_novo_geslo">Novo geslo (prazno, če ga ne spreminjate):</label>
                <input type="password" id="edit_novo_geslo" name="novo_geslo">
            </div>
            <button type="submit" class="btn btn-green">Shrani Spremembe</button>
        </form>
    </div>
</div>

<div id="manageSubjectsModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="document.getElementById('manageSubjectsModal').style.display='none';">&times;</span>
        <h2>Upravljanje Predmetov za: <span id="teacher-name"></span></h2>
        <input type="hidden" id="manage_id_ucitelj">
        <div id="subject-message" style="color: green; font-weight: bold; margin-bottom: 15px;"></div>
        <div class="subject-list" id="subjects-container">
            Nalaganje predmetov...
        </div>
        <p style="margin-top: 15px;">**Klik na predmet ga doda ali odstrani učitelju.**</p>
    </div>
</div>


<script>
    // ----------------------------------------------------
    // GLOBALNE FUNKCIJE
    // ----------------------------------------------------

    /**
     * Preklapljanje med zavihki (uporabniki, dodaj-uporabnika)
     */
    function openTab(evt, tabName) {
        let i, tabcontent, tablinks;
        
        tabcontent = document.getElementsByClassName("tab-pane");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
        }
        
        tablinks = document.getElementsByClassName("tab-button");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].className = tablinks[i].className.replace(" active", "");
        }
        
        document.getElementById(tabName).style.display = "block";
        if (evt && evt.currentTarget) {
            evt.currentTarget.className += " active";
        } else {
             // Za inicialni zagon, kjer ni dogodka
            document.querySelector('.tab-button').className += " active";
        }
    }
    
    // Inicialni zagon
    document.addEventListener('DOMContentLoaded', () => {
        // Inicializacija prvega zavihka
        openTab(null, 'uporabniki'); 

        // Poslušalec za formo DODAJ UPORABNIKA
        document.getElementById('add-user-form').addEventListener('submit', handleAddUser);

        // Poslušalec za formo UREDI UPORABNIKA (predpostavljamo, da obstaja v modalnem oknu)
        document.getElementById('edit-user-form').addEventListener('submit', handleEditUser);
    });

    // ----------------------------------------------------
    // FUNKCIJE ZA UPRAVLJANJE PROFILNE SLIKE (Popravljeno)
    // ----------------------------------------------------

    /**
     * Naloži novo profilno sliko za določenega uporabnika
     */
    function uploadUserPic(userId) {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.onchange = (e) => {
            if (!e.target.files.length) return;

            const fd = new FormData();
            fd.append('profile_pic', input.files[0]);
            fd.append('id_uporabnik', userId);

            fetch('admin_ajax_upload_pic.php', {
                method: 'POST',
                body: fd
            })
            .then(r => {
                // Preveri, če je odgovor JSON
                const contentType = r.headers.get("content-type");
                if (contentType && contentType.indexOf("application/json") !== -1) {
                    return r.json();
                } else {
                    // Če ni JSON, je to verjetno PHP napaka (ki je povzročala prejšnjo napako)
                    throw new Error('Nepričakovan odgovor: ni JSON. Morda PHP napaka.');
                }
            })
            .then(res => {
                if (res.success) {
                    alert('Slika uspešno naložena!');
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

    /**
     * Izbriše profilno sliko za določenega uporabnika
     */
    function deleteUserPic(userId) {
        if (!confirm('Ste prepričani, da želite izbrisati profilno sliko tega uporabnika?')) return;
        
        fetch('admin_ajax_delete_pic.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_uporabnik: userId })
        })
        .then(r => {
            const contentType = r.headers.get("content-type");
            if (contentType && contentType.indexOf("application/json") !== -1) {
                return r.json();
            } else {
                throw new Error('Nepričakovan odgovor: ni JSON. Morda PHP napaka.');
            }
        })
        .then(res => {
            if (res.success) {
                alert('Slika uspešno izbrisana!');
                window.location.reload();
            } else {
                alert('Napaka: ' + (res.message || 'Neznana napaka'));
            }
        }).catch(err => {
            alert('Napaka pri komunikaciji: ' + err.message);
        });
    }

    /**
     * Izbriše uporabnika (s kaskadnim brisanjem)
     */
    function deleteUser(userId, userName) {
        if (!confirm('Ste prepričani, da želite IZBRISATI uporabnika "' + userName + '"?\n\nTa akcija je NEPOVRATNA in bo izbrisala:\n- Vse oddaje\n- Vse naloge\n- Vse predmete\n- Vse podatke povezane s tem uporabnikom!')) {
            return;
        }
        
        if (!confirm('ZADNJA MOŽNOST: Ali ste RES prepričani, da želite izbrisati tega uporabnika?')) {
            return;
        }
        
        fetch('admin_ajax_delete_user.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_uporabnik: userId })
        })
        .then(r => {
            const contentType = r.headers.get("content-type");
            if (contentType && contentType.indexOf("application/json") !== -1) {
                return r.json();
            } else {
                throw new Error('Nepričakovan odgovor: ni JSON. Morda PHP napaka.');
            }
        })
        .then(res => {
            if (res.success) {
                alert('Uporabnik uspešno izbrisan!');
                window.location.reload();
            } else {
                alert('Napaka: ' + (res.message || 'Neznana napaka'));
            }
        }).catch(err => {
            alert('Napaka pri komunikaciji: ' + err.message);
        });
    }

    // ----------------------------------------------------
    // FUNKCIJE ZA MODAL ZA UREJANJE UPORABNIKA (IZPOLNITE, če so manjkale)
    // ----------------------------------------------------

    /**
     * Odpre modalno okno za urejanje in napolni polja
     */
    function openEditModal(userData) {
        document.getElementById('edit_id_uporabnik').value = userData.id_uporabnik;
        document.getElementById('edit_ime').value = userData.ime;
        document.getElementById('edit_priimek').value = userData.priimek;
        document.getElementById('edit_email').value = userData.email;
        document.getElementById('edit_vloga').value = userData.vloga;
        document.getElementById('edit_status').value = userData.status;
        document.getElementById('edit_prvi_vpis').value = userData.prvi_vpis;
        document.getElementById('edit-ime-priimek').textContent = userData.ime + ' ' + userData.priimek;
        document.getElementById('edit_novo_geslo').value = '';
        document.getElementById('edit-message').textContent = '';

        document.getElementById('editUserModal').style.display = 'block';
    }

    /**
     * Shrani spremembe uporabnika (klic v admin_ajax_update.php)
     */
    async function handleEditUser(e) {
        e.preventDefault();
        const form = e.currentTarget;
        const formData = new FormData(form);
        const data = {};
        formData.forEach((value, key) => (data[key] = value));

        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Shranjevanje...';

        try {
            const response = await fetch('admin_ajax_update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await response.json();
            
            document.getElementById('edit-message').textContent = result.message;
            
            if (result.success) {
                // Po uspešni posodobitvi osveži stran
                 setTimeout(() => {
                    document.getElementById('editUserModal').style.display = 'none';
                    window.location.reload();
                }, 1000);
            }
        } catch (error) {
            document.getElementById('edit-message').textContent = 'Napaka pri komunikaciji: ' + error.message;
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Shrani Spremembe';
        }
    }


    // ----------------------------------------------------
    // FUNKCIJE ZA ZAVIHEK DODAJ UPORABNIKA
    // ----------------------------------------------------
    
    /**
     * Dodajanje novega uporabnika (klic v admin_ajax_add_user.php)
     */
    async function handleAddUser(e) {
        e.preventDefault();
        const form = e.currentTarget;
        const formData = new FormData(form);
        const data = {};
        formData.forEach((value, key) => (data[key] = value));

        const submitBtn = form.querySelector('button[type="submit"]');
        const messageDiv = document.getElementById('add-user-message');
        
        submitBtn.disabled = true;
        submitBtn.textContent = 'Dodajanje...';
        messageDiv.textContent = ''; // Ponastavi sporočilo

        try {
            const response = await fetch('admin_ajax_add_user.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await response.json();
            
            messageDiv.innerHTML = result.message.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
            
            if (result.success) {
                form.reset(); // Po uspehu ponastavi formo
                messageDiv.style.color = 'green';
                 // Po uspešni posodobitvi osveži stran s seznami
                 setTimeout(() => {
                    window.location.reload(); 
                }, 3000);
            } else {
                 messageDiv.style.color = 'red';
            }
        } catch (error) {
            messageDiv.textContent = 'Napaka pri komunikaciji: ' + error.message;
            messageDiv.style.color = 'red';
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Dodaj Uporabnika';
        }
    }
    
    // ----------------------------------------------------
    // FUNKCIJE ZA UPRAVLJANJE PREDMETOV
    // ----------------------------------------------------

    /**
     * Odpre modalno okno za upravljanje predmetov in naloži podatke
     */
    async function showTeacherSubjectsModal(idUcitelj, teacherName) {
        document.getElementById('teacher-name').textContent = teacherName;
        document.getElementById('manage_id_ucitelj').value = idUcitelj;
        document.getElementById('subject-message').textContent = '';
        document.getElementById('subjects-container').innerHTML = 'Nalaganje predmetov...';

        try {
            const response = await fetch(`admin_ajax_fetch_subjects.php?id_ucitelj=${idUcitelj}`);
            const result = await response.json();

            if (result.success) {
                const container = document.getElementById('subjects-container');
                container.innerHTML = '';
                
                result.all_subjects.forEach(subject => {
                    const isAssigned = result.assigned_subjects.includes(subject.id_predmet.toString());
                    const div = document.createElement('div');
                    div.className = 'subject-item ' + (isAssigned ? 'assigned' : '');
                    div.dataset.idPredmet = subject.id_predmet;
                    div.dataset.isAssigned = isAssigned ? '1' : '0';
                    div.innerHTML = `
                        <span>${subject.ime_predmeta}</span>
                        <button class="btn btn-sm ${isAssigned ? 'btn-red' : 'btn-green'}" 
                                data-id-predmet="${subject.id_predmet}" 
                                data-action="${isAssigned ? 'delete' : 'add'}">
                            ${isAssigned ? 'Odstrani' : 'Dodeli'}
                        </button>
                    `;
                    div.querySelector('button').addEventListener('click', manageTeacherSubject);
                    container.appendChild(div);
                });

            } else {
                document.getElementById('subjects-container').innerHTML = `<p style="color: red;">Napaka pri nalaganju: ${result.message}</p>`;
            }

        } catch (error) {
            document.getElementById('subjects-container').innerHTML = `<p style="color: red;">Napaka pri komunikaciji: ${error.message}</p>`;
        }

        document.getElementById('manageSubjectsModal').style.display = 'block';
    }

    /**
     * Doda ali odstrani predmet učitelju
     */
    async function manageTeacherSubject(e) {
        const btn = e.currentTarget;
        const itemDiv = btn.closest('.subject-item');
        const idUcitelj = document.getElementById('manage_id_ucitelj').value;
        const idPredmet = btn.dataset.idPredmet;
        const action = btn.dataset.action;

        btn.disabled = true;
        btn.textContent = 'Procesiranje...';
        const messageDiv = document.getElementById('subject-message');
        messageDiv.textContent = '';

        try {
            const response = await fetch('admin_ajax_manage_teacher_subjects.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id_ucitelj: idUcitelj,
                    id_predmet: idPredmet,
                    action: action
                })
            });
            const result = await response.json();

            if (result.success) {
                messageDiv.textContent = result.message;
                messageDiv.style.color = 'green';
                
                // Posodobi gumb in razred takoj po uspehu
                if (action === 'add') {
                    btn.dataset.action = 'delete';
                    btn.textContent = 'Odstrani';
                    btn.className = 'btn btn-sm btn-red';
                    itemDiv.classList.add('assigned');
                } else {
                    btn.dataset.action = 'add';
                    btn.textContent = 'Dodeli';
                    btn.className = 'btn btn-sm btn-green';
                    itemDiv.classList.remove('assigned');
                }
            } else {
                messageDiv.textContent = `Napaka: ${result.message}`;
                messageDiv.style.color = 'red';
            }
        } catch (error) {
            messageDiv.textContent = 'Napaka pri komunikaciji s strežnikom.';
            messageDiv.style.color = 'red';
        } finally {
            btn.disabled = false;
        }
    }

</script>

</body>
</html>