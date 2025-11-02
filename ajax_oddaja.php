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
$allow_re_submission = false; // Dovoljenje za dopolnitev po nezadostni oceni
$oddaja_data = null; // Podatki o obstoječi oddaji

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

    $rok_oddaje = new DateTime($naloga_data['rok_oddaje']);
    $danes = new DateTime();
    
    // 2. Preverimo obstoječo oddajo (vedno zadnjo oddajo za to nalogo)
    $sql_oddaja = "SELECT id_oddaja, pot_na_strezniku, status, ocena FROM oddaja WHERE id_naloga = ? AND id_ucenec = ? ORDER BY id_oddaja DESC LIMIT 1";
    $stmt_oddaja = $pdo->prepare($sql_oddaja);
    $stmt_oddaja->execute([$id_naloga, $user_id]);
    $oddaja_data = $stmt_oddaja->fetch();

    if ($oddaja_data) {
        $ze_oddano = true;
        // Dovolimo ponovno oddajo/dopolnitev samo, če je status 'Ocenjeno' in ocena 'ND'
        if ($oddaja_data['status'] === 'Ocenjeno' && strtoupper($oddaja_data['ocena'] ?? '') === 'ND') {
            $allow_re_submission = true;
        } elseif ($oddaja_data['status'] === 'Oddano' && $oddaja_data['ocena'] === NULL) {
            // Če je že oddano in čaka na oceno, ne dovolimo oddaje (razen če bi bila možnost preklic oddaje)
            echo json_encode(["success" => false, "message" => "Naloga je že oddana in čaka na oceno. Posodobitev ni mogoča."]);
            exit;
        } elseif ($oddaja_data['status'] === 'Ocenjeno' && strtoupper($oddaja_data['ocena'] ?? '') !== 'ND') {
            // Če je končno ocenjeno, ne dovolimo ponovne oddaje
            echo json_encode(["success" => false, "message" => "Naloga je že ocenjena. Ponovna oddaja ni mogoča."]);
            exit;
        }
    }

    // 3. Preverjanje roka
    if ($danes > $rok_oddaje && !$allow_re_submission) {
        echo json_encode(["success" => false, "message" => "Rok za oddajo je potekel."]);
        exit;
    }
    
    // 4. Obdelava datoteke
    if (isset($_FILES['datoteka']) && $_FILES['datoteka']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/oddaje/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_info = pathinfo($_FILES['datoteka']['name']);
        $extension = strtolower($file_info['extension']);
        $safe_filename = bin2hex(random_bytes(8)) . '_' . time() . '.' . $extension;
        $target_file = $upload_dir . $safe_filename;

        if (move_uploaded_file($_FILES['datoteka']['tmp_name'], $target_file)) {
            $pot_na_strezniku = $target_file;
            
            // Če gre za posodobitev oddaje in je bila prejšnja datoteka, jo zbrišemo
            if ($ze_oddano && $oddaja_data['pot_na_strezniku']) {
                @unlink($oddaja_data['pot_na_strezniku']);
            }

        } else {
            echo json_encode(["success" => false, "message" => "Napaka pri nalaganju datoteke."]);
            exit;
        }
    } else {
        // Če ni naložene nove datoteke, ohranimo staro pot pri posodobitvi
        if ($ze_oddano) {
            $pot_na_strezniku = $oddaja_data['pot_na_strezniku'];
        }
    }

    // Preverimo, ali je vneseno besedilo ALI naložena datoteka
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
    http_response_code(500);
    error_log("Database error in ajax_oddaja: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Napaka baze: " . $e->getMessage()]);
} catch (\Exception $e) {
    http_response_code(500);
    error_log("General error in ajax_oddaja: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Prišlo je do splošne napake."]);
}
?>