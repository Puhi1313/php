<?php
session_start();
require_once 'povezava.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['vloga'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Dostop zavrnjen.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id = (int)($data['id_uporabnik'] ?? 0);
if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Neveljaven ID.']);
    exit;
}

// Prevent deleting yourself
if ($id == $_SESSION['user_id']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ne morete izbrisati samega sebe.']);
    exit;
}

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->beginTransaction();

    // 1. Delete profile picture file if exists
    $stmt = $pdo->prepare("SELECT icona_profila FROM uporabnik WHERE id_uporabnik = ?");
    $stmt->execute([$id]);
    $icona_profila = $stmt->fetchColumn();
    
    if (!empty($icona_profila)) {
        $oldPath = __DIR__ . DIRECTORY_SEPARATOR . $icona_profila;
        $safeDir = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'slike');
        if (file_exists($oldPath)) {
            $realOldPath = realpath($oldPath);
            if ($realOldPath && ($safeDir === false || strpos($realOldPath, $safeDir) === 0)) {
                @unlink($oldPath);
            }
        }
    }

    // 2. Delete from related tables in cascade order (based on foreign keys)
    
    // Delete from oddaja (student submissions)
    $stmt = $pdo->prepare("DELETE FROM oddaja WHERE id_ucenec = ?");
    $stmt->execute([$id]);
    
    // Delete from naloga (teacher assignments)
    $stmt = $pdo->prepare("DELETE FROM naloga WHERE id_ucitelj = ?");
    $stmt->execute([$id]);
    
    // Delete from gradivo (teacher materials)
    $stmt = $pdo->prepare("DELETE FROM gradivo WHERE id_ucitelj = ?");
    $stmt->execute([$id]);
    
    // Delete from ucenec_urnik (student timetable)
    $stmt = $pdo->prepare("DELETE FROM ucenec_urnik WHERE id_ucenec = ? OR id_ucitelj = ?");
    $stmt->execute([$id, $id]);
    
    // Delete from ucenec_predmet (student subject relation)
    $stmt = $pdo->prepare("DELETE FROM ucenec_predmet WHERE id_ucenec = ? OR id_ucitelj = ?");
    $stmt->execute([$id, $id]);
    
    // Delete from ucitelj_predmet (teacher subject relation)
    $stmt = $pdo->prepare("DELETE FROM ucitelj_predmet WHERE id_ucitelj = ?");
    $stmt->execute([$id]);
    
    // Delete from urnik (timetable)
    $stmt = $pdo->prepare("DELETE FROM urnik WHERE id_ucitelj = ?");
    $stmt->execute([$id]);
    
    // Delete from naloga_ucenec if table exists (check first)
    try {
        $stmt = $pdo->prepare("DELETE FROM naloga_ucenec WHERE id_ucenec = ?");
        $stmt->execute([$id]);
    } catch (\PDOException $e) {
        // Table might not exist, continue
    }

    // 3. Finally, delete the user from uporabnik table
    $stmt = $pdo->prepare("DELETE FROM uporabnik WHERE id_uporabnik = ?");
    $stmt->execute([$id]);
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Uporabnik uspešno izbrisan.']);
    
} catch (\PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Napaka pri brisanju uporabnika: ' . $e->getMessage()]);
    error_log("User deletion error: " . $e->getMessage());
}

