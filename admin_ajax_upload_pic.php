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

$id = isset($_POST['id_uporabnik']) ? (int)$_POST['id_uporabnik'] : 0;
if (!$id) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Neveljaven ID.']);
    exit;
}
if (!isset($_FILES['profile_pic'])) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Ni datoteke.']);
    exit;
}

$file = $_FILES['profile_pic'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Napaka pri nalaganju.']);
    exit;
}

$maxSize = 2 * 1024 * 1024;
$allowed = ['image/jpeg','image/png','image/gif'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
if (!in_array($mime, $allowed)) {
    echo json_encode(['success'=>false,'message'=>'Neveljavna tipka datoteke.']);
    exit;
}
if ($file['size'] > $maxSize) {
    echo json_encode(['success'=>false,'message'=>'Datoteka prevelika (max 2MB).']);
    exit;
}

try {
    // get old path
    $stmt = $pdo->prepare("SELECT icona_profila FROM uporabnik WHERE id_uporabnik = ?");
    $stmt->execute([$id]);
    $old = $stmt->fetchColumn();

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext === '') {
        $ext = $mime === 'image/png' ? 'png' : ($mime === 'image/gif' ? 'gif' : 'jpg');
    }
    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'slike';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $filename = 'icona_' . $id . '_' . time() . '.' . $ext;
    $target = $uploadDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        echo json_encode(['success'=>false,'message'=>'Premik datoteke ni uspel.']);
        exit;
    }

    $relative = 'slike/' . $filename;
    $stmt = $pdo->prepare("UPDATE uporabnik SET icona_profila = ? WHERE id_uporabnik = ?");
    $stmt->execute([$relative, $id]);

    // delete old file if exists and inside project
    if (!empty($old)) {
        $oldPath = __DIR__ . DIRECTORY_SEPARATOR . $old;
        if (file_exists($oldPath) && strpos(realpath($oldPath), realpath(__DIR__)) === 0) {
            @unlink($oldPath);
        }
    }

    echo json_encode(['success'=>true,'message'=>'Slika naložena.','path'=>$relative]);
} catch (PDOException $e) {
    error_log('admin_ajax_upload_pic error: '.$e->getMessage());
    echo json_encode(['success'=>false,'message'=>'Napaka baze.']);
}
?>