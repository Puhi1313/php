<?php 
// Ta datoteka je vključena v ajax_naloga.php, zato ima dostop do $naloga, $id_predmet, $id_ucitelja in NOVO: $seznam_nalog
// $seznam_nalog je array vseh nalog, $naloga je trenutno izbrana (ali zadnja) naloga

// Odstranjena je spremenljivka $prikaz_rocnega_vnosa, saj forma sedaj ni odvisna od tega.

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
            WHERE ucp.id_predmet = ?
            ORDER BY up.priimek ASC
        ";
        $stmt_vsi_ucenci = $pdo->prepare($sql_vsi_ucenci);
        $stmt_vsi_ucenci->execute([$id_predmet]);
        $vsi_ucenci = $stmt_vsi_ucenci->fetchAll();

        // Spojimo oddane in neodane učence v eno tabelo za preglednost
        $oddani_ucenci_ids = array_column($oddaje, 'id_ucenec');

        foreach ($vsi_ucenci as $ucenec) {
            $podatki = [
                'ime' => $ucenec['ime'],
                'priimek' => $ucenec['priimek'],
                'datum_oddaje' => null,
                'status' => 'Ni oddano',
                'ocena' => null,
                'id_oddaja' => null,
                'pot_na_strezniku' => null
            ];
            
            // Poišči oddajo tega učenca
            // Uporabimo loop, ker array_column ne dela vedno z več istimi idji, čeprav bi moralo biti samo eno
            $key = array_search($ucenec['id_uporabnik'], $oddani_ucenci_ids);
            if ($key !== false) {
                // Če obstaja oddaja, jo dodamo
                $podatki = array_merge($podatki, $oddaje[$key]);
                $podatki['status'] = $oddaje[$key]['status']; 
            }
            
            $ucenci_za_prikaz[] = $podatki;
        }

    } catch (\PDOException $e) {
        $oddaje = [];
        echo "<p style='color: red;'>Napaka pri pridobivanju oddaj: " . $e->getMessage() . "</p>";
    }
}
?>

<hr style="margin-top: 20px;">
<h3 style="color: #0056b3;">Seznam vseh nalog za ta predmet (Arhiv)</h3>
<div style="margin-bottom: 20px;">
    <label for="naloga-select">Izberite nalogo za pregled:</label>
    <select id="naloga-select" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px; width: 100%; max-width: 400px;">
        <?php if (empty($seznam_nalog)): ?>
            <option value="" disabled selected>Ni objavljenih nalog.</option>
        <?php else: ?>
            <?php 
            $trenutna_naloga_id = $naloga['id_naloga'] ?? null;
            foreach ($seznam_nalog as $n): 
                $selected = ($n['id_naloga'] == $trenutna_naloga_id) ? 'selected' : '';
                $naslov_prikaz = htmlspecialchars($n['naslov']) . ' (' . date('d.m.Y', strtotime($n['rok_oddaje'])) . ')';
            ?>
                <option value="<?php echo $n['id_naloga']; ?>" <?php echo $selected; ?>>
                    <?php echo $naslov_prikaz; ?>
                </option>
            <?php endforeach; ?>
        <?php endif; ?>
    </select>
</div>
<hr>


<?php if (!empty($naloga)): ?>
    
    <div class="naloga-detajli-container" style="margin-bottom: 30px;">
        <div style="border: 2px solid #007bff; padding: 15px; margin-bottom: 20px; border-radius: 8px;">
            <h3 style="color: #007bff;">Trenutno Pregledovana Naloga: <?php echo htmlspecialchars($naloga['naslov']); ?></h3>
            <p><strong>Objavljeno:</strong> <?php echo date('d.m.Y H:i', strtotime($naloga['datum_objave'])); ?></p>
            <p style="color: red; font-weight: bold;"><strong>Rok oddaje:</strong> <?php echo date('d.m.Y H:i', strtotime($naloga['rok_oddaje'])); ?></p>
            <p><strong>Opis:</strong></p>
            <div style="border-left: 3px solid #ccc; padding-left: 10px; margin-bottom: 15px;"><?php echo nl2br(htmlspecialchars($naloga['opis_naloge'])); ?></div>
            
            <?php if ($naloga['pot_na_strezniku']): ?>
                <p>Priložena datoteka: <a href="<?php echo htmlspecialchars($naloga['pot_na_strezniku']); ?>" target="_blank">Prenesi datoteko naloge</a></p>
            <?php endif; ?>

            <button data-id-naloga="<?php echo $naloga['id_naloga']; ?>" class="izbrisi-nalogo-btn" style="background: red; color: white; border: none; padding: 8px 15px; cursor: pointer; margin-top: 10px;">
                Izbriši to nalogo (VKLJUČNO z oddajami!)
            </button>
        </div>

        <h4 style="margin-top: 20px;">Oddaje Učencev</h4>
        
        <div class="oddaje-table-wrapper" style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; min-width: 600px;">
                <thead>
                    <tr style="background: #f2f2f2;">
                        <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Učenec</th>
                        <th style="padding: 10px; border: 1px solid #ddd; text-align: center;">Status</th>
                        <th style="padding: 10px; border: 1px solid #ddd; text-align: center;">Datum Oddaje</th>
                        <th style="padding: 10px; border: 1px solid #ddd; text-align: center;">Ocena</th>
                        <th style="padding: 10px; border: 1px solid #ddd; text-align: center;">Akcija</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    foreach ($ucenci_za_prikaz as $podatki): 
                        // Barva statusa (Logika ostaja ista kot prej)
                        $barva = 'gray';
                        $status_oddaje = $podatki['status'];
                        if ($status_oddaje === 'Oddano') {
                            $barva = 'orange';
                        } elseif ($status_oddaje === 'Ocenjeno') {
                            $barva = 'green';
                        } elseif ($status_oddaje === 'Ni oddano') {
                            // Če ni oddano, preverimo rok
                            $rok_oddaje_passed = (new DateTime() > new DateTime($naloga['rok_oddaje']));
                            $barva = $rok_oddaje_passed ? 'darkred' : 'gray';
                            $status_oddaje = $rok_oddaje_passed ? 'ZAMUDA' : 'Ni oddano';
                        }
                    ?>
                    <tr>
                        <td style="padding: 8px; border: 1px solid #ddd;"><?php echo htmlspecialchars($podatki['ime'] . ' ' . $podatki['priimek']); ?></td>
                        <td style="padding: 8px; border: 1px solid #ddd; text-align: center; color: white; background-color: <?php echo $barva; ?>; font-weight: bold;"><?php echo htmlspecialchars($status_oddaje); ?></td>
                        <td style="padding: 8px; border: 1px solid #ddd; text-align: center;"><?php echo $podatki['datum_oddaje'] ? date('d.m.Y H:i', strtotime($podatki['datum_oddaje'])) : '-'; ?></td>
                        <td style="padding: 8px; border: 1px solid #ddd; text-align: center; font-weight: bold;"><?php echo htmlspecialchars($podatki['ocena'] ?? '-'); ?></td>
                        <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">
                            <?php if (isset($podatki['id_oddaja'])): ?>
                                <button 
                                    data-oddaja-id="<?php echo $podatki['id_oddaja']; ?>" 
                                    data-ucenec-ime="<?php echo htmlspecialchars($podatki['ime'] . ' ' . $podatki['priimek']); ?>"
                                    class="pregled-oddaje-btn"
                                    style="background: #007bff; color: white; border: none; padding: 5px 10px; cursor: pointer;">
                                    Pregled/Oceni
                                </button>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
    </div>
    
<?php endif; ?>

<hr style="margin-top: 30px; border-color: green;">
<h3 style="color: green;">Kreiraj NOVO Nalogo</h3>

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
    
    <button type="submit" style="background: green; color: white; border: none; padding: 10px 20px; cursor: pointer; font-size: 16px;">Objavi Nalogo</button>

</form>

<script>
    // Poslušalec za GUMB IZBRIŠI NALOGO (Ostaja enak kot prej)
    const deleteBtn = document.querySelector('.izbrisi-nalogo-btn');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', async function(e) {
            e.preventDefault();
            const idNaloga = this.dataset.idNaloga;
            
            if (!confirm("Ali ste prepričani, da želite IZBRISATI to nalogo in VSE oddaje učencev zanjo? Ta akcija je NEPOVRATNA!")) {
                return;
            }
            
            deleteBtn.disabled = true;
            deleteBtn.textContent = 'Brisanje...';

            try {
                const response = await fetch('ajax_naloga_delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id_naloga: idNaloga })
                });
                
                const result = await response.json();
                alert(result.message);
                
                if (result.success) {
                    // Ponovno naloži detajle: klik na aktiven predmet
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
                
                // Kliči funkcijo, ki naloži vsebino, in ji dodaj ID izbrane naloge
                loadSubjectContent(idPredmet, idUcitelja, imePredmeta, imeUcitelja, idNaloga);
            }
        });
    }

</script>