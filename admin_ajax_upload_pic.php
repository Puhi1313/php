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
// Preverjanje napake nalaganja na strežniku
if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Napaka pri nalaganju (Code: ' . $file['error'] . ').']);
    exit;
}

$maxSize = 2 * 1024 * 1024; // 2MB
$allowed = ['image/jpeg','image/png','image/gif'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Datoteka je prevelika (max 2MB).']);
    exit;
}

if (!in_array($mime, $allowed)) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Dovoljeni so le formati JPEG, PNG in GIF.']);
    exit;
}

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // get old path
    $stmt = $pdo->prepare("SELECT icona_profila FROM uporabnik WHERE id_uporabnik = ?");
    $stmt->execute([$id]);
    $old = $stmt->fetchColumn();

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext === '') {
        $ext = $mime === 'image/png' ? 'png' : ($mime === 'image/gif' ? 'gif' : 'jpg');
    }
    
    // Ustvari mapo 'slike', če ne obstaja
    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'slike';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            echo json_encode(['success'=>false,'message'=>'Mapice za slike ni mogoče ustvariti.']);
            exit;
        }
    }

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
        $safeDir = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'slike');
        if (file_exists($oldPath) && strpos(realpath($oldPath), $safeDir) === 0) {
            @unlink($oldPath);
        }
    }
    
    echo json_encode(['success'=>true,'message'=>'Profilna slika uspešno naložena.', 'path' => $relative]);

} catch (\PDOException $e) {
    // Če je prišlo do napake v bazi, poskusi izbrisati že naloženo datoteko
    if (isset($target) && file_exists($target)) {
        @unlink($target);
    }
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Napaka pri bazi podatkov.']);
} catch (\Exception $e) {
     if (isset($target) && file_exists($target)) {
        @unlink($target);
    }
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Splošna napaka: ' . $e->getMessage()]);
}
// Pazite, da tukaj ni zaključne oznake ?>