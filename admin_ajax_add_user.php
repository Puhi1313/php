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

$ime = trim($data['ime'] ?? '');
$priimek = trim($data['priimek'] ?? '');
$vloga = $data['vloga'] ?? '';
$mesto = trim($data['mesto'] ?? '');
$kontakt_email = trim($data['kontakt_email'] ?? '');

// 1. VALIDACIJA
if (empty($ime) || empty($priimek) || empty($vloga) || empty($mesto) || empty($kontakt_email)) {
    echo json_encode(['success' => false, 'message' => 'Manjkajo obvezni podatki.']);
    exit;
}
if (!in_array($vloga, ['ucenec', 'ucitelj', 'admin'])) {
    echo json_encode(['success' => false, 'message' => 'Neveljavna vloga.']);
    exit;
}

// Funkcija za generiranje šolskega e-maila
function generate_school_email($ime, $priimek, $pdo) {
    // Odstrani šumnike, presledke in pretvori v male črke (za osnovo e-maila)
    $ime_clean = strtolower(preg_replace('/[^a-z0-9]/i', '', iconv('utf-8', 'us-ascii//TRANSLIT', $ime)));
    $priimek_clean = strtolower(preg_replace('/[^a-z0-9]/i', '', iconv('utf-8', 'us-ascii//TRANSLIT', $priimek)));
    $base_email = $ime_clean . '.' . $priimek_clean;
    $skolski_email = $base_email . '@sola.si';
    $counter = 1;

    // Preveri, ali e-mail že obstaja in ga prilagodi (npr. janez.novak2@sola.si)
    while (true) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM uporabnik WHERE email = ?");
        $stmt->execute([$skolski_email]);
        if ($stmt->fetchColumn() == 0) {
            break;
        }
        $skolski_email = $base_email . $counter++ . '@sola.si';
    }
    return $skolski_email;
}

// 2. GENERIRANJE E-MAILA IN GESLA
$skolski_email = generate_school_email($ime, $priimek, $pdo);
$temporary_password = substr(md5(rand()), 0, 8); // Random 8-znak geslo
$hashirano_geslo = password_hash($temporary_password, PASSWORD_DEFAULT);
$prvi_vpis = 1; // Prisilna sprememba gesla ob prvem vpisu

// 3. VSTAVITEV V BAZO
try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Use correct column names: mesto (not mesto_bivanja), osebni_email (not kontakt_email), status = 'active' (not 'aktiven')
    $sql = "INSERT INTO uporabnik (ime, priimek, email, vloga, geslo, status, prvi_vpis, mesto, osebni_email) 
            VALUES (?, ?, ?, ?, ?, 'active', ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $ime, 
        $priimek, 
        $skolski_email, 
        $vloga, 
        $hashirano_geslo, 
        $prvi_vpis, 
        $mesto, 
        $kontakt_email
    ]);
    
    $response_message = "Uporabnik **" . htmlspecialchars($ime) . " " . htmlspecialchars($priimek) . "** (Vloga: " . htmlspecialchars($vloga) . ") je uspešno dodan. **Začetni e-mail:** " . htmlspecialchars($skolski_email) . ", **začetno geslo:** " . htmlspecialchars($temporary_password);
    
    echo json_encode(['success' => true, 'message' => $response_message]);

} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Napaka pri vstavljanju v bazo. Preverite, če e-mail morda že obstaja.']);
}