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
$vloga = $_SESSION['vloga']; // Uporabno za razlikovanje admin/ucitelj

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 1. Pridobitev imena
    $sql_ime = "SELECT ime, priimek FROM uporabnik WHERE id_uporabnik = ?";
    $stmt_ime = $pdo->prepare($sql_ime);
    $stmt_ime->execute([$user_id]);
    $uporabnik_data = $stmt_ime->fetch();
    $ime_priimek = $uporabnik_data ? $uporabnik_data['ime'] . ' ' . $uporabnik_data['priimek'] : 'Neznan uporabnik';

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
        SELECT n.id_naloga, n.naslov, n.datum_objave, n.rok_oddaje, p.ime_predmeta
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
    <style>
        /* Osnovni Stil */
        body { margin: 0; font-family: Arial, sans-serif; background: #f9f9f9; }
        header { background: #1c4587; color: white; display: flex; justify-content: space-between; align-items: center; padding: 15px 30px; }
        header .logo { font-weight: bold; font-size: 18px; }
        .container { max-width: 1200px; margin: 20px auto; padding: 0 15px; }
        
        /* Stil za Zavihke (Tabs) */
        .tabs { display: flex; margin-bottom: 20px; border-bottom: 2px solid #ddd; }
        .tab-button { 
            padding: 10px 20px; 
            cursor: pointer; 
            border: none; 
            background: none; 
            font-size: 16px; 
            color: #555; 
            transition: all 0.3s;
        }
        .tab-button.active { 
            color: #1c4587; 
            border-bottom: 3px solid #1c4587; 
            font-weight: bold; 
            background: #eef5ff;
        }
        .tab-content { display: none; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .tab-content.active { display: block; }
        
        /* Stil za Levo/Desno postavitev */
        .split-content { display: flex; gap: 20px; }
        .left-panel { flex: 1; max-width: 300px; background: #f4f4f4; padding: 15px; border-radius: 6px; }
        .right-panel { flex: 3; background: white; padding: 20px; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        
        /* Seznam predmetov/učencev */
        .list-group-header { font-weight: bold; padding: 10px 0; border-bottom: 1px solid #ddd; margin-top: 15px; }
        .list-item { padding: 8px 0; cursor: pointer; border-bottom: 1px dotted #eee; }
        .list-item:hover, .list-item.active { background: #e0eaff; font-weight: bold; }
        .naloga-item { padding: 10px; border: 1px solid #ddd; margin-bottom: 10px; border-radius: 4px; cursor: pointer;}
        .naloga-item.active { background: #e0eaff; border-color: #1c4587; font-weight: bold; }
        
        .day-header { font-size: 1.1em; font-weight: bold; margin-top: 15px; padding-bottom: 5px; border-bottom: 2px solid #1c4587; }
        
        .modal {
            display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);
        }
        .modal-content {
            background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 800px; border-radius: 8px;
        }
        .close-btn { color: #aaa; float: right; font-size: 28px; font-weight: bold; }
        .close-btn:hover, .close-btn:focus { color: black; text-decoration: none; cursor: pointer; }
    </style>
</head>
<body>

<header>
    <div class="logo">UČILNICA</div>
    <div>Prijavljen: **<?= htmlspecialchars($ime_priimek) ?>** (<?= $vloga === 'admin' ? 'Administrator' : 'Učitelj' ?>) | <a href="logout.php" style="color: #ffdddd;">Odjava</a></div>
</header>

<div class="container">
    <h2>Nadzorna Plošča Učitelja</h2>
    
    <div class="tabs">
        <button class="tab-button active" onclick="openTab(event, 'ucenci')">Učenci in Oddaje</button>
        <button class="tab-button" onclick="openTab(event, 'naloge')">Moje Naloge</button>
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
                            >
                                <strong><?= htmlspecialchars($naloga['naslov']) ?></strong> (<?= htmlspecialchars($naloga['ime_predmeta']) ?>)
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <hr style="margin-top: 20px;">
                <button onclick="prikaziObrazecZaNovoNalogo()" style="width: 100%; padding: 10px; background: green; color: white; border: none; cursor: pointer;">
                    Objavi Novo Nalogo
                </button>
            </div>
            
            <div class="right-panel">
                <h3 id="naloga-detajli-header">Izberite nalogo ali objavite novo</h3>
                <div id="naloga-detajli-vsebina">
                    <p>Za dodajanje nove naloge uporabite gumb na levi 'Objavi Novo Nalogo'.</p>
                </div>
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
            document.getElementById('naloga-detajli-header').textContent = 'Izberite nalogo ali objavite novo';
            document.getElementById('naloga-detajli-vsebina').innerHTML = '<p>Za dodajanje nove naloge uporabite gumb na levi \'Objavi Novo Nalogo\'.</p>';
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

    // 1. Prikaz forme za novo nalogo
    function prikaziObrazecZaNovoNalogo() {
        // Prikaži header in naloži obrazec (za to potrebujemo ID-je predmetov)
        document.querySelectorAll('.naloga-item').forEach(i => i.classList.remove('active'));
        
        const detajliVsebina = document.getElementById('naloga-detajli-vsebina');
        document.getElementById('naloga-detajli-header').textContent = 'Objavi Novo Nalogo';
        
        // Ustvarimo formo dinamično, ker potrebujemo seznam predmetov
        let htmlForm = `
            <form id="naloga-form" method="POST" enctype="multipart/form-data">
                <div>
                    <label for="id_predmet">Izberi Predmet:</label>
                    <select id="id_predmet" name="id_predmet" required style="width: 100%; padding: 8px; margin-bottom: 10px;">
                        <option value="">-- Izberi predmet --</option>
                        <?php 
                        foreach (array_values($ucenci_po_predmetih) as $predmet_data) {
                            echo '<option value="' . htmlspecialchars($predmet_data['id_predmet']) . '">' . htmlspecialchars($predmet_data['ime_predmeta']) . '</option>';
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
                    <input type="file" id="datoteka" name="datoteka" style="margin-bottom: 15px;">
                </div>
                <button type="submit" style="padding: 10px 20px; background: #1c4587; color: white; border: none; cursor: pointer;">Objavi Nalogo</button>
            </form>
        `;
        detajliVsebina.innerHTML = htmlForm;
        
        // Nastavi poslušalca za formo za ustvarjanje naloge
        const nalogaForm = document.getElementById('naloga-form');
        if (nalogaForm) {
            nalogaForm.addEventListener('submit', handleNalogaCreate);
        }
    }
    
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
        
        // Odstrani 'active' iz vseh in dodaj izbranemu
        document.querySelectorAll('.naloga-item').forEach(i => i.classList.remove('active'));
        item.classList.add('active');

        const detajliHeader = document.getElementById('naloga-detajli-header');
        const detajliVsebina = document.getElementById('naloga-detajli-vsebina');
        
        detajliHeader.textContent = `Pregled naloge: ${imePredmeta}`;
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