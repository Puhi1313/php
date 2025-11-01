<?php
session_start();
require_once 'povezava.php';
header('Content-Type: application/json');

// 1. ZAŠČITA: Samo admin lahko izvaja ta ukaz
if (!isset($_SESSION['user_id']) || $_SESSION['vloga'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Dostop zavrnjen.']);
    exit;
}

// 2. PRIDOBITEV PODATKOV
$data = json_decode(file_get_contents("php://input"), true);

$id_uporabnik = $data['id_uporabnik'] ?? null;
$ime = trim($data['ime'] ?? '');
$priimek = trim($data['priimek'] ?? '');
$email = trim($data['email'] ?? '');
$vloga = $data['vloga'] ?? null;
$status = $data['status'] ?? null;
$prvi_vpis = $data['prvi_vpis'] ?? null;
$novo_geslo = $data['novo_geslo'] ?? '';

// 3. VALIDACIJA
if (empty($id_uporabnik) || empty($ime) || empty($priimek) || empty($email) || empty($vloga) || empty($status) || $prvi_vpis === null) {
    echo json_encode(['success' => false, 'message' => 'Manjkajo obvezni podatki.']);
    exit;
}

// 4. PRIPRAVA POSODOBITVE
$sql = "UPDATE uporabnik SET ime = ?, priimek = ?, email = ?, vloga = ?, status = ?, prvi_vpis = ?";
$params = [$ime, $priimek, $email, $vloga, $status, $prvi_vpis];

// DODAJANJE GESLA, ČE JE VNESENO
if (!empty($novo_geslo)) {
    $hashirano_geslo = password_hash($novo_geslo, PASSWORD_DEFAULT);
    $sql .= ", geslo = ?";
    $params[] = $hashirano_geslo;
}

$sql .= " WHERE id_uporabnik = ?";
$params[] = $id_uporabnik;

// 5. IZVAJANJE POSODOBITVE
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    if ($stmt->rowCount()) {
        echo json_encode(['success' => true, 'message' => 'Uporabnik uspešno posodobljen.']);
    } else {
        // Če rowCount = 0, se lahko zgodi, da niso bile spremenjene nobene vrednosti, kar ni nujno napaka
        echo json_encode(['success' => true, 'message' => 'Uporabnik posodobljen (ali ni bilo sprememb).']);
    }

} catch (\PDOException $e) {
    // Napaka baze (npr. duplikat emaila)
    if ($e->getCode() === '23000') { // 23000 je koda za integritetno napako (npr. duplikat ključa)
        echo json_encode(['success' => false, 'message' => 'Napaka: E-mail naslov morda že obstaja.']);
    } else {
        error_log("Admin Update Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Napaka baze: ' . $e->getMessage()]);
    }
}
?>