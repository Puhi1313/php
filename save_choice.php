<?php
session_start();
require_once 'povezava.php';

// Nastavi glavo za JSON odziv
header('Content-Type: application/json');

// Preverimo, da je zahteva POST in da vsebuje podatke
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty(file_get_contents("php://input"))) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Neveljavna zahteva."]);
    exit;
}

// 1. Preveri prijavo in vlogo
if (!isset($_SESSION['user_id']) || $_SESSION['vloga'] !== 'ucenec') {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Niste prijavljeni kot učenec."]);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents("php://input"), true);

$choices = $data['choices'] ?? [];

// Začnemo transakcijo, da zagotovimo, da se vse ali nič ne shrani
$pdo->beginTransaction();

try {
    $inserted_count = 0;
    
    // 2. Vstavi ali posodobi izbrane predmete v tabelo ucenec_predmet (id_predmet in id_ucitelj)
    // Reši podvojene vnose z upsert (če že obstaja, posodobi učitelja)
    $sql_insert = "INSERT INTO ucenec_predmet (id_ucenec, id_predmet, id_ucitelj) VALUES (?, ?, ?) 
                   ON DUPLICATE KEY UPDATE id_ucitelj = VALUES(id_ucitelj)";
    $stmt_insert = $pdo->prepare($sql_insert);

    foreach ($choices as $id_predmet => $id_ucitelj) {
        $stmt_insert->execute([$user_id, $id_predmet, $id_ucitelj]);
        $inserted_count++;
    }

    // 3. Posodobi status prvega vpisa na 0 (da ga ne vrže več na predmetiPage.php)
    $sql_update_vpis = "UPDATE uporabnik SET prvi_vpis = 0 WHERE id_uporabnik = ?";
    $stmt_update_vpis = $pdo->prepare($sql_update_vpis);
    $stmt_update_vpis->execute([$user_id]);
    
    // Posodobi sejo
    $_SESSION['prvi_vpis'] = 0;

    $pdo->commit();

    echo json_encode(["success" => true, "message" => "Uspešno shranjenih $inserted_count predmetov.", "redirect" => "ucilnicaPage.php"]);

} catch (\PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Napaka pri shranjevanju: " . $e->getMessage()]);
}

?>