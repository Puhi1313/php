<?php
session_start();
require_once 'povezava.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['vloga'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Dostop zavrnjen. Niste administrator.']);
    exit;
}

$id_ucitelj = (int)($_GET['id_ucitelj'] ?? 0);

if (!$id_ucitelj) {
    echo json_encode(['success' => false, 'message' => 'Manjka ID učitelja.']);
    exit;
}

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Pridobi vse predmete
    $sql_all = "SELECT id_predmet, ime_predmeta FROM predmet ORDER BY ime_predmeta ASC";
    $stmt_all = $pdo->query($sql_all);
    $all_subjects = $stmt_all->fetchAll(PDO::FETCH_ASSOC);

    // 2. Pridobi predmete, ki so dodeljeni temu učitelju
    $sql_assigned = "SELECT id_predmet FROM ucitelj_predmet WHERE id_ucitelj = ?";
    $stmt_assigned = $pdo->prepare($sql_assigned);
    $stmt_assigned->execute([$id_ucitelj]);
    // Samo ID-ji v enodimenzionalnem arrayu
    $assigned_subjects = $stmt_assigned->fetchAll(PDO::FETCH_COLUMN, 0); 

    echo json_encode(['success' => true, 'all_subjects' => $all_subjects, 'assigned_subjects' => $assigned_subjects]);

} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Napaka pri bazi podatkov.']);
}