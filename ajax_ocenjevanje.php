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
// Če je ocena '1' ali 'ND' (Nezadostno/Dopolnitev), nastavimo status nazaj na 'Oddano'.
// To omogoči učencu, da posodobi nalogo (posodobitev oddaje).
if ($ocena_up === '1' || $ocena_up === 'ND') {
    $status = 'Oddano'; // Dovolimo ponovno oddajo/posodobitev
} else {
    $status = 'Ocenjeno'; // Zaklenemo
}

if (empty($id_oddaja) || empty($ocena)) {
    echo json_encode(["success" => false, "message" => "Manjkata ID oddaje ali ocena."]);
    exit;
}

try {
    // Posodobitev v bazi
    $sql_update = "UPDATE oddaja SET ocena = ?, komentar_ucitelj = ?, status = ? WHERE id_oddaja = ?";
    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->execute([$ocena, $komentar_ucitelj, $status, $id_oddaja]);

    echo json_encode(["success" => true, "message" => "Oddaja uspešno ocenjena in status posodobljen na: " . $status]);

} catch (\PDOException $e) {
    error_log("Napaka ocenjevanja: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Napaka baze: " . $e->getMessage()]);
}