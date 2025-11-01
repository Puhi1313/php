<?php 
// Ta datoteka je vključena v ajax_naloga.php, zato ima dostop do $naloga, $pdo in $user_id

if (empty($naloga)): ?>
    <div class="naloga-detajli">
        <h3>Trenutno ni aktivne naloge za ta predmet.</h3>
        <p>Preverite ponovno kasneje.</p>
    </div>
<?php 
    return;
endif; 

// Pridobitev oddaje in preverjanje rokov
try {
    // Vedno pridobimo zadnjo oddajo za to SPECIFIČNO NALOGO
    $sql_oddaja = "SELECT * FROM oddaja WHERE id_naloga = ? AND id_ucenec = ? ORDER BY id_oddaja DESC LIMIT 1";
    $stmt_oddaja = $pdo->prepare($sql_oddaja);
    $stmt_oddaja->execute([$naloga['id_naloga'], $user_id]);
    $oddaja = $stmt_oddaja->fetch();
} catch (\PDOException $e) {
    $oddaja = false;
    echo "<p style='color: red;'>Napaka pri preverjanju oddaje: " . $e->getMessage() . "</p>";
}

$rok_oddaje_original = new DateTime($naloga['rok_oddaje']);
$rok_oddaje_dejanski = clone $rok_oddaje_original;

$danes = new DateTime();
$je_prepozno = $danes > $rok_oddaje_dejanski; // Preverjanje glede na prvotni rok

$status_barva = 'orange';
$status_tekst = 'Čaka na oddajo';
$disabled_form = ''; // Privzeto je omogočeno

// Preverimo, ali je naloga že oddana in ocenjena
if ($oddaja) {
    if ($oddaja['ocena']) {
        $ocena_up = strtoupper(trim($oddaja['ocena']));
        
        // POGOJI ZA ZAKLEP FORME (Zadostna ocena)
        if ($ocena_up !== '1' && $ocena_up !== 'ND') {
            $status_barva = 'green';
            $status_tekst = 'Ocenjeno - ZAD. (' . htmlspecialchars($oddaja['ocena']) . ')';
            $disabled_form = 'disabled'; // ZAKLENEMO: Uspešno končano
        } 
        // Če je ocena Nezgodstna ('1' ali 'ND')
        else {
            $status_barva = 'red';
            $status_tekst = 'Ocenjeno - NEZAD. (' . htmlspecialchars($oddaja['ocena']) . ')';
            
            // Logika za PODALJŠANJE ROKA
            if ($danes > $rok_oddaje_original) {
                // Če je rok potekel in je ocena '1' ali 'ND', podaljšamo rok za 7 dni (če je novi rok kasnejši od ocenjevanja)
                $datum_ocenjevanja = new DateTime($oddaja['datum_ocenjevanja'] ?? $oddaja['datum_oddaje']); // Če imamo stolpec 'datum_ocenjevanja', sicer uporabimo datum_oddaje
                $nov_rok = clone $datum_ocenjevanja;
                $nov_rok->modify('+7 days');
                
                // Uporabimo večji datum - prvotni rok ali rok podaljšan za 7 dni
                if ($nov_rok > $rok_oddaje_dejanski) {
                    $rok_oddaje_dejanski = $nov_rok;
                }
            }
            // FORME NE ZAKLENEMO: Dovolimo dopolnitev, saj je ocena nezadostna.
            $je_prepozno = $danes > $rok_oddaje_dejanski; // Preverimo ponovno glede na podaljšani rok
        }
    } else {
        $status_barva = 'blue';
        $status_tekst = 'Oddano (Čaka na oceno)';
        // Če je oddano, a še ni ocenjeno, jo lahko posodobi (forma ni disabled)
    }
} else {
    // Naloga še ni oddana (prva oddaja)
    if ($je_prepozno) {
        $status_barva = 'red';
        $status_tekst = 'ROK POTEKEL';
        $disabled_form = 'disabled'; // ZAKLENEMO: Prva oddaja po roku ni dovoljena
    }
}

// Končni prikaz roka (lahko je podaljšan)
$prikaz_roka = $rok_oddaje_dejanski->format('d.m.Y H:i');

?>

<div class="naloga-detajli">
    <h3 style="margin-bottom: 5px;"><?php echo htmlspecialchars($naloga['naslov']); ?></h3>
    <p style="margin-top: 0; font-size: 14px; color: #555;">Objavljeno: <?php echo date('d.m.Y H:i', strtotime($naloga['datum_objave'])); ?></p>
    
    <div style="margin-bottom: 15px; padding: 10px; border: 1px solid #ccc; border-left: 5px solid <?php echo $status_barva; ?>; background: #fff;">
        <p style="margin: 0;">
            Rok oddaje: **<?php echo $prikaz_roka; ?>** <?php if ($rok_oddaje_dejanski != $rok_oddaje_original): ?>
                (Podaljšano)
            <?php endif; ?>
            | Status: <span style="color: <?php echo $status_barva; ?>; font-weight: bold;"><?php echo $status_tekst; ?></span>
        </p>
    </div>
    
    <h5>Opis naloge:</h5>
    <p><?php echo nl2br(htmlspecialchars($naloga['opis_naloge'] ?? '')); ?></p>
    
    <?php if ($naloga['pot_na_strezniku']): ?>
        <p>Priložena datoteka učitelja: <a href="<?php echo htmlspecialchars($naloga['pot_na_strezniku']); ?>" target="_blank">Prenesi datoteko</a></p>
    <?php endif; ?>
    
    <hr>
    
    <?php if ($oddaja): ?>
        <h4>Vaša oddaja:</h4>
        <p style="color: green; font-weight: bold;">Zadnja oddaja: (<?php echo date('d.m.Y H:i', strtotime($oddaja['datum_oddaje'])); ?>).</p>
        
        <div style="border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">
            <p>Besedilo:</p>
            <p><?php echo nl2br(htmlspecialchars($oddaja['besedilo_oddaje'] ?? 'Ni besedila.')); ?></p>
        </div>
        
        <?php if ($oddaja['pot_na_strezniku']): ?>
            <p style="margin-top: 10px;">Vaša datoteka: <a href="<?php echo htmlspecialchars($oddaja['pot_na_strezniku']); ?>" target="_blank">Prenesi oddano datoteko</a></p>
        <?php endif; ?>
        
        <?php if ($oddaja['ocena']): ?>
            <p style="margin-top: 10px; font-weight: bold;">Ocena učitelja: <?php echo htmlspecialchars($oddaja['ocena']); ?></p>
            <?php if ($oddaja['komentar_ucitelj']): ?>
                <p>Komentar: <?php echo nl2br(htmlspecialchars($oddaja['komentar_ucitelj'])); ?></p>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if (!$disabled_form): ?>
            <p>S spodnjo formo lahko oddate **posodobljeno verzijo** (za dopolnitev).</p>
        <?php endif; ?>

        <hr>
    <?php else: ?>
        <h4>Oddaja naloge:</h4>
    <?php endif; ?>
    
    <?php if ($disabled_form): ?>
         <p style="color: red; font-weight: bold;">Oddaja ni mogoča, ker je naloga že ocenjena z zadostno oceno ali je potekel rok za prvo oddajo!</p>
    <?php endif; ?>
    
    <?php if ($je_prepozno && !$disabled_form && $oddaja && ($ocena_up === '1' || $ocena_up === 'ND')): ?>
        <p style="color: red; font-weight: bold;">Opomba: Rok za dopolnitev je <?php echo $prikaz_roka; ?>!</p>
    <?php endif; ?>


    <form id="oddaja-form" action="ajax_oddaja.php" method="POST" enctype="multipart/form-data" class="oddaja-form">
        
        <input type="hidden" name="id_naloga" value="<?php echo htmlspecialchars($naloga['id_naloga']); ?>">
        
        <div style="margin-bottom: 10px;">
            <label for="besedilo_oddaje">Besedilo oddaje (Opcija):</label>
            <textarea id="besedilo_oddaje" name="besedilo_oddaje" rows="6" style="width: 100%; padding: 8px;" <?php echo $disabled_form; ?>><?php echo htmlspecialchars($oddaja['besedilo_oddaje'] ?? ''); ?></textarea>
        </div>
        
        <div style="margin-bottom: 10px;">
            <label for="datoteka">Priloži datoteko (Opcija):</label>
            <input type="file" id="datoteka" name="datoteka" <?php echo $disabled_form; ?>>
            <?php if ($oddaja && $oddaja['pot_na_strezniku'] && !$disabled_form): ?>
                <p style="font-size: 12px; color: #555;">**Opomba:** Če priložite novo datoteko, bo stara datoteka zamenjana.</p>
            <?php endif; ?>
        </div>
        
        <button type="submit" style="background: <?php echo $disabled_form ? '#aaa' : 'green'; ?>; color: white; border: none; padding: 10px 15px; cursor: pointer;" <?php echo $disabled_form; ?>>
            <?php echo $oddaja ? 'Posodobi Oddajo' : 'Oddaj Nalogo'; ?>
        </button>
    </form>
</div>