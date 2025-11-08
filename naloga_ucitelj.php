<?php 
// Ta datoteka je vključena v ajax_naloga.php, zato ima dostop do $pdo, $naloga, $id_predmet, $id_ucitelja in $seznam_nalog
// $seznam_nalog je array vseh nalog, $naloga je trenutno izbrana (ali zadnja) naloga

// Pridobivanje podatkov o oddajah, če obstaja aktivna naloga
$ucenci_za_prikaz = []; // Structure: [id_ucenec => ['ucenec' => [...], 'oddaje' => [...]]]
if (!empty($naloga)) {
    try {
        // Pridobitev VSEH oddaj za to nalogo (not just latest), ordered by date DESC
        $sql_oddaje = "
            SELECT o.*, u.ime, u.priimek
            FROM oddaja o
            JOIN uporabnik u ON o.id_ucenec = u.id_uporabnik
            WHERE o.id_naloga = ?
            ORDER BY o.id_ucenec ASC, o.datum_oddaje DESC
        ";
        $stmt_oddaje = $pdo->prepare($sql_oddaje);
        $stmt_oddaje->execute([$naloga['id_naloga']]);
        $vse_oddaje = $stmt_oddaje->fetchAll();

        // Pridobitev vseh učencev za ta predmet
        $sql_vsi_ucenci = "
            SELECT up.id_uporabnik, up.ime, up.priimek
            FROM ucenec_predmet ucp
            JOIN uporabnik up ON ucp.id_ucenec = up.id_uporabnik
            WHERE ucp.id_predmet = ? AND ucp.id_ucitelj = ?
            ORDER BY up.priimek ASC
        ";
        $stmt_vsi_ucenci = $pdo->prepare($sql_vsi_ucenci);
        $stmt_vsi_ucenci->execute([$id_predmet, $id_ucitelja]);
        $vsi_ucenci = $stmt_vsi_ucenci->fetchAll();

        // Organize submissions by student
        $oddaje_po_ucencih = [];
        foreach ($vse_oddaje as $oddaja) {
            $id_ucenec = $oddaja['id_ucenec'];
            if (!isset($oddaje_po_ucencih[$id_ucenec])) {
                $oddaje_po_ucencih[$id_ucenec] = [];
            }
            $oddaje_po_ucencih[$id_ucenec][] = $oddaja;
        }

        // Create structure for display: each student with all their submissions
        foreach ($vsi_ucenci as $ucenec) {
            $id_ucenec = $ucenec['id_uporabnik'];
            $oddaje_ucenca = $oddaje_po_ucencih[$id_ucenec] ?? [];
            
            // Find active submission (latest non-'Zamenjana' status, or latest if all are 'Zamenjana')
            $aktivna_oddaja = null;
            if (!empty($oddaje_ucenca)) {
                foreach ($oddaje_ucenca as $oddaja) {
                    if ($oddaja['status'] !== 'Zamenjana') {
                        $aktivna_oddaja = $oddaja;
                        break;
                    }
                }
                // If all are 'Zamenjana', use the latest one
                if (!$aktivna_oddaja) {
                    $aktivna_oddaja = $oddaje_ucenca[0];
                }
            }
            
            $ucenci_za_prikaz[] = [
                'id_uporabnik' => $id_ucenec,
                'ime' => $ucenec['ime'],
                'priimek' => $ucenec['priimek'],
                'oddaje' => $oddaje_ucenca, // All submissions
                'aktivna_oddaja' => $aktivna_oddaja, // Current active submission
                'ima_oddaje' => !empty($oddaje_ucenca)
            ];
        }

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


    <h3 style="margin-top: 30px;">Oddaje Učencev (Skupaj: <?php echo count($ucenci_za_prikaz); ?>)</h3>
    
    <div style="margin-top: 20px;">
        <?php foreach ($ucenci_za_prikaz as $ucenec_data): ?>
            <div style="margin-bottom: 25px; padding: 15px; border: 2px solid #ddd; border-radius: 8px; background: #f9f9f9;">
                <h4 style="margin: 0 0 10px 0; color: #3f51b5;">
                    <?php echo htmlspecialchars($ucenec_data['ime'] . ' ' . $ucenec_data['priimek']); ?>
                </h4>
                
                <?php if ($ucenec_data['ima_oddaje']): ?>
                    <?php 
                    $oddaje = $ucenec_data['oddaje'];
                    $aktivna = $ucenec_data['aktivna_oddaja'];
                    ?>
                    
                    <div style="margin-top: 10px;">
                        <strong style="color: #28a745;">Aktivna oddaja:</strong>
                        <?php if ($aktivna): ?>
                            <div style="margin-left: 20px; margin-top: 5px; padding: 10px; background: #e8f5e9; border-left: 4px solid #28a745;">
                                <p style="margin: 5px 0;"><strong>Status:</strong> <?php echo htmlspecialchars($aktivna['status']); ?></p>
                                <p style="margin: 5px 0;"><strong>Datum oddaje:</strong> <?php echo date('d.m.Y H:i', strtotime($aktivna['datum_oddaje'])); ?></p>
                                <?php if ($aktivna['ocena']): ?>
                                    <p style="margin: 5px 0;"><strong>Ocena:</strong> <?php echo htmlspecialchars($aktivna['ocena']); ?></p>
                                <?php endif; ?>
                                <?php if ($aktivna['podaljsan_rok']): ?>
                                    <p style="margin: 5px 0;"><strong>Podaljšan rok:</strong> <?php echo date('d.m.Y H:i', strtotime($aktivna['podaljsan_rok'])); ?></p>
                                <?php endif; ?>
                                <button 
                                    data-oddaja-id="<?php echo $aktivna['id_oddaja']; ?>" 
                                    data-ucenec-ime="<?php echo htmlspecialchars($ucenec_data['ime'] . ' ' . $ucenec_data['priimek']); ?>" 
                                    class="pregled-oddaje-btn"
                                    style="background: #1c4587; color: white; border: none; padding: 5px 10px; cursor: pointer; margin-top: 10px;">
                                    Pregled/Oceni
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (count($oddaje) > 1): ?>
                        <div style="margin-top: 15px;">
                            <strong style="color: #666;">Zgodovina oddaj (<?php echo count($oddaje); ?>):</strong>
                            <div style="margin-left: 20px; margin-top: 5px;">
                                <?php foreach ($oddaje as $index => $oddaja): ?>
                                    <div style="margin-bottom: 10px; padding: 8px; background: <?php echo $oddaja['id_oddaja'] == $aktivna['id_oddaja'] ? '#e8f5e9' : '#fff'; ?>; border-left: 3px solid <?php echo $oddaja['status'] === 'Zamenjana' ? '#999' : ($oddaja['id_oddaja'] == $aktivna['id_oddaja'] ? '#28a745' : '#ccc'); ?>;">
                                        <p style="margin: 3px 0; font-size: 0.9em;">
                                            <strong>#<?php echo count($oddaje) - $index; ?>:</strong> 
                                            <?php echo date('d.m.Y H:i', strtotime($oddaja['datum_oddaje'])); ?> | 
                                            Status: <strong><?php echo htmlspecialchars($oddaja['status']); ?></strong>
                                            <?php if ($oddaja['ocena']): ?>
                                                | Ocena: <strong><?php echo htmlspecialchars($oddaja['ocena']); ?></strong>
                                            <?php endif; ?>
                                            <?php if ($oddaja['id_oddaja'] == $aktivna['id_oddaja']): ?>
                                                <span style="color: #28a745; font-weight: bold;">(AKTIVNA)</span>
                                            <?php endif; ?>
                                        </p>
                                        <button 
                                            data-oddaja-id="<?php echo $oddaja['id_oddaja']; ?>" 
                                            data-ucenec-ime="<?php echo htmlspecialchars($ucenec_data['ime'] . ' ' . $ucenec_data['priimek']); ?>" 
                                            class="pregled-oddaje-btn"
                                            style="background: #666; color: white; border: none; padding: 3px 8px; cursor: pointer; font-size: 0.85em;">
                                            Pregled
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p style="color: #999; font-style: italic;">Ni oddano</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

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