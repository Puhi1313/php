<?php
session_start();
require_once 'povezava.php';

// Zaščita pred direktnim dostopom in preverjanje vloge
if (!isset($_SESSION['user_id']) || !isset($_SESSION['vloga'])) {
    http_response_code(401);
    die("Dostop zavrnjen.");
}

$user_id = $_SESSION['user_id'];
$vloga = $_SESSION['vloga'];
$id_predmet = null;
$id_ucitelja = null;


// --- LOGIKA ZA KREIRANJE NOVE NALOGE (SAMO UČITELJ) ---
if ($vloga !== 'ucenec' && (isset($_GET['action']) && $_GET['action'] === 'create')) {
    
    // Uporabimo $_POST in $_FILES za obdelavo FormData
    $id_predmet = $_POST['id_predmet'] ?? null;
    $naslov = trim($_POST['naslov'] ?? '');
    $opis_naloge = trim($_POST['opis_naloge'] ?? '');
    $rok_oddaje = $_POST['rok_oddaje'] ?? null;

    if (empty($id_predmet) || empty($naslov) || empty($rok_oddaje)) {
        echo json_encode(['success' => false, 'message' => 'Manjkajo nujni podatki (predmet, naslov, rok).']);
        exit;
    }

    $pot_na_strezniku = null;
    

    // 1. Preverimo rok oddaje
    $sql_rok = "SELECT rok_oddaje FROM naloga WHERE id_naloga = ?";
    $stmt_rok = $pdo->prepare($sql_rok);
    $stmt_rok->execute([$id_naloga]);
    $rok_data = $stmt_rok->fetch();

    if (!$rok_data) {
        echo json_encode(["success" => false, "message" => "Naloga ne obstaja."]);
        exit;
    }

    $rok_oddaje = new DateTime($rok_data['rok_oddaje']);
    $danes = new DateTime();

    // Omogoči posodobitev oddaje, tudi če je rok potekel, razen če gre za prvo oddajo!
    // Logika za prvo oddajo (če je rok potekel, ne more oddati)
    $sql_check_oddaja = "SELECT id_oddaja FROM oddaja WHERE id_naloga = ? AND id_ucenec = ?";
    $stmt_check_oddaja = $pdo->prepare($sql_check_oddaja);
    $stmt_check_oddaja->execute([$id_naloga, $user_id]);
    $ze_oddano = $stmt_check_oddaja->fetch();

    if (!$ze_oddano && $danes > $rok_oddaje) {
        echo json_encode(["success" => false, "message" => "ROK ZA ODDAJO JE POTEKEL. Oddaja ni več mogoča."]);
        // Če je bila naložena datoteka, jo zbrišemo, da ne ostaja na strežniku
        if ($pot_na_strezniku && file_exists($pot_na_strezniku)) {
            unlink($pot_na_strezniku);
        }
        exit;
}
    // Obdelava datoteke, če je naložena
    if (isset($_FILES['datoteka_ucitelj']) && $_FILES['datoteka_ucitelj']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/naloge/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $filename = time() . '_' . basename($_FILES['datoteka_ucitelj']['name']);
        $target_file = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['datoteka_ucitelj']['tmp_name'], $target_file)) {
            $pot_na_strezniku = $target_file;
        } else {
            echo json_encode(['success' => false, 'message' => 'Napaka pri nalaganju datoteke na strežnik.']);
            exit;
        }
    }

    try {
        $sql = "INSERT INTO naloga (id_ucitelj, id_predmet, naslov, opis_naloge, rok_oddaje, pot_na_strezniku) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $id_predmet, $naslov, $opis_naloge, $rok_oddaje, $pot_na_strezniku]);

        echo json_encode(['success' => true, 'message' => 'Naloga uspešno objavljena!']);

    } catch (\PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Napaka baze: ' . $e->getMessage()]);
    }

    exit;
}


// --- LOGIKA ZA PRIKAZ NALOGE (UČENEC IN UČITELJ) ---

// Podatki, poslani preko AJAX iz ucilnicaPage.php
$data = json_decode(file_get_contents("php://input"), true);
$id_predmet = $data['id_predmet'] ?? null;
$id_ucitelja = $data['id_ucitelja'] ?? null;

if (empty($id_predmet) || empty($id_ucitelja)) {
    echo "Izberite predmet.";
    exit;
}

// Pridobi zadnjo nalogo za ta predmet in učitelja
try {
    $sql_naloga = "SELECT * FROM naloga WHERE id_predmet = ? AND id_ucitelj = ? ORDER BY rok_oddaje DESC LIMIT 1";
    $stmt_naloga = $pdo->prepare($sql_naloga);
    $stmt_naloga->execute([$id_predmet, $id_ucitelja]);
    $naloga = $stmt_naloga->fetch();
    
} catch (\PDOException $e) {
    echo "Napaka pri pridobivanju podatkov: " . $e->getMessage();
    exit;
}

if ($vloga === 'ucenec') {
    // PRIKAZ ZA UČENCA
    include 'naloga_ucenec.php'; 
} else {
    // PRIKAZ ZA UČITELJA (Obrazec za kreiranje)
    include 'naloga_ucitelj.php'; 
}

?>