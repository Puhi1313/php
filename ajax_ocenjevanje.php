<?php
session_start();
require_once 'povezava.php';
header('Content-Type: application/json');

// Preverjanje prijave in vloge učitelja
if (!isset($_SESSION['user_id']) || $_SESSION['vloga'] === 'ucenec') {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Dostop zavrnjen. Niste učitelj."]);
    exit;
}

$id_oddaja = $_POST['id_oddaja'] ?? null;
$ocena = trim($_POST['ocena'] ?? '');
$komentar_ucitelj = trim($_POST['komentar_ucitelj'] ?? '');

$ocena_up = strtoupper($ocena);

// NOVA LOGIKA ZA DOLOČITEV STATUSA
// Če je ocena '1', nastavimo status na 'Dopolnitev' in podaljšamo rok za 7 dni
// Če je ocena 'ND' (Nezadostno/Dopolnitev), nastavimo status na 'Dopolnitev'.
// To omogoči učencu, da oddajo novo dopolnitev.
if ($ocena_up === '1') {
    $status = 'Dopolnitev'; // Dovolimo ponovno oddajo/dopolnitev
    $podaljsan_rok = date('Y-m-d H:i:s', strtotime('+7 days')); // 7 days from now
} elseif ($ocena_up === 'ND') {
    $status = 'Dopolnitev'; // Dovolimo ponovno oddajo/dopolnitev
    $podaljsan_rok = null; // No extension for ND
} else {
    $status = 'Ocenjeno'; // Zaklenemo
    $podaljsan_rok = null;
}

if (empty($id_oddaja) || empty($ocena)) {
    echo json_encode(["success" => false, "message" => "Manjkata ID oddaje ali ocena."]);
    exit;
}

try {
    // Posodobitev v bazi
    // If grade is 1, set podaljsan_rok to 7 days from now
    if ($ocena_up === '1') {
        $sql_update = "UPDATE oddaja SET ocena = ?, komentar_ucitelj = ?, status = ?, podaljsan_rok = DATE_ADD(NOW(), INTERVAL 7 DAY) WHERE id_oddaja = ?";
    } else {
        $sql_update = "UPDATE oddaja SET ocena = ?, komentar_ucitelj = ?, status = ? WHERE id_oddaja = ?";
    }
    $stmt_update = $pdo->prepare($sql_update);
    if ($ocena_up === '1') {
        $stmt_update->execute([$ocena, $komentar_ucitelj, $status, $id_oddaja]);
    } else {
        $stmt_update->execute([$ocena, $komentar_ucitelj, $status, $id_oddaja]);
    }

    echo json_encode(["success" => true, "message" => "Oddaja uspešno ocenjena in status posodobljen na: " . $status . ($ocena_up === '1' ? " (Podaljšan rok: 7 dni)" : "")]);

} catch (\PDOException $e) {
    error_log("Napaka ocenjevanja: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Napaka baze: " . $e->getMessage()]);
}