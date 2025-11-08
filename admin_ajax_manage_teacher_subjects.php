<?php
session_start();
require_once 'povezava.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['vloga'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Dostop zavrnjen. Niste administrator.']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$id_ucitelj = (int)($data['id_ucitelj'] ?? 0);
$id_predmet = (int)($data['id_predmet'] ?? 0);
$action = $data['action'] ?? ''; // 'add' ali 'delete'

if (!$id_ucitelj || !$id_predmet || !in_array($action, ['add', 'delete'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Manjkajo ali so neveljavni podatki.']);
    exit;
}

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($action === 'add') {
        // Dodaj predmet učitelju (INSERT IGNORE prepreči podvojene zapise)
        $sql = "INSERT IGNORE INTO ucitelj_predmet (id_ucitelj, id_predmet) VALUES (?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_ucitelj, $id_predmet]);
        $message = "Predmet uspešno dodeljen.";
        
    } elseif ($action === 'delete') {
        // Odstrani predmet učitelju
        $sql = "DELETE FROM ucitelj_predmet WHERE id_ucitelj = ? AND id_predmet = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_ucitelj, $id_predmet]);
        $message = "Predmet uspešno odstranjen.";
    }

    echo json_encode(['success' => true, 'message' => $message]);

} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Napaka pri bazi podatkov.']);
}