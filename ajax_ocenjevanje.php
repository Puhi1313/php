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

if (empty($id_oddaja) || empty($ocena)) {
    echo json_encode(["success" => false, "message" => "Manjkata ID oddaje ali ocena."]);
    exit;
}

try {
    $sql_update = "UPDATE oddaja SET ocena = ?, komentar_ucitelj = ?, status = 'Ocenjeno' WHERE id_oddaja = ?";
    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->execute([$ocena, $komentar_ucitelj, $id_oddaja]);
    
    echo json_encode(["success" => true, "message" => "Ocena uspešno shranjena!"]);

} catch (\PDOException $e) {
    echo json_encode(["success" => false, "message" => "Napaka baze: " . $e->getMessage()]);
}
?>