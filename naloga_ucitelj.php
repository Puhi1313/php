<?php 
// Ta datoteka je vključena v ajax_naloga.php, zato ima dostop do $pdo, $naloga, $id_predmet, $id_ucitelja in $seznam_nalog
// $seznam_nalog je array vseh nalog, $naloga je trenutno izbrana (ali zadnja) naloga

// Pridobivanje podatkov o oddajah, če obstaja aktivna naloga
$ucenci_za_prikaz = [];
if (!empty($naloga)) {
    // Pridobitev vseh oddaj za to nalogo
    try {
        $sql_oddaje = "
            SELECT o.*, u.ime, u.priimek
            FROM oddaja o
            JOIN uporabnik u ON o.id_ucenec = u.id_uporabnik
            WHERE o.id_naloga = ?
            ORDER BY u.priimek ASC
        ";
        $stmt_oddaje = $pdo->prepare($sql_oddaje);
        $stmt_oddaje->execute([$naloga['id_naloga']]);
        $oddaje = $stmt_oddaje->fetchAll();

        // Pridobitev vseh učencev za ta predmet za status 'Ni oddano'
        $sql_vsi_ucenci = "
            SELECT up.id_uporabnik, up.ime, up.priimek
            FROM ucenec_predmet ucp
            JOIN uporabnik up ON ucp.id_ucenec = up.id_uporabnik
            WHERE ucp.id_predmet = ? AND ucp.id_ucitelj = ?
            ORDER BY up.priimek ASC
        ";
        $stmt_vsi_ucenci = $pdo->prepare($sql_vsi_ucenci);
        $stmt_vsi_ucenci->execute([$id_predmet, $id_ucitelja]);
        $vsi_ucenci = $stmt_vsi_ucenci->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_UNIQUE);

        // Združite podatke: ustvarite seznam vseh učencev s statusom oddaje
        $oddaje_map = [];
        foreach ($oddaje as $oddaja) {
            $oddaje_map[$oddaja['id_ucenec']] = $oddaja;
        }

        foreach ($vsi_ucenci as $id => $ucenec) {
            $oddaja_status = isset($oddaje_map[$id]) ? $oddaje_map[$id] : [
                'id_uporabnik' => $id,
                'ime' => $ucenec['ime'],
                'priimek' => $ucenec['priimek'],
                'status' => 'Ni oddano',
                'datum_oddaje' => null,
                'ocena' => null,
                'id_oddaja' => null
            ];
            $ucenci_za_prikaz[] = $oddaja_status;
        }
        
        // Ponovno sortiraj po priimku, ker je PDO::FETCH_GROUP spremenil vrstni red
        usort($ucenci_za_prikaz, function($a, $b) {
            return strcmp($a['priimek'], $b['priimek']);
        });

    } catch (\PDOException $e) {
        echo "<p style='color: red;'>Napaka pri bazi: " . $e->getMessage() . "</p>";
    }
}
?>

<div style="margin-bottom: 20px; padding: 10px; border: 1px solid #ccc; background-color: #f7f7f7;">
    <?php if (!empty($naloga)): ?>
        <h2>Pregled Naloge: <?php echo htmlspecialchars($naloga['naslov']); ?></h2>
        <p style="margin-top: 5px; color: #555;">Objavljeno: <?php echo date('d.m.Y H:i', strtotime($naloga['datum_objave'])); ?> | Rok: <span style="font-weight: bold; color: #dc3545;"><?php echo date('d.m.Y H:i', strtotime($naloga['rok_oddaje'])); ?></span></p>
    <?php else: ?>
        <h2>Objavi Novo Nalogo</h2>
    <?php endif; ?>
    
    <?php if (!empty($seznam_nalog)): ?>
        <label for="naloga-select" style="display: block; margin-top: 15px; font-weight: bold;">Izberi nalogo iz arhiva:</label>
        <select id="naloga-select" style="padding: 8px; width: 100%; margin-top: 5px;">
            <option value="" <?php echo empty($naloga) ? 'selected' : ''; ?>>-- Prikaz zadnje naloge / Obrazec za novo nalogo --</option>
            <?php foreach ($seznam_nalog as $arhivirana_naloga): ?>
                <option 
                    value="<?php echo $arhivirana_naloga['id_naloga']; ?>" 
                    <?php echo (!empty($naloga) && $naloga['id_naloga'] == $arhivirana_naloga['id_naloga']) ? 'selected' : ''; ?>
                >
                    <?php echo htmlspecialchars($arhivirana_naloga['naslov']); ?> (Rok: <?php echo date('d.m.Y H:i', strtotime($arhivirana_naloga['rok_oddaje'])); ?>)
                </option>
            <?php endforeach; ?>
        </select>
    <?php endif; ?>
</div>

<?php if (!empty($naloga)): ?>

    <div class="naloga-detajli" style="margin-bottom: 20px; padding: 15px; border: 1px solid #ddd;">
        <p><strong>Opis:</strong></p>
        <div style="white-space: pre-wrap; margin-bottom: 15px;"><?php echo htmlspecialchars($naloga['opis_naloge'] ?? 'Brez opisa.'); ?></div>
        
        <?php if ($naloga['pot_na_strezniku']): ?>
            <p><strong>Priložena datoteka:</strong> <a href="<?php echo htmlspecialchars($naloga['pot_na_strezniku']); ?>" target="_blank">Prenesi datoteko naloge</a></p>
        <?php endif; ?>
        
        <button 
            data-id-naloga="<?php echo $naloga['id_naloga']; ?>" 
            id="delete-naloga-btn" 
            style="background: #dc3545; color: white; border: none; padding: 10px 15px; cursor: pointer; margin-top: 20px;">
            Izbriši to nalogo in vse oddaje učencev
        </button>
    </div>


    <h3 style="margin-top: 30px;">Prejemniki (Skupaj: <?php echo count($ucenci_za_prikaz); ?>)</h3>
    <ul style="margin: 0; padding-left: 20px;">
        <?php foreach ($ucenci_za_prikaz as $podatki): ?>
            <li><?php echo htmlspecialchars($podatki['ime'] . ' ' . $podatki['priimek']); ?></li>
        <?php endforeach; ?>
    </ul>

<?php else: ?>

    <p style="color: red; font-weight: bold;">Trenutno ni nobene aktivne naloge za ta predmet. Objavite novo spodaj.</p>
    
    <h3 style="margin-top: 30px;">Objavi Novo Nalogo</h3>
    <form id="naloga-form" method="POST" enctype="multipart/form-data">
        
        <input type="hidden" name="id_predmet" value="<?php echo htmlspecialchars($id_predmet); ?>"> 

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

        <button type="submit" style="background: #28a745; color: white; border: none; padding: 10px 15px; cursor: pointer; width: 100%;">Objavi Nalogo</button>
    </form>
    
<?php endif; ?>

<script>
    // Poslušalec za GUMB za brisanje naloge (če je ta del naloga naložen)
    const deleteBtn = document.getElementById('delete-naloga-btn');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', async function() {
            const idNaloga = deleteBtn.dataset.idNaloga;
            
            if (!confirm("Ali ste prepričani, da želite IZBRISATI to nalogo in VSE oddaje učencev zanjo? Ta akcija je NEPOVRATNA!")) {
                return;
            }
            
            deleteBtn.disabled = true;
            deleteBtn.textContent = 'Brisanje...';

            try {
                // Ta klic zahteva, da imate datoteko ajax_naloga_delete.php, ki je v vašem primeru bila ustrezna.
                const response = await fetch('ajax_naloga_delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id_naloga: idNaloga })
                });
                
                const result = await response.json();
                alert(result.message);
                
                if (result.success) {
                    // Ponovno naloži detajle, ki bodo zdaj prazni (ali bo prikazal prejšnjo nalogo)
                    // KLJUČNO: Kliče funkcijo iz učitelj_ucilnica.php, ki naloži vsebino.
                    document.querySelector('.predmet-item.active').click(); 
                } else {
                    deleteBtn.disabled = false;
                    deleteBtn.textContent = 'Izbriši to nalogo';
                }
            } catch (error) {
                alert('Napaka pri komunikaciji s strežnikom med brisanjem.');
                deleteBtn.disabled = false;
                deleteBtn.textContent = 'Izbriši to nalogo';
            }
        });
    }

    // NOVO: Poslušalec za IZBIRO NALOGE IZ SEZNAMA
    const nalogaSelect = document.getElementById('naloga-select');
    if (nalogaSelect) {
        nalogaSelect.addEventListener('change', function() {
            const idNaloga = this.value; // Pridobi ID izbrane naloge
            
            // Poišči aktiven predmet, da veš, katere podatke poslati v AJAX
            const activeItem = document.querySelector('.predmet-item.active');
            if (activeItem) {
                const idPredmet = activeItem.dataset.idPredmet;
                const idUcitelja = activeItem.dataset.idUcitelja;
                const imePredmeta = activeItem.dataset.imePredmeta;
                const imeUcitelja = activeItem.dataset.imeUcitelja;
                
                // Opomba: Predpostavljamo, da imate globalno funkcijo 'loadSubjectContent' ali 'loadSubjectDetails'
                // v vaši datoteki 'ucitelj_ucilnica.php', ki je na voljo.
                // Tukaj kličemo funkcijo in ji POSREDUJEMO id_naloga_specificna
                if (idNaloga) {
                    // Naloži specifično nalogo
                    loadSubjectContent(idPredmet, idUcitelja, imePredmeta, imeUcitelja, idNaloga);
                } else {
                    // Naloži zadnjo nalogo (ali prikaže formo za novo)
                    loadSubjectContent(idPredmet, idUcitelja, imePredmeta, imeUcitelja, null); 
                }
            }
        });
    }

</script>