<?php
session_start();
require_once 'povezava.php'; // Predpostavljamo, da je 'povezava.php' na voljo

// Preverjanje vloge in preusmeritev
if (!isset($_SESSION['user_id']) || $_SESSION['vloga'] !== 'ucenec') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$ime_priimek = 'Neznan uporabnik';
$predmeti_ucenec = [];
$vse_naloge_ucenec = [];

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Pridobitev imena
    $sql_ime = "SELECT ime, priimek FROM uporabnik WHERE id_uporabnik = ?";
    $stmt_ime = $pdo->prepare($sql_ime);
    $stmt_ime->execute([$user_id]);
    $uporabnik_data = $stmt_ime->fetch();
    $ime_priimek = $uporabnik_data ? $uporabnik_data['ime'] . ' ' . $uporabnik_data['priimek'] : 'Neznan uporabnik';

    // 2. Pridobitev vseh predmetov, ki jih ima učenec
    $sql_predmeti = "
        SELECT DISTINCT p.id_predmet, p.ime_predmeta, u.id_uporabnik AS id_ucitelja, u.ime AS ime_ucitelja, u.priimek AS priimek_ucitelja
        FROM ucenec_predmet up
        JOIN predmet p ON up.id_predmet = p.id_predmet
        JOIN uporabnik u ON up.id_ucitelj = u.id_uporabnik
        WHERE up.id_ucenec = ?
        ORDER BY p.ime_predmeta
    ";
    $stmt_predmeti = $pdo->prepare($sql_predmeti);
    $stmt_predmeti->execute([$user_id]);
    $predmeti_ucenec = $stmt_predmeti->fetchAll();
    
    // 3. Pridobitev vseh nalog in oddaj učenca za vse njegove predmete
    // Združimo naloge in oddaje, da lahko na PHP strani implementiramo logiko 7-dnevnega podaljšanja
    $sql_vse_naloge = "
        SELECT 
            n.id_naloga, n.naslov, n.opis_naloge, n.rok_oddaje, n.datum_objave, 
            p.ime_predmeta, p.id_predmet,
            u.ime AS ime_ucitelja, u.priimek AS priimek_ucitelja,
            o.id_oddaja, o.datum_oddaje, o.ocena, o.status, o.komentar_ucitelj
        FROM naloga n
        JOIN predmet p ON n.id_predmet = p.id_predmet
        JOIN uporabnik u ON n.id_ucitelj = u.id_uporabnik
        JOIN ucenec_predmet up ON n.id_predmet = up.id_predmet AND n.id_ucitelj = up.id_ucitelj
        LEFT JOIN oddaja o ON n.id_naloga = o.id_naloga AND o.id_ucenec = ?
        WHERE up.id_ucenec = ?
        ORDER BY n.rok_oddaje DESC
    ";
    $stmt_naloge = $pdo->prepare($sql_vse_naloge);
    $stmt_naloge->execute([$user_id, $user_id]);
    $vse_naloge_raw = $stmt_naloge->fetchAll();

    $aktivne_naloge = [];
    $oddane_naloge = [];
    $danes = new DateTime();

    foreach ($vse_naloge_raw as $naloga) {
        $rok_oddaje_original = new DateTime($naloga['rok_oddaje']);
        $rok_oddaje_dejanski = clone $rok_oddaje_original;

        $je_oddano = !empty($naloga['id_oddaja']);
        $status = $naloga['status'];
        $ocena = $naloga['ocena'];
        
        $podaljsan_rok = false;

        // Logika 7-dnevnega podaljšanja roka po oceni 'ND' (Ni Dopolnjeno)
        if ($je_oddano && strtoupper($ocena) === 'ND') {
            // Če je ocena ND, podaljšamo rok za 7 dni, štet od originalnega roka.
            $rok_oddaje_dejanski = clone $rok_oddaje_original;
            $rok_oddaje_dejanski->modify('+7 days');
            $podaljsan_rok = true;
        }

        $je_aktivna = false;

        if (!$je_oddano && $danes < $rok_oddaje_original) {
            // 1. Še ni oddano in rok ni potekel.
            $je_aktivna = true;
        } elseif ($je_oddano && strtoupper($ocena) === 'ND' && $danes < $rok_oddaje_dejanski) {
            // 2. Oddano, ocena ND in podaljšan rok ni potekel.
            $je_aktivna = true;
        }

        if ($je_aktivna) {
            $naloga['rok_oddaje_prikaz'] = $podaljsan_rok ? $rok_oddaje_dejanski->format('d.m.Y H:i') . ' (Podaljšano)' : $rok_oddaje_original->format('d.m.Y H:i');
            $aktivne_naloge[] = $naloga;
        }

        if ($je_oddano) {
            $oddane_naloge[] = $naloga;
        }
    }

} catch (\PDOException $e) {
    error_log("Database Error (Učenec): " . $e->getMessage()); 
    die("Prišlo je do napake v sistemu. Prosimo, poskusite kasneje.");
}
?>
<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Učilnica za Učence</title>
    <style>
        /* Osnovni Stil za celoten sistem */
        body { margin: 0; font-family: Arial, sans-serif; background: #f9f9f9; }
        header { background: #4a86e8; color: white; display: flex; justify-content: space-between; align-items: center; padding: 15px 30px; }
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
            color: #4a86e8; 
            border-bottom: 3px solid #4a86e8; 
            font-weight: bold; 
            background: #eef5ff;
        }
        .tab-content { display: none; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .tab-content.active { display: block; }

        /* Stil za Tabela */
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #f0f0f0; font-weight: bold; }
        tr:nth-child(even) { background-color: #f7f7f7; }

        /* Stil za Filtre */
        .filters { margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; background: #fff; border-radius: 6px; display: flex; gap: 20px; align-items: center; }

        /* Statusi */
        .status-neoddano { color: red; font-weight: bold; }
        .status-oddano { color: orange; font-weight: bold; }
        .status-ocenjeno { color: green; font-weight: bold; }
    </style>
</head>
<body>

<header>
    <div class="logo">UČILNICA</div>
    <div>Prijavljen: **<?= htmlspecialchars($ime_priimek) ?>** (Učenec) | <a href="logout.php" style="color: #ffdddd;">Odjava</a></div>
</header>

<div class="container">
    <h2>Moja Učilnica</h2>
    
    <div style="margin-bottom: 30px; padding: 15px; background: #eef; border-left: 5px solid #4a86e8; border-radius: 4px;">
        <strong>Tvoji predmeti:</strong> 
        <?php 
            $predmet_imena = array_column($predmeti_ucenec, 'ime_predmeta');
            echo implode(', ', array_unique($predmet_imena)); 
        ?>
    </div>
    
    <div class="tabs">
        <button class="tab-button active" onclick="openTab(event, 'aktivne')">Aktivne Naloge (<?= count($aktivne_naloge) ?>)</button>
        <button class="tab-button" onclick="openTab(event, 'oddane')">Oddaje in Ocene (<?= count($oddane_naloge) ?>)</button>
    </div>

    <div id="aktivne" class="tab-content active">
        <h3>Aktivne Naloge</h3>
        
        <div class="filters">
            <label for="filter-predmet-aktivne">Filtriraj po predmetu:</label>
            <select id="filter-predmet-aktivne" onchange="filterNaloge('aktivne')">
                <option value="">Vsi predmeti</option>
                <?php foreach (array_unique(array_column($predmeti_ucenec, 'ime_predmeta')) as $p): ?>
                    <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <table id="tabela-aktivne">
            <thead>
                <tr>
                    <th>Naslov Naloge</th>
                    <th>Predmet</th>
                    <th>Učitelj</th>
                    <th>Rok Oddaje</th>
                    <th>Status</th>
                    <th>Akcija</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($aktivne_naloge as $naloga): 
                    // Določitev statusa
                    $status_tekst = 'Ni oddano';
                    $status_klasa = 'status-neoddano';
                    if (!empty($naloga['id_oddaja'])) {
                         $status_tekst = 'Oddano (Čaka na oceno)';
                         $status_klasa = 'status-oddano';
                         if (strtoupper($naloga['ocena']) === 'ND') {
                            $status_tekst = 'ND - Potrebna dopolnitev (Rok podaljšan)';
                            $status_klasa = 'status-neoddano';
                         }
                    }
                    ?>
                    <tr data-predmet="<?= htmlspecialchars($naloga['ime_predmeta']) ?>" data-datum="<?= htmlspecialchars($naloga['rok_oddaje']) ?>">
                        <td><?= htmlspecialchars($naloga['naslov']) ?></td>
                        <td><?= htmlspecialchars($naloga['ime_predmeta']) ?></td>
                        <td><?= htmlspecialchars($naloga['ime_ucitelja'] . ' ' . $naloga['priimek_ucitelja']) ?></td>
                        <td><?= htmlspecialchars($naloga['rok_oddaje_prikaz'] ?? date('d.m.Y H:i', strtotime($naloga['rok_oddaje']))) ?></td>
                        <td class="<?= $status_klasa ?>"><?= $status_tekst ?></td>
                        <td>
                            <button onclick="prikaziNalogo('<?= $naloga['id_naloga'] ?>', '<?= $naloga['ime_predmeta'] ?>', '<?= $naloga['ime_ucitelja'] . ' ' . $naloga['priimek_ucitelja'] ?>')" style="padding: 5px 10px; background: #4a86e8; color: white; border: none; cursor: pointer;">
                                Oddaj / Pregled
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div id="naloga-modal" class="modal">
            <div class="modal-content">
                <span class="close-btn">&times;</span>
                <h4 id="naloga-modal-naslov"></h4>
                <div id="naloga-modal-vsebina">Nalaganje...</div>
            </div>
        </div>
    </div>

    <div id="oddane" class="tab-content">
        <h3>Oddane Naloge in Ocene</h3>
        
        <div class="filters">
            <label for="filter-predmet-oddane">Filtriraj po predmetu:</label>
            <select id="filter-predmet-oddane" onchange="filterNaloge('oddane')">
                <option value="">Vsi predmeti</option>
                <?php foreach (array_unique(array_column($predmeti_ucenec, 'ime_predmeta')) as $p): ?>
                    <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="filter-ocenjeno-oddane">Status:</label>
            <select id="filter-ocenjeno-oddane" onchange="filterNaloge('oddane')">
                <option value="">Vsi statusi</option>
                <option value="Ocenjeno">Ocenjeno</option>
                <option value="Oddano">Neocenjeno</option>
            </select>
        </div>

        <table id="tabela-oddane">
            <thead>
                <tr>
                    <th>Naslov Naloge</th>
                    <th>Predmet</th>
                    <th>Datum Oddaje</th>
                    <th>Ocena</th>
                    <th>Komentar Učitelja</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                // Sortiramo oddane naloge po datumu oddaje
                usort($oddane_naloge, function($a, $b) {
                    return strtotime($b['datum_oddaje']) - strtotime($a['datum_oddaje']);
                });
                
                foreach ($oddane_naloge as $oddaja): 
                    $status_oddaje = empty($oddaja['ocena']) ? 'Oddano' : 'Ocenjeno';
                ?>
                    <tr 
                        data-predmet="<?= htmlspecialchars($oddaja['ime_predmeta']) ?>" 
                        data-status="<?= $status_oddaje ?>"
                        data-datum="<?= htmlspecialchars($oddaja['datum_oddaje']) ?>"
                    >
                        <td><?= htmlspecialchars($oddaja['naslov']) ?></td>
                        <td><?= htmlspecialchars($oddaja['ime_predmeta']) ?></td>
                        <td><?= date('d.m.Y H:i', strtotime($oddaja['datum_oddaje'])) ?></td>
                        <td style="font-weight: bold; color: <?= $oddaja['ocena'] == 'ND' ? 'red' : (empty($oddaja['ocena']) ? 'gray' : 'green') ?>;"><?= htmlspecialchars($oddaja['ocena'] ?? 'Čaka') ?></td>
                        <td><?= htmlspecialchars($oddaja['komentar_ucitelj'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

<script>
    // Nastavitev zavihkov
    function openTab(evt, tabName) {
        var i, tabcontent, tablinks;
        tabcontent = document.getElementsByClassName("tab-content");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
        }
        tablinks = document.getElementsByClassName("tab-button");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].className = tablinks[i].className.replace(" active", "");
        }
        document.getElementById(tabName).style.display = "block";
        evt.currentTarget.className += " active";
        
        // Ob menjavi zavihka ponovno uveljavi filtre za aktivni zavihek
        filterNaloge(tabName);
    }
    
    // Funkcija za filtriranje tabel
    function filterNaloge(tabId) {
        const tabela = document.getElementById(`tabela-${tabId}`);
        if (!tabela) return;

        const filterPredmet = document.getElementById(`filter-predmet-${tabId}`).value;
        const filterStatus = document.getElementById(`filter-ocenjeno-${tabId}`)?.value;
        // Za filtriranje po datumu bi potrebovali še vnosno polje

        const rows = tabela.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

        for (let i = 0; i < rows.length; i++) {
            const row = rows[i];
            const predmet = row.getAttribute('data-predmet');
            const status = row.getAttribute('data-status');
            
            let showRow = true;

            if (filterPredmet && predmet !== filterPredmet) {
                showRow = false;
            }

            if (tabId === 'oddane' && filterStatus) {
                if (status !== filterStatus) {
                    showRow = false;
                }
            }

            row.style.display = showRow ? '' : 'none';
        }
    }


    // Nastavitev Modala (za Oddajo/Pregled naloge)
    const modal = document.getElementById('naloga-modal');
    const closeBtn = document.getElementsByClassName("close-btn")[0];

    closeBtn.onclick = function() {
        modal.style.display = "none";
    }

    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }
    
    // Asinhrono nalaganje detajlov naloge (re-using existing AJAX logic)
    async function prikaziNalogo(id_naloga, ime_predmeta, ime_ucitelja) {
        const nalogaVsebina = document.getElementById('naloga-modal-vsebina');
        document.getElementById('naloga-modal-naslov').textContent = `Naloga: ${ime_predmeta} - ${ime_ucitelja}`;
        nalogaVsebina.innerHTML = 'Nalaganje...';
        modal.style.display = "block";

        try {
            // Predpostavljamo, da imamo datoteko ajax_naloga_pregled.php, ki nalaga naloga_ucenec.php
            const response = await fetch('ajax_naloga.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    id_naloga: id_naloga, // Pošljemo specifičen ID naloge
                    id_predmet: null, // Ni potrebno, če pošljemo ID naloge
                    id_ucitelja: null, // Ni potrebno
                    vloga: 'ucenec' 
                })
            });
            
            const html = await response.text();
            nalogaVsebina.innerHTML = html;
            
            // Če je naloga naložena, poišči oddaja-form in dodaj poslušalca
            const oddajaForm = document.getElementById('oddaja-form');
            if (oddajaForm) {
                oddajaForm.addEventListener('submit', handleOddajaSubmit);
            }
            
        } catch (error) {
            nalogaVsebina.innerHTML = '<p style="color: red;">Napaka pri nalaganju detajlov naloge.</p>';
            console.error('Fetch error:', error);
        }
    }
    
    // Funkcija za obravnavo oddaje (re-using ajax_oddaja.php logic)
    async function handleOddajaSubmit(e) {
        e.preventDefault();
        
        const form = e.currentTarget;
        const formData = new FormData(form);
        
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Oddajanje...';

        try {
            const response = await fetch('ajax_oddaja.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            alert(result.message);
            
            if (result.success) {
                // Če je uspešno, ponovno naloži stran ali modal za osvežitev statusa
                modal.style.display = "none";
                window.location.reload(); // Najenostavnejša rešitev za osvežitev seznama
            } else {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Poskusi ponovno oddati';
            }
        } catch (error) {
            alert('Napaka pri komunikaciji s strežnikom med oddajo.');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Oddaj nalogo';
        }
    }

    // Inicialni prikaz zavihka in filtrov
    document.addEventListener('DOMContentLoaded', () => {
        openTab({ currentTarget: document.querySelector('.tab-button.active') }, 'aktivne');
    });

</script>
</body>
</html>