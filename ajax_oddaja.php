<?php
session_start();
require_once 'povezava.php';
header('Content-Type: application/json');

// Preverjanje prijave in vloge
if (!isset($_SESSION['user_id']) || $_SESSION['vloga'] !== 'ucenec') {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Dostop zavrnjen. Niste učenec."]);
    exit;
}

$user_id = $_SESSION['user_id'];
$id_naloga = $_POST['id_naloga'] ?? null;
$besedilo_oddaje = trim($_POST['besedilo_oddaje'] ?? '');

if (empty($id_naloga)) {
    echo json_encode(["success" => false, "message" => "Manjka ID naloge."]);
    exit;
}

$pot_na_strezniku = null;
$ze_oddano = false; 
$oddaja_data = null; 

try {
    // 1. Pridobimo podatke o nalogi
    $sql_naloga = "SELECT rok_oddaje FROM naloga WHERE id_naloga = ?";
    $stmt_naloga = $pdo->prepare($sql_naloga);
    $stmt_naloga->execute([$id_naloga]);
    $naloga_data = $stmt_naloga->fetch();
    
    if (!$naloga_data) {
        echo json_encode(["success" => false, "message" => "Naloga ni najdena."]);
        exit;
    }

    // 2. Preverimo obstoj prejšnje oddaje in rok oddaje
    $sql_oddaja = "SELECT * FROM oddaja WHERE id_naloga = ? AND id_ucenec = ? ORDER BY id_oddaja DESC LIMIT 1";
    $stmt_oddaja = $pdo->prepare($sql_oddaja);
    $stmt_oddaja->execute([$id_naloga, $user_id]);
    $oddaja_data = $stmt_oddaja->fetch();
    
    if ($oddaja_data) {
        $ze_oddano = true;
    }

    $rok_oddaje_original = new DateTime($naloga_data['rok_oddaje']);
    $danes = new DateTime();
    $je_prepozno = $danes > $rok_oddaje_original;
    
    // Če je ocenjeno ND, dopustimo dopolnitev tudi po roku, sicer ne
    $allow_re_submission = $oddaja_data && strtoupper($oddaja_data['ocena']) === 'ND';
    
    if ($je_prepozno && !$allow_re_submission) {
        echo json_encode(["success" => false, "message" => "Rok za oddajo je potekel in nimate dovoljenja za dopolnitev (vaša ocena ni ND)."]);
        exit;
    }

    // 3. Obdelava datoteke
    if (isset($_FILES['datoteka']) && $_FILES['datoteka']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/oddaje/'; // Predpostavljamo, da ta mapa obstaja!
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_name = uniqid('oddaja_') . '_' . basename($_FILES['datoteka']['name']);
        $target_file = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['datoteka']['tmp_name'], $target_file)) {
            $pot_na_strezniku = $target_file;
            
            // Če je datoteka naložena, staro datoteko izbrišemo (samo, če obstaja)
            if ($ze_oddano && $oddaja_data['pot_na_strezniku'] && file_exists($oddaja_data['pot_na_strezniku'])) {
                 @unlink($oddaja_data['pot_na_strezniku']);
            }
        } else {
            echo json_encode(["success" => false, "message" => "Napaka pri nalaganju datoteke na strežnik."]);
            exit;
        }
    } else {
        // Če ni naložene nove datoteke, ohranimo staro pot (za posodobitev)
        if ($ze_oddano) {
            $pot_na_strezniku = $oddaja_data['pot_na_strezniku'];
        }
    }
    
    // 4. Preverjanje vsebine
    if (empty($besedilo_oddaje) && empty($pot_na_strezniku)) {
        echo json_encode(["success" => false, "message" => "Za oddajo vnesite besedilo ali naložite datoteko."]);
        exit;
    }
    
    // 5. Vstavitev ali Posodobitev v bazo
    if ($ze_oddano) {
        // Posodobimo (ponovna oddaja/dopolnitev). Ponastavimo oceno in komentar.
        $sql_update = "UPDATE oddaja SET datum_oddaje = NOW(), besedilo_oddaje = ?, pot_na_strezniku = ?, status = 'Oddano', ocena = NULL, komentar_ucitelj = NULL
                       WHERE id_oddaja = ?";
        $stmt_oddaja = $pdo->prepare($sql_update);
        $stmt_oddaja->execute([$besedilo_oddaje, $pot_na_strezniku, $oddaja_data['id_oddaja']]);
        $sporocilo = "Oddaja uspešno posodobljena!";
        
    } else {
        // Vstavimo (prva oddaja)
        $sql_insert = "INSERT INTO oddaja (id_naloga, id_ucenec, besedilo_oddaje, pot_na_strezniku) 
                       VALUES (?, ?, ?, ?)";
        $stmt_oddaja = $pdo->prepare($sql_insert);
        $stmt_oddaja->execute([$id_naloga, $user_id, $besedilo_oddaje, $pot_na_strezniku]);
        $sporocilo = "Naloga uspešno oddana!";
    }

    echo json_encode(["success" => true, "message" => $sporocilo]);
    
} catch (\PDOException $e) {
    // V primeru neuspeha poskrbimo za tiho brisanje naložene datoteke
    if ($pot_na_strezniku && file_exists($pot_na_strezniku)) {
        @unlink($pot_na_strezniku);
    }
    echo json_encode(["success" => false, "message" => "Napaka baze: " . $e->getMessage()]);
} catch (\Exception $e) {
    echo json_encode(["success" => false, "message" => "Splošna napaka: " . $e->getMessage()]);
}