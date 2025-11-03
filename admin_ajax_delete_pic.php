<?php
<?php
session_start();
require_once 'povezava.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['vloga'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Dostop zavrnjen.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id = (int)($data['id_uporabnik'] ?? 0);
if (!$id) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Neveljaven ID.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT icona_profila FROM uporabnik WHERE id_uporabnik = ?");
    $stmt->execute([$id]);
    $old = $stmt->fetchColumn();

    $stmt = $pdo->prepare("UPDATE uporabnik SET icona_profila = NULL WHERE id_uporabnik = ?");
    $stmt->execute([$id]);

    if (!empty($old)) {
        $oldPath = __DIR__ . DIRECTORY_SEPARATOR . $old;
        if (file_exists($oldPath) && strpos(realpath($oldPath), realpath(__DIR__)) === 0) {
            @unlink($oldPath);
        }
    }

    echo json_encode(['success'=>true,'message'=>'Slika izbrisana.']);
} catch (PDOException $e) {
    error_log('admin_ajax_delete_pic error: '.$e->getMessage());
    echo json_encode(['success'=>false,'message'=>'Napaka baze.']);
}
?>