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

    $rok_oddaje_original = new DateTime($naloga_data['rok_oddaje']);
    $rok_oddaje_dejanski = clone $rok_oddaje_original;
    $danes = new DateTime();


    // 2. Preverimo obstoj prejšnje oddaje in ocene
    $sql_oddaja = "SELECT * FROM oddaja WHERE id_naloga = ? AND id_ucenec = ? ORDER BY id_oddaja DESC LIMIT 1";
    $stmt_oddaja = $pdo->prepare($sql_oddaja);
    $stmt_oddaja->execute([$id_naloga, $user_id]);
    $oddaja_data = $stmt_oddaja->fetch();
    
    if ($oddaja_data) {
        $ze_oddano = true;
        $ocena_up = strtoupper(trim($oddaja_data['ocena'] ?? ''));

        // Preverjanje za dopolnitev: Dovoljena, če je ocena '1' ali 'ND'
        if ($ocena_up === '1' || $ocena_up === 'ND') {
            $allow_re_submission = true;
            
            // Logika za PODALJŠANJE ROKA: Če je rok potekel, je podaljšan za 7 dni
            if ($danes > $rok_oddaje_original) {
                 $datum_ocenjevanja = new DateTime($oddaja_data['datum_oddaje']); // Za demo uporabimo datum oddaje
                 $nov_rok = clone $datum_ocenjevanja;
                 $nov_rok->modify('+7 days');
                 
                 // Dejanski rok je lahko podaljšan (če je večji od prvotnega)
                 if ($nov_rok > $rok_oddaje_dejanski) {
                     $rok_oddaje_dejanski = $nov_rok;
                 }
            }
        } 
        // Preverjanje za Zaklep: Zaklenemo, če je oddano, ocenjeno in ocena NI '1' ali 'ND'
        elseif ($oddaja_data['ocena'] !== null) {
            echo json_encode(["success" => false, "message" => "Naloga je že ocenjena z zadostno oceno. Posodobitev ni dovoljena."]);
            exit;
        }
    }

    // Ponovno preverimo, ali je prepozno glede na DEJANJKI/PODALJŠANI rok
    $je_prepozno_dejansko = $danes > $rok_oddaje_dejanski;
    
    // 3. Glavna preprečitev oddaje: 
    if (!$ze_oddano && $je_prepozno_dejansko) {
        // Prva oddaja po prvotnem roku ni dovoljena
        echo json_encode(["success" => false, "message" => "Rok za prvo oddajo je potekel."]);
        exit;
    } 
    
    if ($ze_oddano && $je_prepozno_dejansko && !$allow_re_submission) {
        // Posodobitev po podaljšanem roku, ki ni bila dopolnitev (ne bi se smelo zgoditi, če je bil disabled form)
        echo json_encode(["success" => false, "message" => "Rok za oddajo je potekel, in naloga ni za dopolnitev."]);
        exit;
    }
    
    // Če je dopolnitev in je po podaljšanem roku
    if ($allow_re_submission && $je_prepozno_dejansko) {
        echo json_encode(["success" => false, "message" => "Rok za dopolnitev je potekel (" . $rok_oddaje_dejanski->format('d.m.Y H:i') . "). Oddaja ni več mogoča."]);
        exit;
    }


    // 4. Obdelava datoteke (enak kot prej)
    if (isset($_FILES['datoteka']) && $_FILES['datoteka']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/oddaje/'; 
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['datoteka']['name'], PATHINFO_EXTENSION);
        $file_name_clean = preg_replace('/[^A-Za-z0-9_\-]/', '_', pathinfo($_FILES['datoteka']['name'], PATHINFO_FILENAME));
        $new_file_name = $file_name_clean . '_' . $user_id . '_' . time() . '.' . $file_extension;
        $target_file = $upload_dir . $new_file_name;
        
        if (move_uploaded_file($_FILES['datoteka']['tmp_name'], $target_file)) {
            $pot_na_strezniku = $target_file;
            
            // Če je posodobitev, izbrišemo staro datoteko, če obstaja in ni enaka novi
            if ($ze_oddano && $oddaja_data['pot_na_strezniku'] && file_exists($oddaja_data['pot_na_strezniku']) && $oddaja_data['pot_na_strezniku'] !== $pot_na_strezniku) {
                @unlink($oddaja_data['pot_na_strezniku']);
            }
        } else {
             echo json_encode(['success' => false, 'message' => 'Napaka pri premiku datoteke na strežnik.']);
             exit;
        }
    } else {
        // Če ni priložena nova datoteka, obdržimo staro pot (če je to posodobitev)
        if ($ze_oddano && $oddaja_data['pot_na_strezniku']) {
            $pot_na_strezniku = $oddaja_data['pot_na_strezniku'];
        }
    }

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
    error_log("Napaka oddaje: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Splošna napaka baze podatkov pri oddaji. " . $e->getMessage()]);
} catch (\Exception $e) {
    error_log("Splošna napaka oddaje: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Splošna napaka pri obdelavi oddaje."]);
}