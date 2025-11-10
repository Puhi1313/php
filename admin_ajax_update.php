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
$raw_input = file_get_contents("php://input");
$data = json_decode($raw_input, true);

// Debug: Log raw input to see what we're receiving
error_log("Raw input: " . $raw_input);
error_log("Decoded data: " . json_encode($data));

// Extract values - ensure status is properly captured as string
$id_uporabnik = isset($data['id_uporabnik']) ? (int)$data['id_uporabnik'] : null;
$ime = isset($data['ime']) ? trim($data['ime']) : '';
$priimek = isset($data['priimek']) ? trim($data['priimek']) : '';
$email = isset($data['email']) ? trim($data['email']) : '';
$vloga = isset($data['vloga']) ? trim($data['vloga']) : null;
// CRITICAL: Ensure status is captured as string, not null or empty
$status = isset($data['status']) ? trim((string)$data['status']) : null;
$prvi_vpis = isset($data['prvi_vpis']) ? $data['prvi_vpis'] : null;
$novo_geslo = isset($data['novo_geslo']) ? trim($data['novo_geslo']) : '';

// Debug: Log extracted status value
error_log("Extracted status: " . var_export($status, true) . " (type: " . gettype($status) . ")");

// 3. VALIDACIJA
// Valid status values - MUST match database enum('pending','active','rejected')
$valid_statuses = ['pending', 'active', 'rejected'];

// Validate required fields
if (empty($id_uporabnik) || empty($ime) || empty($priimek) || empty($email) || empty($vloga) || $prvi_vpis === null) {
    echo json_encode([
        'success' => false, 
        'message' => 'Manjkajo obvezni podatki.',
        'debug' => [
            'id_uporabnik' => $id_uporabnik,
            'ime' => $ime,
            'priimek' => $priimek,
            'email' => $email,
            'vloga' => $vloga,
            'prvi_vpis' => $prvi_vpis
        ]
    ]);
    exit;
}

// CRITICAL: Validate status separately - must be a non-empty string and valid value
if ($status === null || $status === '') {
    echo json_encode([
        'success' => false, 
        'message' => 'Status je obvezen podatek.',
        'debug' => [
            'status_received' => $data['status'] ?? 'NOT_SET',
            'status_after_trim' => $status,
            'status_type' => gettype($status)
        ]
    ]);
    exit;
}

if (!in_array($status, $valid_statuses, true)) {
    echo json_encode([
        'success' => false, 
        'message' => 'Status mora biti ena od vrednosti: ' . implode(', ', $valid_statuses),
        'debug' => [
            'status' => $status,
            'valid_statuses' => $valid_statuses,
            'status_in_array' => in_array($status, $valid_statuses, true)
        ]
    ]);
    exit;
}

// NOVO PREVERJANJE: Če je geslo vneseno, mora imeti vsaj 12 znakov
if (!empty($novo_geslo) && strlen($novo_geslo) < 12) {
    echo json_encode(['success' => false, 'message' => 'Geslo mora vsebovati vsaj 12 znakov.']);
    exit;
}

// 4. PRIPRAVA POSODOBITVE
// CRITICAL: Ensure status is explicitly set as a string in the SQL query
$sql = "UPDATE uporabnik SET ime = ?, priimek = ?, email = ?, vloga = ?, status = ?, prvi_vpis = ?";
// CRITICAL: Verify status is not null before adding to params
if ($status === null || $status === '') {
    error_log("ERROR: Status is null or empty before SQL execution!");
    echo json_encode([
        'success' => false, 
        'message' => 'Napaka: Status ni nastavljen pred izvajanjem poizvedbe.',
        'debug' => ['status' => $status]
    ]);
    exit;
}
$params = [$ime, $priimek, $email, $vloga, $status, $prvi_vpis];

// Debug: Verify params before execution
error_log("SQL params before execution: " . json_encode($params));
error_log("Status in params array: " . var_export($params[4], true));

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
    $result = $stmt->execute($params);
    
    // Debug: Log the actual values being used
    error_log("Update query: " . $sql);
    error_log("Update params: " . json_encode($params));
    error_log("Status value: " . var_export($status, true));

    // CRITICAL: Verify the update was successful by checking the database
    if ($stmt->rowCount() > 0) {
        // Double-check that the status was actually saved
        $verify_stmt = $pdo->prepare("SELECT status FROM uporabnik WHERE id_uporabnik = ?");
        $verify_stmt->execute([$id_uporabnik]);
        $saved_status = $verify_stmt->fetchColumn();
        
        if ($saved_status === $status) {
            echo json_encode([
                'success' => true, 
                'message' => 'Uporabnik uspešno posodobljen.',
                'debug' => [
                    'rows_affected' => $stmt->rowCount(), 
                    'status_sent' => $status,
                    'status_saved' => $saved_status,
                    'match' => true
                ]
            ]);
        } else {
            // Status didn't match - something went wrong
            error_log("CRITICAL: Status mismatch! Sent: " . $status . ", Saved: " . $saved_status);
            echo json_encode([
                'success' => false, 
                'message' => 'Napaka: Status ni bil pravilno shranjen v bazo.',
                'debug' => [
                    'status_sent' => $status,
                    'status_saved' => $saved_status,
                    'rows_affected' => $stmt->rowCount()
                ]
            ]);
        }
    } else {
        // Check if the values actually changed by querying the current state
        $check_stmt = $pdo->prepare("SELECT status FROM uporabnik WHERE id_uporabnik = ?");
        $check_stmt->execute([$id_uporabnik]);
        $current_status = $check_stmt->fetchColumn();
        
        if ($current_status === $status) {
            // Status is already what we're trying to set, so no change needed
            echo json_encode([
                'success' => true, 
                'message' => 'Uporabnik posodobljen (status je že nastavljen na ' . $status . ').',
                'debug' => ['current_status' => $current_status, 'new_status' => $status]
            ]);
        } else {
            // Something went wrong - values didn't match
            error_log("CRITICAL: Update failed! Current: " . $current_status . ", Attempted: " . $status);
            echo json_encode([
                'success' => false, 
                'message' => 'Posodobitev ni uspela. Preverite podatke.',
                'debug' => [
                    'current_status' => $current_status, 
                    'attempted_status' => $status,
                    'rows_affected' => $stmt->rowCount()
                ]
            ]);
        }
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