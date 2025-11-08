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

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 1. Retrieve the old file path (icona_profila)
    $stmt = $pdo->prepare("SELECT icona_profila FROM uporabnik WHERE id_uporabnik = ?");
    $stmt->execute([$id]);
    $old = $stmt->fetchColumn();

    // 2. Unlink the file from the server (if it exists)
    if (!empty($old)) {
        $oldPath = __DIR__ . DIRECTORY_SEPARATOR . $old;
        $safeDir = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'slike');
        // Verify the file is in the safe directory ('slike')
        if (file_exists($oldPath)) {
            $realOldPath = realpath($oldPath);
            if ($realOldPath && ($safeDir === false || strpos($realOldPath, $safeDir) === 0)) {
                @unlink($oldPath);
            }
        }
    }

    // 3. Set icona_profila column to NULL in the uporabnik table
    $stmt = $pdo->prepare("UPDATE uporabnik SET icona_profila = NULL WHERE id_uporabnik = ?");
    $stmt->execute([$id]);

    // 4. Return success JSON response
    echo json_encode(['success' => true, 'message' => 'Profilna slika uspešno izbrisana.']);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Napaka pri bazi podatkov: ' . $e->getMessage()]);
}