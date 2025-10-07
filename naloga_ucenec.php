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
    $sql_oddaja = "SELECT * FROM oddaja WHERE id_naloga = ? AND id_ucenec = ?";
    $stmt_oddaja = $pdo->prepare($sql_oddaja);
    $stmt_oddaja->execute([$naloga['id_naloga'], $user_id]);
    $oddaja = $stmt_oddaja->fetch();
} catch (\PDOException $e) {
    $oddaja = false;
    echo "<p style='color: red;'>Napaka pri preverjanju oddaje: " . $e->getMessage() . "</p>";
}

$rok_oddaje = new DateTime($naloga['rok_oddaje']);
$danes = new DateTime();
$je_prepozno = $danes > $rok_oddaje;
$prikaz_roka = $rok_oddaje->format('d.m.Y H:i');

$status_barva = 'orange';
$status_tekst = 'Čaka na oddajo';

if ($oddaja) {
    $status_barva = $oddaja['ocena'] ? 'green' : 'blue';
    $status_tekst = $oddaja['ocena'] ? 'OCENJENO (' . $oddaja['ocena'] . ')' : 'Oddano';
} elseif ($je_prepozno) {
    $status_barva = 'red';
    $status_tekst = 'ROK POTEKEL';
}

$disabled_form = $je_prepozno && !$oddaja ? 'disabled' : ''; // Onemogočimo oddajo, če je rok potekel in še ni oddano

?>

<style>
    .status-badge {
        padding: 5px 10px;
        border-radius: 5px;
        font-weight: bold;
        color: white;
        display: inline-block;
        margin-left: 10px;
        background-color: <?php echo $status_barva; ?>;
    }
</style>

<div class="naloga-detajli">
    <h3><?php echo htmlspecialchars($naloga['naslov']); ?></h3>
    <p>Objavljeno: <?php echo date('d.m.Y', strtotime($naloga['datum_objave'])); ?></p>
    <p class="rok">Rok za oddajo: **<?php echo $prikaz_roka; ?>** <span class="status-badge"><?php echo htmlspecialchars($status_tekst); ?></span>
    </p>
    
    <?php if ($oddaja && $oddaja['ocena']): ?>
        <p style="margin-top: 15px; border: 1px solid green; padding: 10px; background: #e6ffe6;">
            **OCENA UČITELJA: <?php echo htmlspecialchars($oddaja['ocena']); ?>**<br>
            *Komentar:* <?php echo nl2br(htmlspecialchars($oddaja['komentar_ucitelj'] ?? 'Ni komentarja.')); ?>
        </p>
    <?php endif; ?>
    
    <hr>
    <h4>Opis naloge:</h4>
    <p><?php echo nl2br(htmlspecialchars($naloga['opis_naloge'])); ?></p>
    
    <?php if ($naloga['pot_na_strezniku']): ?>
        <p>Priložena datoteka učitelja: <a href="<?php echo htmlspecialchars($naloga['pot_na_strezniku']); ?>" target="_blank">Prenesi datoteko</a></p>
    <?php endif; ?>
    
    <hr>
    <h4>Vaša oddaja:</h4>
    
    <?php if ($oddaja): ?>
        <p style="color: green; font-weight: bold;">Naloga je že bila oddana (<?php echo date('d.m.Y H:i', strtotime($oddaja['datum_oddaje'])); ?>).</p>
        <p>Vaše besedilo: <?php echo nl2br(htmlspecialchars($oddaja['besedilo_oddaje'] ?? '')); ?></p>
        <?php if ($oddaja['pot_na_strezniku']): ?>
            <p>Vaša datoteka: <a href="<?php echo htmlspecialchars($oddaja['pot_na_strezniku']); ?>" target="_blank">Prenesi oddano datoteko</a></p>
        <?php endif; ?>
        <p>S to formo lahko oddate **posodobljeno verzijo**.</p>
    <?php endif; ?>
    
    <form id="oddaja-form" action="ajax_oddaja.php" method="POST" enctype="multipart/form-data" class="oddaja-form">
        <input type="hidden" name="id_naloga" value="<?php echo $naloga['id_naloga']; ?>">
        
        <div>
            <label for="besedilo_oddaje">Besedilo/Komentar (neobvezno):</label>
            <textarea id="besedilo_oddaje" name="besedilo_oddaje" rows="5" style="width: 100%;" <?php echo $disabled_form; ?>><?php echo htmlspecialchars($oddaja['besedilo_oddaje'] ?? ''); ?></textarea>
        </div>
        
        <div style="margin-top: 15px;">
            <label for="datoteka_ucenec">Oddaja datoteke (.pdf, .doc, ipd.):</label>
            <input type="file" id="datoteka_ucenec" name="datoteka_ucenec" accept=".pdf,.doc,.docx,.zip" <?php echo $disabled_form; ?>>
        </div>
        
        <button type="submit" style="margin-top: 20px;" <?php echo $disabled_form; ?>>Oddaj nalogo</button>
        
        <?php if ($je_prepozno && !$oddaja): ?>
            <p style="color: red; margin-top: 10px;">**ROK POTEKEL. Oddaja ni več mogoča.**</p>
        <?php endif; ?>
        
    </form>
    
</div>