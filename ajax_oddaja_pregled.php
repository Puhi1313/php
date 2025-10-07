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
    $sql = "
        SELECT o.*, ucenec.ime AS ucenec_ime, ucenec.priimek AS ucenec_priimek, 
               naloga.naslov AS naloga_naslov, naloga.rok_oddaje
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
} catch (\PDOException $e) {
    die("Napaka pri bazi: " . $e->getMessage());
}

?>
<div style="padding: 15px; background: #fff; border: 1px solid #007bff; border-radius: 5px;">
    <h4>Oddaja učenca: **<?php echo htmlspecialchars($oddaja['ucenec_ime'] . ' ' . $oddaja['ucenec_priimek']); ?>**</h4>
    <p>Naloga: <?php echo htmlspecialchars($oddaja['naloga_naslov']); ?></p>
    <p>Rok oddaje: <?php echo date('d.m.Y H:i', strtotime($oddaja['rok_oddaje'])); ?></p>
    <p>Oddano: **<?php echo date('d.m.Y H:i', strtotime($oddaja['datum_oddaje'])); ?>**</p>
    
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