<?php
session_start();
require_once 'povezava.php'; // Predpostavljamo, da je 'povezava.php' na voljo

// Preverjanje vloge in preusmeritev
if (!isset($_SESSION['user_id']) || $_SESSION['vloga'] === 'ucenec') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$ime_priimek = 'Neznan uporabnik';
$ucenci_po_predmetih = [];
$naloge_po_datumu = [];
$predmeti_menu = []; // List of all subjects assigned to teacher from ucitelj_predmet table
$vloga = $_SESSION['vloga']; // Uporabno za razlikovanje admin/ucitelj

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 1. Pridobitev imena
    $sql_ime = "SELECT ime, priimek FROM uporabnik WHERE id_uporabnik = ?";
    $stmt_ime = $pdo->prepare($sql_ime);
    $stmt_ime->execute([$user_id]);
    $uporabnik_data = $stmt_ime->fetch();
    $ime_priimek = $uporabnik_data ? $uporabnik_data['ime'] . ' ' . $uporabnik_data['priimek'] : 'Neznan uporabnik';

    // 1.5. Pridobitev vseh predmetov, ki so dodeljeni učitelju iz tabele ucitelj_predmet
    // This ensures teachers can see all their assigned subjects, even if they don't have students yet
    $sql_predmeti_ucitelj = "
        SELECT p.id_predmet, p.ime_predmeta
        FROM ucitelj_predmet up
        JOIN predmet p ON up.id_predmet = p.id_predmet
        WHERE up.id_ucitelj = ?
        ORDER BY p.ime_predmeta ASC
    ";
    $stmt_predmeti_ucitelj = $pdo->prepare($sql_predmeti_ucitelj);
    $stmt_predmeti_ucitelj->execute([$user_id]);
    $predmeti_menu = $stmt_predmeti_ucitelj->fetchAll();

    // 2. Pridobitev učencev, ki so dodeljeni učitelju (po predmetih)
    $sql_ucenci = "
        SELECT 
            p.id_predmet, p.ime_predmeta, 
            u.id_uporabnik AS id_ucenec, u.ime AS ime_ucenca, u.priimek AS priimek_ucenca
        FROM ucenec_predmet up
        JOIN predmet p ON up.id_predmet = p.id_predmet
        JOIN uporabnik u ON up.id_ucenec = u.id_uporabnik
        WHERE up.id_ucitelj = ?
        ORDER BY p.ime_predmeta, u.priimek
    ";
    $stmt_ucenci = $pdo->prepare($sql_ucenci);
    $stmt_ucenci->execute([$user_id]);
    $ucenci_raw = $stmt_ucenci->fetchAll();

    // Organizacija podatkov po predmetih in učencih
    foreach ($ucenci_raw as $row) {
        $key_predmet = $row['ime_predmeta'] . '###' . $row['id_predmet'];
        if (!isset($ucenci_po_predmetih[$key_predmet])) {
            $ucenci_po_predmetih[$key_predmet] = [
                'id_predmet' => $row['id_predmet'],
                'ime_predmeta' => $row['ime_predmeta'],
                'ucenci' => []
            ];
        }
        $key_ucenec = $row['id_ucenec'];
        if (!isset($ucenci_po_predmetih[$key_predmet]['ucenci'][$key_ucenec])) {
            $ucenci_po_predmetih[$key_predmet]['ucenci'][$key_ucenec] = [
                'id_ucenec' => $row['id_ucenec'],
                'ime_priimek' => $row['ime_ucenca'] . ' ' . $row['priimek_ucenca'],
                'oddaje' => []
            ];
        }
    }
    
    // 3. Pridobitev vseh nalog, ki jih je objavil ta učitelj
    $sql_naloge = "
        SELECT n.id_naloga, n.id_predmet, n.naslov, n.datum_objave, n.rok_oddaje, p.ime_predmeta
        FROM naloga n
        JOIN predmet p ON n.id_predmet = p.id_predmet
        WHERE n.id_ucitelj = ?
        ORDER BY n.datum_objave DESC
    ";
    $stmt_naloge = $pdo->prepare($sql_naloge);
    $stmt_naloge->execute([$user_id]);
    $naloge_ucitelj = $stmt_naloge->fetchAll();

    // Organizacija nalog po datumu objave
    foreach ($naloge_ucitelj as $naloga) {
        $datum_objave = (new DateTime($naloga['datum_objave']))->format('d.m.Y');
        if (!isset($naloge_po_datumu[$datum_objave])) {
            $naloge_po_datumu[$datum_objave] = [];
        }
        $naloge_po_datumu[$datum_objave][] = $naloga;
    }

} catch (\PDOException $e) {
    error_log("Database Error (Učitelj): " . $e->getMessage()); 
    die("Prišlo je do napake v sistemu. Prosimo, poskusite kasneje.");
}
?>
<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Učilnica za Učitelje</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Momo+Trust+Display&family=Raleway:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <style>
        html {
            color: #596235;
        }
        body {
            margin: 0;
            font-family: "Raleway", sans-serif;
            /* Harmonious layered background using existing palette */
            background:
                radial-gradient(900px 500px at 10% -10%, rgba(205, 205, 182, 0.65), rgba(205, 205, 182, 0) 70%),
                radial-gradient(900px 500px at 110% 10%, rgba(128, 133, 47, 0.18), rgba(128, 133, 47, 0) 60%),
                linear-gradient(180deg, #f7f8f3 0%, #eff1e4 45%, #e3e6d1 100%);
            background-attachment: fixed;
            color: #596235;
        }
        header {
            background: #cdcdb6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        header .logo {
            font-weight: bold;
            font-size: 24px;
            color: #596235;
        }
        header nav {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        header nav span {
            color: #596235;
        }
        header nav a {
            color: #596235;
            text-decoration: none;
            transition: text-decoration 0.2s;
        }
        header nav a:hover {
            text-decoration: underline;
        }
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 15px;
        }
        
        h2 {
            color: #596235;
            border-bottom: 2px solid #cdcdb6;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        /* Stil za Zavihke (Tabs) */
        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 2px solid #ddd;
        }
        .tab-button {
            background: #eee;
            border: 1px solid #ddd;
            border-bottom: none;
            padding: 10px 15px;
            cursor: pointer;
            margin-right: 5px;
            border-radius: 10px 10px 0 0;
            font-size: 16px;
            color: #596235;
            transition: background 0.3s, transform 0.15s ease, color 0.3s;
        }
        .tab-button:hover {
            transform: translateY(-1px);
            background: #f5f5f5;
        }
        .tab-button.active {
            background: #fff;
            border-color: #cdcdb6;
            border-bottom: 2px solid #fff;
            font-weight: bold;
            color: #596235;
        }
        .tab-content {
            display: none;
            background: white;
            padding: 20px;
            border: 1px solid #cdcdb6;
            border-top: none;
            border-radius: 0 10px 10px 10px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
        }
        .tab-content.active {
            display: block;
        }
        
        /* Stil za Levo/Desno postavitev */
        .split-content {
            display: flex;
            gap: 20px;
        }
        .left-panel {
            flex: 1;
            max-width: 300px;
            background: #f8f8f0;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #cdcdb6;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        .left-panel h4 {
            margin-top: 0;
            color: #596235;
            border-bottom: 2px solid #cdcdb6;
            padding-bottom: 5px;
            margin-bottom: 15px;
        }
        .right-panel {
            flex: 3;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            border: 1px solid #cdcdb6;
        }
        .right-panel h3 {
            color: #596235;
            margin-top: 0;
            border-bottom: 2px solid #cdcdb6;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .right-panel p {
            color: #596235;
            line-height: 1.6;
        }
        
        /* Seznam predmetov/učencev */
        .list-group-header {
            font-weight: bold;
            padding: 10px 0;
            border-bottom: 2px solid #cdcdb6;
            margin-top: 15px;
            color: #596235;
        }
        .list-item {
            padding: 10px;
            margin-bottom: 5px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 10px;
            cursor: pointer;
            color: #596235;
            transition: background 0.2s, border-color 0.2s, transform 0.15s ease, box-shadow 0.15s ease;
        }
        .list-item:hover, .list-item.active {
            background: #e6e6fa;
            border-color: #cdcdb6;
            font-weight: bold;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        }
        
        .naloga-item {
            padding: 12px 15px;
            border: 1px solid #e2e2e2;
            border-left: 5px solid #cdcdb6;
            margin-bottom: 10px;
            border-radius: 10px;
            cursor: pointer;
            background: #fff;
            transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.2s ease, background 0.2s;
            color: #596235;
        }
        .naloga-item:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.07);
            border-left-color: #80852f;
        }
        .naloga-item.active {
            background: #e6e6fa;
            border-color: #cdcdb6;
            border-left-color: #596235;
            font-weight: bold;
        }
        
        .day-header {
            font-size: 1.1em;
            font-weight: bold;
            margin-top: 15px;
            padding-bottom: 5px;
            border-bottom: 2px solid #cdcdb6;
            color: #596235;
        }
        
        /* Form styling */
        form label {
            display: block;
            margin-bottom: 5px;
            color: #596235;
            font-weight: 500;
        }
        form input[type="text"],
        form input[type="datetime-local"],
        form textarea,
        form select {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-family: "Raleway", sans-serif;
            color: #596235;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        form input[type="text"]:focus,
        form input[type="datetime-local"]:focus,
        form textarea:focus,
        form select:focus {
            outline: none;
            border-color: #cdcdb6;
            box-shadow: 0 0 0 3px rgba(205, 205, 182, 0.2);
        }
        form button[type="submit"] {
            padding: 10px 20px;
            background: #596235;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 500;
            transition: background 0.3s, transform 0.15s ease;
        }
        form button[type="submit"]:hover:not(:disabled) {
            background: #4a5230;
            transform: translateY(-1px);
        }
        form button[type="submit"]:disabled {
            background: #999;
            cursor: not-allowed;
        }
        form input[type="file"] {
            padding: 5px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-family: "Raleway", sans-serif;
        }

        /* Drag & Drop area */
        .dropzone {
            position: relative;
            border: 2px dashed #bfbfae;
            border-radius: 14px;
            background: #f6f7f1;
            padding: 26px;
            text-align: center;
            color: #596235;
            transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
            margin-bottom: 15px;
        }
        .dropzone:hover { box-shadow: 0 6px 16px rgba(0,0,0,0.06); }
        .dropzone.dragover {
            border-color: #80852f;
            background: #eef0e1;
        }
        .dropzone .dz-icon { font-size: 42px; margin-bottom: 10px; display: block; }
        .dropzone .dz-title { font-weight: 700; margin: 6px 0; }
        .dropzone .dz-sub { color: #6c7450; margin-bottom: 12px; }
        .dropzone .dz-browse { display: inline-block; }
        .dropzone .dz-file-name { margin-top: 8px; font-size: 14px; color: #6c7450; }
        
        /* Button styling for grading and other action buttons */
        button.pregled-oddaje-btn,
        button.delete-btn,
        .btn-primary {
            padding: 8px 15px;
            background: #596235;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.3s, transform 0.15s ease;
            font-family: "Raleway", sans-serif;
        }
        button.pregled-oddaje-btn:hover:not(:disabled),
        button.delete-btn:hover:not(:disabled),
        .btn-primary:hover:not(:disabled) {
            background: #4a5230;
            transform: translateY(-1px);
        }
        button.delete-btn {
            background: #a85d4a;
        }
        button.delete-btn:hover:not(:disabled) {
            background: #8d4e3d;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
            padding-top: 60px;
        }
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 90%;
            max-width: 800px;
            border-radius: 16px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        .close-btn {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            transition: color 0.2s;
        }
        .close-btn:hover, .close-btn:focus {
            color: #596235;
            text-decoration: none;
            cursor: pointer;
        }
    </style>
</head>
<body>

<header>
    <div class="logo">E-Učilnica</div>
    <nav style="display:flex;gap:12px;align-items:center;">
        <?php
            // Get current user profile picture
            $pic = '';
            try {
                $stmt_pic = $pdo->prepare("SELECT icona_profila FROM uporabnik WHERE id_uporabnik = ?");
                $stmt_pic->execute([$user_id]);
                $pic = $stmt_pic->fetchColumn();
            } catch (\Exception $e) { $pic = ''; }
        ?>
        <?php if (!empty($pic) && file_exists(__DIR__ . DIRECTORY_SEPARATOR . $pic)): ?>
            <a href="ucitelj_profile.php" style="display:flex;align-items:center;text-decoration:none;">
                <img src="<?php echo htmlspecialchars($pic); ?>" alt="Profil" style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid #fff;margin-right:8px;">
            </a>
        <?php else: ?>
            <a href="ucitelj_profile.php" style="display:flex;align-items:center;text-decoration:none;">
                <div style="width:36px;height:36px;border-radius:50%;background:#596235;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;margin-right:8px;">
                    <?php echo strtoupper(substr($ime_priimek,0,1)); ?>
                </div>
            </a>
        <?php endif; ?>
        <span>Pozdravljen, <?= htmlspecialchars($ime_priimek) ?> (<?= $vloga === 'admin' ? 'Administrator' : 'Učitelj' ?>)</span>
        <a href="ucitelj_profile.php">Moj profil</a>
        <a href="logout.php">Odjava</a>
    </nav>
</header>

<div class="container">
    <h2>Nadzorna Plošča Učitelja</h2>
    
    <div class="tabs">
        <button class="tab-button active" onclick="openTab(event, 'ucenci')">Učenci in Oddaje</button>
        <button class="tab-button" onclick="openTab(event, 'naloge')">Moje Naloge</button>
        <button class="tab-button" onclick="openTab(event, 'nova-naloga')">Nova Naloga</button>
    </div>

    <div id="ucenci" class="tab-content active">
        <div class="split-content">
            <div class="left-panel">
                <h4>Seznam Učencev</h4>
                <?php foreach ($ucenci_po_predmetih as $key => $data): ?>
                    <div class="list-group-header">
                        <?= htmlspecialchars($data['ime_predmeta']) ?>
                    </div>
                    <?php 
                    $ucenci_array = array_values($data['ucenci']); // Pretvori v oštevilčeno polje za lažje delo z indeksi
                    usort($ucenci_array, function($a, $b) { return strcmp($a['ime_priimek'], $b['ime_priimek']); });
                    foreach ($ucenci_array as $ucenec): ?>
                        <div 
                            class="list-item ucenec-item" 
                            data-id-ucenec="<?= $ucenec['id_ucenec'] ?>"
                            data-ime-ucenca="<?= htmlspecialchars($ucenec['ime_priimek']) ?>"
                        >
                            <?= htmlspecialchars($ucenec['ime_priimek']) ?>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
            
            <div class="right-panel">
                <h3 id="ucenec-pregled-header">Izberite učenca za pregled oddaj</h3>
                <div id="ucenec-pregled-vsebina">
                    <p>Tukaj se bodo prikazale vse oddane naloge izbranega učenca za vaše predmete, ki čakajo na oceno, ali so že bile ocenjene. Uporabite gumb 'Pregled/Oceni' za ocenjevanje.</p>
                </div>
            </div>
        </div>
        
        <div id="grading-modal" class="modal">
            <div class="modal-content">
                <span class="close-btn">&times;</span>
                <h4 id="grading-modal-naslov"></h4>
                <div id="grading-modal-vsebina">Nalaganje detajlov oddaje...</div>
            </div>
        </div>
    </div>

    <div id="naloge" class="tab-content">
        <div class="split-content">
            <div class="left-panel">
                <h4>Objavljene Naloge</h4>
                <?php if (empty($naloge_po_datumu)): ?>
                    <p>Še nimate objavljenih nalog.</p>
                <?php else: ?>
                    <?php foreach ($naloge_po_datumu as $datum => $naloge_dneva): ?>
                        <div class="day-header"><?= $datum ?></div>
                        <?php foreach ($naloge_dneva as $naloga): ?>
                            <div 
                                class="naloga-item" 
                                data-id-naloga="<?= $naloga['id_naloga'] ?>"
                                data-id-predmet="<?= $naloga['id_predmet'] ?>"
                                data-ime-predmeta="<?= htmlspecialchars($naloga['ime_predmeta']) ?>"
                                data-naslov="<?= htmlspecialchars($naloga['naslov']) ?>"
                            >
                                <strong><?= htmlspecialchars($naloga['naslov']) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                
            </div>
            
            <div class="right-panel">
                <h3 id="naloga-detajli-header">Izberite nalogo ali objavite novo</h3>
                <div id="naloga-detajli-vsebina">
                    <p>Na levi izberite nalogo za pregled. Za ustvarjanje nove naloge odprite zavihek "Nova Naloga".</p>
                </div>
            </div>
        </div>
    </div>

    <div id="nova-naloga" class="tab-content">
        <div class="split-content">
            <div class="left-panel">
                <h4>Nova Naloga</h4>
                <p>Ustvarite novo nalogo in jo dodelite predmetu.</p>
            </div>
            <div class="right-panel">
                <h3>Objavi Novo Nalogo</h3>
                <form id="nova-naloga-form" method="POST" enctype="multipart/form-data">
                    <div>
                        <label for="id_predmet">Izberi Predmet:</label>
                        <select id="id_predmet" name="id_predmet" required style="width: 100%; padding: 8px; margin-bottom: 10px;">
                            <option value="">-- Izberi predmet --</option>
                            <?php 
                            // Use predmeti_menu which contains all subjects assigned to teacher from ucitelj_predmet table
                            foreach ($predmeti_menu as $predmet) {
                                echo '<option value="' . htmlspecialchars($predmet['id_predmet']) . '">' . htmlspecialchars($predmet['ime_predmeta']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label for="naslov">Naslov naloge:</label>
                        <input type="text" id="naslov" name="naslov" required style="width: 100%; padding: 8px; margin-bottom: 10px;">
                    </div>
                    <div>
                        <label for="rok_oddaje">Rok oddaje:</label>
                        <input type="datetime-local" id="rok_oddaje" name="rok_oddaje" required style="width: 100%; padding: 8px; margin-bottom: 10px;">
                    </div>
                    <div>
                        <label for="opis_naloge">Opis naloge:</label>
                        <textarea id="opis_naloge" name="opis_naloge" rows="8" style="width: 100%; padding: 8px; margin-bottom: 10px;"></textarea>
                    </div>
                    <div>
                        <label for="datoteka">Priloži datoteko (opcija):</label>
                        <div id="dz-nova-naloga" class="dropzone">
                            <span class="dz-icon">☁️</span>
                            <div class="dz-title">Povlecite in spustite datoteko sem</div>
                            <div class="dz-sub">ALI</div>
                            <button type="button" class="dz-browse btn-primary">Izberite datoteko</button>
                            <div class="dz-file-name" id="dz-nova-naloga-filename">Ni izbrane datoteke</div>
                            <input type="file" id="datoteka" name="datoteka" style="display:none;">
                        </div>
                    </div>
                    <button type="submit" class="btn-primary">Objavi Nalogo</button>
                </form>
            </div>
        </div>
    </div>

</div>

<script>
    // Globalne spremenljivke
    const userId = <?= $user_id ?>;
    const vloga = '<?= $vloga ?>';
    const gradingModal = document.getElementById('grading-modal');
    const gradingModalCloseBtn = gradingModal.getElementsByClassName("close-btn")[0];
    
    // Zapiranje modala za ocenjevanje
    gradingModalCloseBtn.onclick = function() { gradingModal.style.display = "none"; }
    window.onclick = function(event) {
        if (event.target == gradingModal) { gradingModal.style.display = "none"; }
    }


    // ----------------------------------------------------
    // FUNKCIJE ZA UPRAVLJANJE ZAVIHKOV IN VSEBINE
    // ----------------------------------------------------
    
    function openTab(evt, tabName) {
        var i, tabcontent, tablinks;
        tabcontent = document.getElementsByClassName("tab-content");
        for (i = 0; i < tabcontent.length; i++) { tabcontent[i].style.display = "none"; }
        tablinks = document.getElementsByClassName("tab-button");
        for (i = 0; i < tablinks.length; i++) { tablinks[i].className = tablinks[i].className.replace(" active", ""); }
        document.getElementById(tabName).style.display = "block";
        evt.currentTarget.className += " active";
        
        // Ponastavitev vsebine ob preklopu
        if (tabName === 'naloge') {
            document.getElementById('naloga-detajli-header').textContent = 'Izberite nalogo';
            document.getElementById('naloga-detajli-vsebina').innerHTML = '<p>Na levi izberite eno od vaših nalog za ogled podrobnosti.</p>';
        }
    }
    
    // Inicialni prikaz prvega zavihka
    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('ucenci').style.display = 'block';
        
        // Nastavi poslušalce za zavihek "Učenci"
        document.querySelectorAll('.ucenec-item').forEach(item => {
            item.addEventListener('click', handleUcenecSelect);
        });

        // Nastavi poslušalce za zavihek "Moje Naloge"
        document.querySelectorAll('.naloga-item').forEach(item => {
            item.addEventListener('click', handleNalogaSelect);
        });

        // Nastavi poslušalca za formo za novo nalogo v zavihku "Nova Naloga"
        const formNovaNaloga = document.getElementById('nova-naloga-form');
        if (formNovaNaloga) {
            formNovaNaloga.addEventListener('submit', handleNalogaCreate);
        }

        // Inicializiraj drag & drop komponento za nalaganje datotek
        initDropzone({
            wrapperId: 'dz-nova-naloga',
            inputId: 'datoteka',
            fileNameId: 'dz-nova-naloga-filename'
        });
    });


    // ----------------------------------------------------
    // ZAVIHEK: UČENCI IN ODDAJE (Ocenjevanje)
    // ----------------------------------------------------
    
    // 1. Nalaganje oddaj izbranega učenca
    async function handleUcenecSelect(e) {
        e.preventDefault();
        const item = e.currentTarget;
        const idUcenec = item.dataset.idUcenec;
        const imeUcenca = item.dataset.imeUcenca;
        
        // Odstrani 'active' iz vseh in dodaj izbranemu
        document.querySelectorAll('.ucenec-item').forEach(i => i.classList.remove('active'));
        item.classList.add('active');

        const header = document.getElementById('ucenec-pregled-header');
        const vsebina = document.getElementById('ucenec-pregled-vsebina');
        
        header.textContent = `Oddaje učenca: ${imeUcenca}`;
        vsebina.innerHTML = 'Nalaganje oddaj...';

        try {
            // Predpostavljamo, da imamo ločen ajax_ucenec_oddaje.php za to
            const response = await fetch('ajax_ucenec_oddaje.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id_ucenec: idUcenec, id_ucitelja: userId })
            });
            
            const html = await response.text();
            vsebina.innerHTML = html;
            
            // Po nalaganju vsebine nastavimo poslušalce za gumbe za ocenjevanje
            setupGradingListeners();

        } catch (error) {
            vsebina.innerHTML = '<p style="color: red;">Napaka pri nalaganju oddaj za tega učenca.</p>';
            console.error('Fetch error:', error);
        }
    }
    
    // 2. Nastavitev poslušalcev za ocenjevanje
    function setupGradingListeners() {
        document.querySelectorAll('.pregled-oddaje-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const idOddaja = this.dataset.oddajaId;
                const imeUcenca = this.dataset.ucenecIme;
                prikaziModalZaOcenjevanje(idOddaja, imeUcenca);
            });
        });
    }

    // Reusable Drag & Drop init
    function initDropzone({ wrapperId, inputId, fileNameId }) {
        const wrapper = document.getElementById(wrapperId);
        const input = document.getElementById(inputId);
        const fileName = document.getElementById(fileNameId);
        if (!wrapper || !input) return;

        const browseBtn = wrapper.querySelector('.dz-browse');

        // Click to open dialog
        if (browseBtn) browseBtn.addEventListener('click', () => input.click());
        wrapper.addEventListener('click', (e) => {
            // avoid double triggering when clicking browse button
            if (e.target.classList.contains('dz-browse')) return;
            input.click();
        });

        // Drag over/leave styling
        ;['dragenter','dragover'].forEach(evt => {
            wrapper.addEventListener(evt, (e) => {
                e.preventDefault();
                e.stopPropagation();
                wrapper.classList.add('dragover');
            });
        });
        ;['dragleave','dragend','drop'].forEach(evt => {
            wrapper.addEventListener(evt, (e) => {
                e.preventDefault();
                e.stopPropagation();
                wrapper.classList.remove('dragover');
            });
        });

        // Handle drop
        wrapper.addEventListener('drop', (e) => {
            if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                updateDzFileName();
            }
        });

        // Handle manual selection
        input.addEventListener('change', updateDzFileName);

        function updateDzFileName() {
            if (!fileName) return;
            if (input.files && input.files.length) {
                const names = Array.from(input.files).map(f => f.name).join(', ');
                fileName.textContent = names;
            } else {
                fileName.textContent = 'Ni izbrane datoteke';
            }
        }
    }

    // 3. Prikaz Modala za ocenjevanje
    async function prikaziModalZaOcenjevanje(idOddaja, imeUcenca) {
        const modalNaslov = document.getElementById('grading-modal-naslov');
        const modalVsebina = document.getElementById('grading-modal-vsebina');
        
        modalNaslov.textContent = `Ocenjevanje oddaje za ${imeUcenca}`;
        modalVsebina.innerHTML = 'Nalaganje detajlov...';
        gradingModal.style.display = "block";

        try {
            // Uporabimo obstoječo logiko iz ajax_oddaja_pregled.php (ki vključuje formo)
            const response = await fetch('ajax_oddaja_pregled.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id_oddaja: idOddaja })
            });
            
            const html = await response.text();
            modalVsebina.innerHTML = html;
            
            // Nastavi poslušalca za formo za ocenjevanje
            const gradingForm = document.getElementById('grading-form');
            if (gradingForm) {
                gradingForm.addEventListener('submit', handleGradingSubmit);
            }
        } catch (error) {
            modalVsebina.innerHTML = '<p style="color: red;">Napaka pri nalaganju detajlov oddaje.</p>';
            console.error('Fetch error:', error);
        }
    }
    
    // 4. Obravnava oddaje forme za ocenjevanje
    async function handleGradingSubmit(e) {
        e.preventDefault();
        
        const form = e.currentTarget;
        const formData = new FormData(form);
        
        const gradeBtn = form.querySelector('button[type="submit"]');
        gradeBtn.disabled = true;
        gradeBtn.textContent = 'Ocenjevanje...';

        try {
            // Uporabimo obstoječo logiko iz ajax_ocenjevanje.php
            const response = await fetch('ajax_ocenjevanje.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            alert(result.message);
            
            if (result.success) {
                gradingModal.style.display = "none";
                // Ponovno naloži seznam oddaj za osvežitev statusa/ocene
                document.querySelector('.ucenec-item.active').click(); 
            } else {
                gradeBtn.disabled = false;
                gradeBtn.textContent = 'Shrani Oceno';
            }
        } catch (error) {
            alert('Napaka pri ocenjevanju: ' + error.message);
            gradeBtn.disabled = false;
            gradeBtn.textContent = 'Shrani Oceno';
        }
    }
    
    // ----------------------------------------------------
    // ZAVIHEK: MOJE NALOGE (Upravljanje z nalogami)
    // ----------------------------------------------------

    // 1. (Odstranjeno) Prikaz forme za novo nalogo - zdaj ima svoj zavihek "Nova Naloga"
    
    // 2. Obravnava ustvarjanja naloge
    async function handleNalogaCreate(e) {
        e.preventDefault();
        
        const form = e.currentTarget;
        const formData = new FormData(form);
        
        const nalogaBtn = form.querySelector('button[type="submit"]');
        nalogaBtn.disabled = true;
        nalogaBtn.textContent = 'Shranjevanje...';

        try {
            // Uporabimo obstoječo logiko iz ajax_naloga.php?action=create
            const response = await fetch('ajax_naloga.php?action=create', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            alert(result.message);
            
            if (result.success) {
                // Če je uspešno, osveži stran (ponovno naloži seznam nalog)
                window.location.reload(); 
            } else {
                nalogaBtn.disabled = false;
                nalogaBtn.textContent = 'Objavi Nalogo';
            }
        } catch (error) {
            alert('Napaka pri komunikaciji s strežnikom.');
            nalogaBtn.disabled = false;
            nalogaBtn.textContent = 'Objavi Nalogo';
        }
    }

    // 3. Nalaganje detajlov obstoječe naloge (in njenih oddaj)
    async function handleNalogaSelect(e) {
        e.preventDefault();
        const item = e.currentTarget;
        const idNaloga = item.dataset.idNaloga;
        const idPredmet = item.dataset.idPredmet;
        const imePredmeta = item.dataset.imePredmeta;
        const naslovNaloge = item.dataset.naslov;
        
        // Odstrani 'active' iz vseh in dodaj izbranemu
        document.querySelectorAll('.naloga-item').forEach(i => i.classList.remove('active'));
        item.classList.add('active');

        const detajliHeader = document.getElementById('naloga-detajli-header');
        const detajliVsebina = document.getElementById('naloga-detajli-vsebina');
        
        detajliHeader.textContent = `Pregled naloge: ${naslovNaloge}`;
        detajliVsebina.innerHTML = 'Nalaganje detajlov...';

        try {
            // Uporabimo obstoječo logiko iz ajax_naloga.php (ki naloži naloga_ucitelj.php)
            const response = await fetch('ajax_naloga.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    id_naloga: idNaloga, // Specifičen ID naloge
                    id_predmet: idPredmet,
                    id_ucitelja: userId,
                    vloga: 'ucitelj' 
                })
            });
            
            const html = await response.text();
            detajliVsebina.innerHTML = html;
            
            // Po nalaganju vsebine nastavimo poslušalce za gumbe (ocenjevanje, brisanje)
            setupTeacherDetailListeners();

        } catch (error) {
            detajliVsebina.innerHTML = '<p style="color: red;">Napaka pri nalaganju detajlov naloge.</p>';
            console.error('Fetch error:', error);
        }
    }
    
    // 4. Nastavitev poslušalcev za ocenjevanje in brisanje znotraj detajlov naloge
    function setupTeacherDetailListeners() {
        // Poslušalec za gumb 'Pregled/Oceni' (ponovno uporabi isto funkcijo kot pri zavihku "Učenci")
        setupGradingListeners(); 

        // Poslušalec za gumb 'Izbriši nalogo'
        const deleteBtn = document.getElementById('delete-naloga-btn');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', handleNalogaDelete);
        }
    }
    
    // 5. Obravnava brisanja naloge
    async function handleNalogaDelete(e) {
        e.preventDefault();
        const deleteBtn = e.currentTarget;
        const idNaloga = deleteBtn.dataset.idNaloga;

        if (!confirm("Ali ste prepričani, da želite IZBRISATI to nalogo in VSE oddaje učencev zanjo? Ta akcija je NEPOVRATNA!")) {
            return;
        }
        
        deleteBtn.disabled = true;
        deleteBtn.textContent = 'Brisanje...';

        try {
            // Uporabimo obstoječo logiko iz ajax_naloga_delete.php
            const response = await fetch('ajax_naloga_delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id_naloga: idNaloga })
            });
            
            const result = await response.json();
            alert(result.message);
            
            if (result.success) {
                // Če je uspešno, osveži stran (ponovno naloži seznam nalog)
                window.location.reload(); 
            } else {
                deleteBtn.disabled = false;
                deleteBtn.textContent = 'Izbriši to nalogo';
            }
        } catch (error) {
            alert('Napaka pri komunikaciji s strežnikom med brisanjem.');
            deleteBtn.disabled = false;
            deleteBtn.textContent = 'Izbriši to nalogo';
        }
    }
</script>
</body>
</html>