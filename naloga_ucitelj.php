<?php 
// Ta datoteka je vključena v ajax_naloga.php, zato ima dostop do $naloga, $id_predmet in $id_ucitelja

$prikaz_rocnega_vnosa = true; // Nastavitev, ali se prikaže obrazec za novo nalogo

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
        $vsi_ucenci = $stmt_vsi_ucenci->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_UNIQUE);
        
        // Združite oddaje in učence za celoten pregled
        $pregled = [];
        $oddani_idji = [];

        foreach ($oddaje as $o) {
            $pregled[$o['id_ucenec']] = $o;
            $oddani_idji[] = $o['id_ucenec'];
        }

        // Dodaj učence, ki še niso oddali
        foreach ($vsi_ucenci as $id => $ucenec) {
            if (!in_array($id, $oddani_idji)) {
                 // Preveri, ali je rok potekel
                $rok_oddaje = new DateTime($naloga['rok_oddaje']);
                $danes = new DateTime();
                $status = $danes > $rok_oddaje ? 'Ni oddano (ROK POTEKEL)' : 'Ni oddano';

                $pregled[$id] = [
                    'id_ucenec' => $id,
                    'ime' => $ucenec['ime'],
                    'priimek' => $ucenec['priimek'],
                    'status' => $status,
                    'datum_oddaje' => null,
                    'ocena' => null
                ];
            } else {
                 // Če je oddano, posodobi status za lepši prikaz
                 $pregled[$id]['status'] = $pregled[$id]['ocena'] ? 'Ocenjeno' : 'Oddano';
            }
        }
        
        // Poredite pregled po priimku
        uasort($pregled, fn($a, $b) => $a['priimek'] <=> $b['priimek']);

    } catch (\PDOException $e) {
        echo "<p style='color: red;'>Napaka pri pridobivanju oddaj: " . $e->getMessage() . "</p>";
        $oddaje = [];
    }
}
?>

<div style="margin-bottom: 30px; padding: 15px; background: #fff; border-radius: 5px; border: 1px solid #ddd;">
    <h4 style="color: #007bff;">Objavi novo nalogo</h4>
    
    <form id="naloga-form" action="ajax_naloga.php?action=create" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="id_predmet" value="<?php echo htmlspecialchars($id_predmet); ?>">
        
        <div><label for="naslov">Naslov naloge:</label>
            <input type="text" id="naslov" name="naslov" required style="width: 100%; padding: 8px;"></div>
        
        <div style="margin-top: 10px;"><label for="opis_naloge">Opis/Navodila:</label>
            <textarea id="opis_naloge" name="opis_naloge" rows="7" style="width: 100%;"></textarea></div>
        
        <div style="margin-top: 10px;"><label for="rok_oddaje">Rok za oddajo (Datum in Čas):</label>
            <input type="datetime-local" id="rok_oddaje" name="rok_oddaje" required style="padding: 8px;"></div>
        
        <div style="margin-top: 10px;"><label for="datoteka_ucitelj">Priloži datoteko:</label>
            <input type="file" id="datoteka_ucitelj" name="datoteka_ucitelj"></div>
        
        <button type="submit" style="margin-top: 20px; padding: 10px 15px; background: #28a745; color: white; border: none; cursor: pointer;">Objavi Nalogo</button>
    </form>
</div>

<?php if (!empty($naloga)): ?>
<div class="naloga-detajli" style="padding: 15px; background: #fff; border-radius: 5px; border: 1px solid #28a745; margin-top: 20px;">
    <h3>Aktivna naloga: <?php echo htmlspecialchars($naloga['naslov']); ?></h3>
    <p>Rok: <span style="color: red; font-weight: bold;"><?php echo date('d.m.Y H:i', strtotime($naloga['rok_oddaje'])); ?></span></p>
    
    <h4 style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 10px;">Pregled oddaj učencev (Skupaj: <?php echo count($pregled); ?>)</h4>
    
    <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
        <thead>
            <tr style="background-color: #f2f2f2;">
                <th style="padding: 8px; border: 1px solid #ddd; text-align: left;">Učenec</th>
                <th style="padding: 8px; border: 1px solid #ddd;">Status</th>
                <th style="padding: 8px; border: 1px solid #ddd;">Oddano</th>
                <th style="padding: 8px; border: 1px solid #ddd;">Ocena</th>
                <th style="padding: 8px; border: 1px solid #ddd;">Akcija</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pregled as $id_ucenec => $podatki): 
                $status_oddaje = $podatki['status'] ?? 'Ni oddano';
                $barva = match($status_oddaje) {
                    'Oddano' => 'blue',
                    'Ocenjeno' => 'green',
                    'Ni oddano (ROK POTEKEL)' => 'red',
                    default => 'orange'
                };
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
<?php endif; ?>

<script>
    // Poslušalec, ki ga pokličemo iz ucilnicaPage.php, da obdela klike za pregled
    document.querySelectorAll('.pregled-oddaje-btn').forEach(button => {
        button.addEventListener('click', async (e) => {
            const oddajaId = e.currentTarget.dataset.oddajaId;
            const ucenecIme = e.currentTarget.dataset.ucenecIme;
            
            document.getElementById('content-header').textContent = `Pregled oddaje - ${ucenecIme}`;
            document.getElementById('content-details').innerHTML = '<p>Nalaganje oddaje...</p>';

            // AJAX klic za pridobitev vsebine oddaje in obrazca za ocenjevanje
            try {
                const response = await fetch('ajax_oddaja_pregled.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id_oddaja: oddajaId
                    })
                });
                const html = await response.text();
                document.getElementById('content-details').innerHTML = html;
                setupGradingFormListener(); // Pripravimo poslušalca za ocenjevanje
            } catch (error) {
                document.getElementById('content-details').innerHTML = '<p style="color: red;">Napaka pri nalaganju detajlov oddaje.</p>';
                console.error('Fetch error:', error);
            }
        });
    });
    
    // Funkcija za nastavitev poslušalca obrazca za ocenjevanje
    function setupGradingFormListener() {
        const gradingForm = document.getElementById('grading-form');
        if (gradingForm) {
            gradingForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const gradeBtn = this.querySelector('button[type="submit"]');
                gradeBtn.disabled = true;
                gradeBtn.textContent = 'Shranjevanje ocene...';

                try {
                    const response = await fetch('ajax_ocenjevanje.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    alert(result.message);
                    
                    if (result.success) {
                        // Vrnitev na pregled predmeta za osvežitev statusa
                        document.querySelector('.predmet-item.active').click(); 
                    } else {
                        gradeBtn.textContent = 'Shrani oceno';
                        gradeBtn.disabled = false;
                    }
                } catch (error) {
                    alert('Napaka pri komunikaciji s strežnikom.');
                    gradeBtn.textContent = 'Shrani oceno';
                    gradeBtn.disabled = false;
                }
            });
        }
    }
</script>