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
$ze_oddano = false; // Predpostavimo, da še ni oddano

try {
    // 1. Preverimo rok oddaje in status prejšnje oddaje
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
    
    // Preverimo, ali je že oddano (za logiko "prva oddaja po roku ni možna")
    $sql_check_oddaja = "SELECT id_oddaja, pot_na_strezniku FROM oddaja WHERE id_naloga = ? AND id_ucenec = ?";
    $stmt_check_oddaja = $pdo->prepare($sql_check_oddaja);
    $stmt_check_oddaja->execute([$id_naloga, $user_id]);
    $oddaja_data = $stmt_check_oddaja->fetch();
    
    if ($oddaja_data) {
        $ze_oddano = true;
        // Če posodablja, shranimo staro pot datoteke, da jo lahko zbrišemo, če pride nova
        $stara_pot_na_strezniku = $oddaja_data['pot_na_strezniku'];
    }

    if (!$ze_oddano && $danes > $rok_oddaje) {
        echo json_encode(["success" => false, "message" => "ROK ZA ODDAJO JE POTEKEL. Oddaja ni več mogoča."]);
        exit;
    }

    // 2. Obdelava datoteke, če je naložena
    if (isset($_FILES['datoteka_ucenec']) && $_FILES['datoteka_ucenec']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/oddaje/';
        // Tudi če je mapa ustvarjena zgoraj, je ta kontrola varnostna mreža
        if (!is_dir($upload_dir)) {
            // Poskus ustvarjanja mape, če ne obstaja
            if (!mkdir($upload_dir, 0777, true)) {
                echo json_encode(["success" => false, "message" => "Napaka pri ustvarjanju mape za oddaje. Poskusite ponovno ali kontaktirajte administratorja."]);
                exit;
            }
        }
        
        // Dodamo ID učenca in naloge v ime datoteke
        $original_filename = basename($_FILES['datoteka_ucenec']['name']);
        $extension = pathinfo($original_filename, PATHINFO_EXTENSION);
        $filename = $user_id . '_' . $id_naloga . '_' . time() . '.' . $extension;
        $target_file = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['datoteka_ucenec']['tmp_name'], $target_file)) {
            $pot_na_strezniku = $target_file;
            
            // Če posodabljamo, zbrišemo staro datoteko, če obstaja (in če je nova uspešno naložena)
            if ($ze_oddano && $pot_na_strezniku && isset($stara_pot_na_strezniku) && file_exists($stara_pot_na_strezniku)) {
                 @unlink($stara_pot_na_strezniku); // @ za preprečitev napak, če brisanje ne uspe
            }

        } else {
            // Če je prišlo do napake pri prenosu (npr. prevelika datoteka)
            echo json_encode(["success" => false, "message" => "Napaka pri nalaganju datoteke na strežnik. Preverite velikost datoteke."]);
            exit;
        }
    } else {
         // Če ni nove datoteke, obdržimo staro pot, če obstaja posodobitev
         if ($ze_oddano) {
             $pot_na_strezniku = $oddaja_data['pot_na_strezniku'];
         }
    }

    // Preverimo, ali je učenec oddal vsaj besedilo ali datoteko
    if (empty($besedilo_oddaje) && empty($pot_na_strezniku)) {
        echo json_encode(["success" => false, "message" => "Za oddajo vnesite besedilo ali naložite datoteko."]);
        exit;
    }
    
    // 3. Vstavitev ali Posodobitev v bazo
    if ($ze_oddano) {
        // Posodobimo (ponovna oddaja)
        $sql_update = "UPDATE oddaja SET datum_oddaje = NOW(), besedilo_oddaje = ?, pot_na_strezniku = ?, status = 'Oddano', ocena = NULL, komentar_ucitelj = NULL
                       WHERE id_naloga = ? AND id_ucenec = ?";
        $stmt_oddaja = $pdo->prepare($sql_update);
        $stmt_oddaja->execute([$besedilo_oddaje, $pot_na_strezniku, $id_naloga, $user_id]);
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
    // V primeru PDO napake (baza)
    echo json_encode(["success" => false, "message" => "Napaka baze: " . $e->getMessage()]);
} catch (\Exception $e) {
    // V primeru druge napake
    echo json_encode(["success" => false, "message" => "Nepredvidena napaka: " . $e->getMessage()]);
}
?>