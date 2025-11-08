<?php
session_start();
require_once 'povezava.php';

// Preverjanje prijave in vloge učitelja
if (!isset($_SESSION['user_id']) || $_SESSION['vloga'] === 'ucenec') {
    http_response_code(401);
    die("Dostop zavrnjen.");
}

$data = json_decode(file_get_contents("php://input"), true);
$id_oddaja = $data['id_oddaja'] ?? null;

if (empty($id_oddaja)) {
    die("Manjka ID oddaje.");
}

try {
    // Get the specific submission
    $sql = "
        SELECT o.*, ucenec.ime AS ucenec_ime, ucenec.priimek AS ucenec_priimek, 
               naloga.naslov AS naloga_naslov, naloga.rok_oddaje, naloga.id_naloga
        FROM oddaja o
        JOIN uporabnik ucenec ON o.id_ucenec = ucenec.id_uporabnik
        JOIN naloga ON o.id_naloga = naloga.id_naloga
        WHERE o.id_oddaja = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_oddaja]);
    $oddaja = $stmt->fetch();

    if (!$oddaja) {
        die("Oddaja ni najdena.");
    }
    
    // Get all submissions for this task and student (submission history)
    $sql_history = "
        SELECT id_oddaja, datum_oddaje, status, ocena
        FROM oddaja
        WHERE id_naloga = ? AND id_ucenec = ?
        ORDER BY datum_oddaje DESC
    ";
    $stmt_history = $pdo->prepare($sql_history);
    $stmt_history->execute([$oddaja['id_naloga'], $oddaja['id_ucenec']]);
    $submission_history = $stmt_history->fetchAll();
    
    // Check if this is the active submission (not 'Zamenjana' or latest if all are 'Zamenjana')
    $is_active = false;
    if (!empty($submission_history)) {
        // Check if there's a non-'Zamenjana' submission
        $has_active = false;
        foreach ($submission_history as $sub) {
            if ($sub['status'] !== 'Zamenjana') {
                $has_active = true;
                if ($sub['id_oddaja'] == $id_oddaja) {
                    $is_active = true;
                }
                break;
            }
        }
        // If all are 'Zamenjana', the latest one is considered active
        if (!$has_active && $submission_history[0]['id_oddaja'] == $id_oddaja) {
            $is_active = true;
        }
    }
    
} catch (\PDOException $e) {
    die("Napaka pri bazi: " . $e->getMessage());
}

?>
<div style="padding: 15px; background: #fff; border: 1px solid #007bff; border-radius: 5px;">
    <h4>Oddaja učenca: **<?php echo htmlspecialchars($oddaja['ucenec_ime'] . ' ' . $oddaja['ucenec_priimek']); ?>**</h4>
    <p>Naloga: <?php echo htmlspecialchars($oddaja['naloga_naslov']); ?></p>
    <p>Rok oddaje: <?php echo date('d.m.Y H:i', strtotime($oddaja['rok_oddaje'])); ?></p>
    <?php if (!empty($oddaja['podaljsan_rok'])): ?>
        <p><strong style="color: #d4a574;">Podaljšan rok:</strong> <?php echo date('d.m.Y H:i', strtotime($oddaja['podaljsan_rok'])); ?></p>
    <?php endif; ?>
    <p>Oddano: **<?php echo date('d.m.Y H:i', strtotime($oddaja['datum_oddaje'])); ?>**</p>
    <p><strong>Status:</strong> <?php echo htmlspecialchars($oddaja['status']); ?> <?php if ($is_active): ?><span style="color: #28a745; font-weight: bold;">(AKTIVNA)</span><?php endif; ?></p>
    
    <?php if (count($submission_history) > 1): ?>
        <div style="margin-top: 15px; padding: 10px; background: #f0f0f0; border-radius: 5px;">
            <strong>Zgodovina oddaj (<?php echo count($submission_history); ?>):</strong>
            <ul style="margin: 5px 0; padding-left: 20px;">
                <?php foreach ($submission_history as $index => $sub): ?>
                    <li style="margin: 5px 0;">
                        #<?php echo count($submission_history) - $index; ?>: 
                        <?php echo date('d.m.Y H:i', strtotime($sub['datum_oddaje'])); ?> | 
                        Status: <strong><?php echo htmlspecialchars($sub['status']); ?></strong>
                        <?php if ($sub['ocena']): ?>
                            | Ocena: <strong><?php echo htmlspecialchars($sub['ocena']); ?></strong>
                        <?php endif; ?>
                        <?php if ($sub['id_oddaja'] == $id_oddaja): ?>
                            <span style="color: #007bff; font-weight: bold;">(Trenutno prikazano)</span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <hr>
    
    <h5>Besedilo oddaje:</h5>
    <div style="border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">
        <?php echo nl2br(htmlspecialchars($oddaja['besedilo_oddaje'] ?? 'Ni besedila.')); ?>
    </div>
    
    <?php if ($oddaja['pot_na_strezniku']): ?>
        <p style="margin-top: 15px;">Priložena datoteka: <a href="<?php echo htmlspecialchars($oddaja['pot_na_strezniku']); ?>" target="_blank">Prenesi oddano datoteko</a></p>
    <?php endif; ?>
    
    <hr>
    
    <h5 style="color: green;">Ocenjevanje in komentar</h5>
    <form id="grading-form" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="id_oddaja" value="<?php echo $id_oddaja; ?>">
        
        <div>
            <label for="ocena">Ocena (npr. 1-5 ali A-F):</label>
            <input type="text" id="ocena" name="ocena" value="<?php echo htmlspecialchars($oddaja['ocena'] ?? ''); ?>" required style="width: 100%; padding: 8px;">
        </div>
        
        <div style="margin-top: 10px;">
            <label for="komentar_ucitelj">Komentar za učenca:</label>
            <textarea id="komentar_ucitelj" name="komentar_ucitelj" rows="5" style="width: 100%;"><?php echo htmlspecialchars($oddaja['komentar_ucitelj'] ?? ''); ?></textarea>
        </div>
        
        <button type="submit" style="margin-top: 20px; padding: 10px 15px; background: #007bff; color: white; border: none; cursor: pointer;">Shrani oceno</button>
    </form>
</div>