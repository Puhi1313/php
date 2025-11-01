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
    
    // Obdelava nalaganja datoteke
    if (isset($_FILES['datoteka']) && $_FILES['datoteka']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/naloge/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $ime_datoteke = basename($_FILES['datoteka']['name']);
        $koncnica = pathinfo($ime_datoteke, PATHINFO_EXTENSION);
        $novo_ime = $id_predmet . '_' . time() . '.' . $koncnica;
        $cilj_pot = $upload_dir . $novo_ime;

        if (move_uploaded_file($_FILES['datoteka']['tmp_name'], $cilj_pot)) {
            $pot_na_strezniku = $cilj_pot;
        } else {
            echo json_encode(['success' => false, 'message' => 'Napaka pri nalaganju datoteke.']);
            exit;
        }
    }

    // Vstavljanje naloge
    try {
        $sql = "INSERT INTO naloga (id_ucitelj, id_predmet, naslov, opis_naloge, rok_oddaje, pot_na_strezniku) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $id_predmet, $naslov, $opis_naloge, $rok_oddaje, $pot_na_strezniku]);

        echo json_encode(['success' => true, 'message' => 'Naloga uspešno objavljena!']);

    } catch (\PDOException $e) {
        // Če je prišlo do napake baze, poskusimo izbrisati datoteko
        if ($pot_na_strezniku && file_exists($pot_na_strezniku)) {
            @unlink($pot_na_strezniku);
        }
        echo json_encode(['success' => false, 'message' => 'Napaka baze: ' . $e->getMessage()]);
    }

    exit;
}


// --- LOGIKA ZA PRIKAZ NALOGE (UČENEC IN UČITELJ) ---\r\n

// Podatki, poslani preko AJAX iz ucilnicaPage.php
$data = json_decode(file_get_contents("php://input"), true);
$id_predmet = $data['id_predmet'] ?? null;
$id_ucitelja = $data['id_ucitelja'] ?? null;
$id_naloga_specificna = $data['id_naloga'] ?? null; // Če učitelj izbere specifično nalogo iz arhiva

if (empty($id_predmet) || empty($id_ucitelja)) {
    echo "Izberite predmet.";
    exit;
}

$naloga = false;
$seznam_nalog = []; // Novo: Za arhiv vseh nalog

try {
    // Novo: Pridobi seznam vseh nalog za ta predmet in učitelja
    $sql_seznam_nalog = "
        SELECT id_naloga, naslov, rok_oddaje, datum_objave 
        FROM naloga 
        WHERE id_predmet = ? AND id_ucitelj = ? 
        ORDER BY datum_objave DESC
    ";
    $stmt_seznam_nalog = $pdo->prepare($sql_seznam_nalog);
    $stmt_seznam_nalog->execute([$id_predmet, $id_ucitelja]);
    $seznam_nalog = $stmt_seznam_nalog->fetchAll();

    if ($id_naloga_specificna) {
        // Če je določen ID naloge (klik iz arhiva/seznama), pridobi to nalogo
        $sql_naloga = "
            SELECT * FROM naloga 
            WHERE id_naloga = ? AND id_predmet = ? AND id_ucitelj = ? 
            LIMIT 1
        ";
        $stmt_naloga = $pdo->prepare($sql_naloga);
        $stmt_naloga->execute([$id_naloga_specificna, $id_predmet, $id_ucitelja]);
        $naloga = $stmt_naloga->fetch();
        
    } elseif (!empty($seznam_nalog)) {
        // Če ni določen ID naloge, a obstajajo naloge, privzeto prikaži zadnjo objavljeno
        $id_zadnje_naloge = $seznam_nalog[0]['id_naloga'];
        $sql_naloga = "
            SELECT * FROM naloga 
            WHERE id_naloga = ?
            LIMIT 1
        ";
        $stmt_naloga = $pdo->prepare($sql_naloga);
        $stmt_naloga->execute([$id_zadnje_naloge]);
        $naloga = $stmt_naloga->fetch();
    }
    
    // Če naloge ni (ali je prazno), $naloga ostane 'false'
    
} catch (\PDOException $e) {
    // V primeru napake izpišemo sporočilo
    die("Napaka pri pridobivanju nalog: " . $e->getMessage());
}


// Vključi ustrezno datoteko glede na vlogo
if ($vloga === 'ucenec') {
    include 'naloga_ucenec.php';
} else {
    // Dodamo seznam vseh nalog kot spremenljivko v učiteljevo datoteko
    include 'naloga_ucitelj.php';
}
?>