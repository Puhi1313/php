<?php
session_start();
require_once 'povezava.php';

// Preverjanje prijave in preusmeritev
if (!isset($_SESSION['user_id']) || !isset($_SESSION['vloga'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$vloga = $_SESSION['vloga'];
$ime_priimek = 'Neznan uporabnik';
$urnik = [];

try {
    // Pridobitev imena in priimka
    $sql_ime = "SELECT ime, priimek FROM uporabnik WHERE id_uporabnik = ?";
    $stmt_ime = $pdo->prepare($sql_ime);
    $stmt_ime->execute([$user_id]);
    $uporabnik_data = $stmt_ime->fetch();
    $ime_priimek = $uporabnik_data ? $uporabnik_data['ime'] . ' ' . $uporabnik_data['priimek'] : 'Neznan uporabnik';

    // Pridobitev urnika
    if ($vloga === 'ucenec') {
        // UČENEC: Pridobi urnik
        $sql_urnik = "
            SELECT ur.dan, ur.ura, p.ime_predmeta, p.id_predmet, u.id_uporabnik AS id_ucitelja, u.ime AS ime_ucitelja, u.priimek AS priimek_ucitelja
            FROM urnik ur
            JOIN predmet p ON ur.id_predmet = p.id_predmet
            JOIN uporabnik u ON ur.id_ucitelj = u.id_uporabnik
            WHERE ur.id_predmet IN (SELECT id_predmet FROM ucenec_predmet WHERE id_ucenec = ?)
            ORDER BY FIELD(dan,'Ponedeljek','Torek','Sreda','Četrtek','Petek'), ura
        ";
        $stmt_urnik = $pdo->prepare($sql_urnik);
        $stmt_urnik->execute([$user_id]);
    } elseif ($vloga === 'ucitelj' || $vloga === 'admin') {
        // UČITELJ: Pridobi urnik
        $sql_urnik = "
            SELECT ur.dan, ur.ura, p.ime_predmeta, p.id_predmet, u.id_uporabnik AS id_ucitelja, u.ime AS ime_ucitelja, u.priimek AS priimek_ucitelja
            FROM urnik ur
            JOIN predmet p ON ur.id_predmet = p.id_predmet
            JOIN uporabnik u ON ur.id_ucitelj = u.id_uporabnik
            WHERE ur.id_ucitelj = ?
            ORDER BY FIELD(dan,'Ponedeljek','Torek','Sreda','Četrtek','Petek'), ura
        ";
        $stmt_urnik = $pdo->prepare($sql_urnik);
        $stmt_urnik->execute([$user_id]);
    }
    
    $urnik_raw = $stmt_urnik->fetchAll();

    // Organizacija urnika po dneh in urah
    foreach ($urnik_raw as $ura) {
        // Ključ 'urnik_id' je kombinacija id-ja predmeta in id-ja učitelja in dneva
        $urnik_id = $ura['id_predmet'] . '-' . $ura['id_ucitelja'] . '-' . $ura['dan']; 
        $urnik[$ura['dan']][$urnik_id] = [
            'ura' => $ura['ura'],
            'id_predmet' => $ura['id_predmet'],
            'ime_predmeta' => $ura['ime_predmeta'],
            'id_ucitelja' => $ura['id_ucitelja'],
            'ime_ucitelja' => $ura['ime_ucitelja'] . ' ' . $ura['priimek_ucitelja']
        ];
    }

} catch (\PDOException $e) {
    die("Napaka pri bazi podatkov: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="sl">
<head><meta charset="UTF-8"><title>Učilnica</title>
<style>
    /* Styling za izboljšano učilnico - Dve koloni */
    body { margin: 0; font-family: Arial, sans-serif; background: #f9f9f9; }
    header { background: #ddd; display: flex; justify-content: space-between; align-items: center; padding: 15px 30px; }
    .main-grid { display: grid; grid-template-columns: 350px 1fr; min-height: calc(100vh - 58px); }
    .sidebar { background: #fff; border-right: 1px solid #eee; padding: 20px; overflow-y: auto; }
    .content-area { padding: 20px; overflow-y: auto; background: #f4f4f4; }
    
    /* Stil za urnik */
    .day-header { font-size: 1.1em; font-weight: bold; margin-top: 15px; padding-bottom: 5px; border-bottom: 2px solid #ccc; cursor: pointer; color: #007bff; }
    .day-header.active { color: #28a745; }
    .predmet-item { 
        padding: 10px; margin: 5px 0; background: #e9ecef; border-radius: 5px; 
        cursor: pointer; transition: background 0.2s; 
    }
    .predmet-item:hover { background: #cfd8e3; }
    .predmet-item.active { background: #007bff; color: white; }
    .predmet-info { font-size: 0.9em; color: #555; }
    .predmet-item.active .predmet-info { color: #fff; }

    /* Stil za detajle */
    .naloga-detajli h3 { color: #007bff; }
    .oddaja-form { background: #fff; padding: 15px; border: 1px solid #ccc; border-radius: 5px; margin-top: 20px; }
    .rok { color: red; font-weight: bold; }

</style>
</head>
<body>
<header>
  <div class="logo">REDOVALNICA</div>
  <div>Prijavljen: **<?php echo htmlspecialchars($ime_priimek); ?>** (<?php echo htmlspecialchars(ucfirst($vloga)); ?>) | <a href="logout.php">Odjava</a></div>
</header>

<div class="main-grid">
    <div class="sidebar">
        <h2>Urnik</h2>
        <?php if (empty($urnik)): ?>
            <p>Urnik ni določen ali nimate predmetov.</p>
        <?php else: ?>
            <?php foreach (array_keys($urnik) as $dan): ?>
                <div class="day-header" data-dan="<?php echo htmlspecialchars($dan); ?>">
                    <?php echo htmlspecialchars($dan); ?>
                </div>
                <div class="predmet-list" id="list-<?php echo htmlspecialchars($dan); ?>" style="display: none;">
                    <?php 
                    // Urejanje po uri za lepši prikaz
                    $ure_v_dnevu = $urnik[$dan];
                    usort($ure_v_dnevu, fn($a, $b) => $a['ura'] <=> $b['ura']);

                    foreach ($ure_v_dnevu as $item): 
                        // Združeni ID za AJAX poizvedbo
                        $data_id = $item['id_predmet'] . '-' . $item['id_ucitelja']; 
                    ?>
                        <div 
                            class="predmet-item" 
                            data-urnik-id="<?php echo htmlspecialchars($data_id); ?>"
                            data-ime-predmeta="<?php echo htmlspecialchars($item['ime_predmeta']); ?>"
                            data-id-predmet="<?php echo htmlspecialchars($item['id_predmet']); ?>"
                            data-id-ucitelja="<?php echo htmlspecialchars($item['id_ucitelja']); ?>"
                        >
                            **<?php echo htmlspecialchars($item['ura']); ?>. ura:** <?php echo htmlspecialchars($item['ime_predmeta']); ?><br>
                            <span class="predmet-info">Učitelj: <?php echo htmlspecialchars($item['ime_ucitelja']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="content-area">
        <h2 id="content-header">Izberite dan in predmet za prikaz detajlov</h2>
        <div id="content-details">
            </div>
    </div>
</div>

<script>
    const contentDetails = document.getElementById('content-details');
    const contentHeader = document.getElementById('content-header');
    const vloga = '<?php echo $vloga; ?>';
    const userId = <?php echo $user_id; ?>;

    // 1. Logika za odpiranje dnevov v Urniku
    document.querySelectorAll('.day-header').forEach(header => {
        header.addEventListener('click', (e) => {
            const dan = e.target.dataset.dan;
            const listDiv = document.getElementById('list-' + dan);
            
            // Zapri vse ostale in odstrani 'active'
            document.querySelectorAll('.predmet-list').forEach(list => list.style.display = 'none');
            document.querySelectorAll('.day-header').forEach(h => h.classList.remove('active'));
            document.querySelectorAll('.predmet-item').forEach(item => item.classList.remove('active'));

            // Odpri izbrani dan in aktiviraj header
            listDiv.style.display = 'block';
            e.target.classList.add('active');
            
            // Počisti detajle
            contentDetails.innerHTML = '';
            contentHeader.textContent = `Izberite predmet v ${dan}`;
        });
    });

    // 2. Logika za klik na Predmet/Uro (AJAX klic za podatke)
    document.querySelectorAll('.predmet-item').forEach(item => {
        item.addEventListener('click', (e) => {
            const target = e.currentTarget;
            const urnikId = target.dataset.urnikId;
            const imePredmeta = target.dataset.imePredmeta;
            const idPredmet = target.dataset.idPredmet;
            const idUcitelja = target.dataset.idUcitelja;
            
            // Odstrani aktivni status z vseh in ga nastavi na izbran predmet
            document.querySelectorAll('.predmet-item').forEach(i => i.classList.remove('active'));
            target.classList.add('active');
            
            contentHeader.textContent = `Detajli za: ${imePredmeta}`;
            contentDetails.innerHTML = '<p>Nalaganje naloge...</p>';
            
            // AJAX klic za pridobitev naloge (ali obrazca za kreiranje)
            fetch('ajax_naloga.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id_predmet: idPredmet,
                    id_ucitelja: idUcitelja,
                    user_id: userId,
                    vloga: vloga
                })
            })
            .then(response => response.text())
            .then(html => {
                contentDetails.innerHTML = html;
                setupFormListeners(); // Pripravi poslušalce dogodkov za morebitne obrazce
            })
            .catch(error => {
                contentDetails.innerHTML = '<p style="color: red;">Napaka pri nalaganju detajlov.</p>';
                console.error('Fetch error:', error);
            });
        });
    });
    
    // Funkcija za nastavitev poslušalcev obrazcev po nalaganju AJAX vsebine
    function setupFormListeners() {
        // --- LOGIKA ZA ODDAJO NALOGE (UČENEC) ---
        const oddajaForm = document.getElementById('oddaja-form');
        if (oddajaForm) {
            oddajaForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                formData.append('vloga', vloga);
                
                const oddajaBtn = this.querySelector('button[type="submit"]');
                oddajaBtn.disabled = true;
                oddajaBtn.textContent = 'Oddajanje...';

                try {
                    const response = await fetch('ajax_oddaja.php', {
                        method: 'POST',
                        body: formData // FormData deluje s fili in je enostavnejša za uporabo
                    });
                    
                    const result = await response.json();
                    alert(result.message);
                    oddajaBtn.textContent = result.success ? 'Uspešno oddano!' : 'Poskusi ponovno';
                    oddajaBtn.disabled = false;
                    
                    if (result.success) {
                        // Ponovno naloži detajle za posodobitev statusa oddaje
                        document.querySelector('.predmet-item.active').click(); 
                    }
                } catch (error) {
                    alert('Napaka pri komunikaciji s strežnikom.');
                    oddajaBtn.disabled = false;
                    oddajaBtn.textContent = 'Oddaj nalogo';
                }
            });
        }
        
        // --- LOGIKA ZA KREIRANJE NALOGE (UČITELJ) ---
        const nalogaForm = document.getElementById('naloga-form');
        if (nalogaForm) {
            nalogaForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                formData.append('vloga', vloga);
                
                const nalogaBtn = this.querySelector('button[type="submit"]');
                nalogaBtn.disabled = true;
                nalogaBtn.textContent = 'Shranjevanje...';

                try {
                    const response = await fetch('ajax_naloga.php?action=create', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    alert(result.message);
                    nalogaBtn.textContent = result.success ? 'Naloga shranjena!' : 'Poskusi ponovno';
                    nalogaBtn.disabled = false;
                    
                    if (result.success) {
                        // Ponovno naloži detajle za prikaz nove naloge
                        document.querySelector('.predmet-item.active').click(); 
                    }
                } catch (error) {
                    alert('Napaka pri komunikaciji s strežnikom.');
                    nalogaBtn.disabled = false;
                    nalogaBtn.textContent = 'Objavi Nalogo';
                }
            });
        }
    }

</script>
</body>
</html>