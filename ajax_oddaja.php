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
    
    // 2. Get latest submission (for checking if already submitted and waiting for grade)
    $sql_latest = "SELECT id_oddaja, pot_na_strezniku, status, ocena, podaljsan_rok, datum_oddaje
                   FROM oddaja 
                   WHERE id_naloga = ? AND id_ucenec = ? 
                   ORDER BY id_oddaja DESC LIMIT 1";
    $stmt_latest = $pdo->prepare($sql_latest);
    $stmt_latest->execute([$id_naloga, $user_id]);
    $latest_submission = $stmt_latest->fetch();
    
    // 3. Check for ANY submission with ocena = '1' (regardless of status or podaljsan_rok)
    $sql_failing = "SELECT id_oddaja, pot_na_strezniku, status, ocena, podaljsan_rok, datum_oddaje
                    FROM oddaja 
                    WHERE id_naloga = ? AND id_ucenec = ? 
                    AND ocena = '1'
                    ORDER BY datum_oddaje DESC LIMIT 1";
    $stmt_failing = $pdo->prepare($sql_failing);
    $stmt_failing->execute([$id_naloga, $user_id]);
    $failing_submission = $stmt_failing->fetch();
    
    $previous_oddaja_id = null;
    $ze_oddano = false;
    $rok_za_preverjanje = $rok_oddaje;
    
    // CRITICAL: If there's ANY submission with ocena = '1', allow re-submission
    if ($failing_submission) {
        $allow_re_submission = true;
        $ze_oddano = true;
        $previous_oddaja_id = $failing_submission['id_oddaja'];
        
        // Check podaljsan_rok if it exists
        if (!empty($failing_submission['podaljsan_rok'])) {
            $rok_za_preverjanje = new DateTime($failing_submission['podaljsan_rok']);
            // Check if extended deadline has passed
            if ($danes > $rok_za_preverjanje) {
                // Even if podaljsan_rok expired, still allow resubmission (no deadline restriction for ocena=1)
                // But use original deadline for display purposes
                $rok_za_preverjanje = $rok_oddaje;
            }
        } else {
            // No podaljsan_rok - allow resubmission without deadline restriction
            $rok_za_preverjanje = $rok_oddaje;
        }
    } elseif ($latest_submission) {
        $ze_oddano = true;
        $previous_oddaja_id = $latest_submission['id_oddaja'];
        
        // Check latest submission status
        if ($latest_submission['status'] === 'Oddano' && $latest_submission['ocena'] === NULL) {
            // Already submitted and waiting for grade - don't allow another submission
            echo json_encode(["success" => false, "message" => "Naloga je že oddana in čaka na oceno. Posodobitev ni mogoča."]);
            exit;
        } elseif ($latest_submission['status'] === 'Ocenjeno' && $latest_submission['ocena'] != '1' && strtoupper($latest_submission['ocena'] ?? '') !== 'ND') {
            // Finally graded (not 1 or ND) - don't allow re-submission
            echo json_encode(["success" => false, "message" => "Naloga je že ocenjena. Ponovna oddaja ni mogoča."]);
            exit;
        } elseif ($latest_submission['status'] === 'Dopolnitev' && strtoupper($latest_submission['ocena'] ?? '') === 'ND') {
            // ND grade - allow re-submission within 7 days
            $allow_re_submission = true;
            if ($latest_submission['datum_oddaje']) {
                $dt = new DateTime($latest_submission['datum_oddaje']);
                $until = new DateTime($dt->format('Y-m-d H:i:s'));
                $until->modify('+7 days');
                if ($danes > $until) {
                    echo json_encode(["success" => false, "message" => "Rok za dopolnitev je potekel."]);
                    exit;
                }
            }
        }
        
        // Use podaljsan_rok if available from latest submission
        if (!empty($latest_submission['podaljsan_rok'])) {
            $rok_za_preverjanje = new DateTime($latest_submission['podaljsan_rok']);
        }
    }
    
    // 4. Final deadline check (for new submissions or non-failing re-submissions)
    // CRITICAL: Don't block resubmissions for tasks with ocena=1 (allow_re_submission is true)
    if (!$allow_re_submission && $danes > $rok_za_preverjanje) {
        echo json_encode(["success" => false, "message" => "Rok za oddajo je potekel."]);
        exit;
    }
    
    // For tasks with ocena=1, if podaljsan_rok exists and is still valid, use it; otherwise allow anyway
    if ($allow_re_submission && $failing_submission && !empty($failing_submission['podaljsan_rok'])) {
        $podaljsan_rok_dt = new DateTime($failing_submission['podaljsan_rok']);
        if ($danes <= $podaljsan_rok_dt) {
            $rok_za_preverjanje = $podaljsan_rok_dt;
        }
        // If podaljsan_rok expired, still allow resubmission (no deadline restriction for ocena=1)
    }
    
    // 5. Obdelava datoteke
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
            
            // Note: We don't delete old files on re-submission since we're preserving history
            // Old submissions are marked as 'Zamenjana' but files are kept for reference

        } else {
            echo json_encode(["success" => false, "message" => "Napaka pri nalaganju datoteke."]);
            exit;
        }
    } else {
        // If no new file is uploaded for re-submission, the submission must include text
        // We don't carry over old file paths in re-submissions - student must provide new content
        $pot_na_strezniku = null;
    }

    // Preverimo, ali je vneseno besedilo ALI naložena datoteka
    if (empty($besedilo_oddaje) && empty($pot_na_strezniku)) {
        echo json_encode(["success" => false, "message" => "Za oddajo vnesite besedilo ali naložite datoteko."]);
        exit;
    }
    
    // 6. Vstavitev v bazo (ALWAYS INSERT for re-submissions to preserve history)
    $pdo->beginTransaction();
    
    try {
        if ($ze_oddano && $allow_re_submission) {
            // This is a re-submission (failing task or ND grade)
            // Mark failing submission(s) and any active 'Oddano' submissions as 'Zamenjana' to preserve history
            // This ensures the failing submission (ocena=1) is preserved but marked inactive
            $sql_update_previous = "UPDATE oddaja 
                                    SET status = 'Zamenjana' 
                                    WHERE id_naloga = ? 
                                    AND id_ucenec = ? 
                                    AND (status = 'Dopolnitev' OR status = 'Oddano')
                                    AND status != 'Zamenjana'";
            $stmt_update_previous = $pdo->prepare($sql_update_previous);
            $stmt_update_previous->execute([$id_naloga, $user_id]);
            
            // INSERT new submission - explicitly set ocena and komentar_ucitelj to NULL for re-grading
            // This flags the task for re-grading after resubmission
            $sql_insert = "INSERT INTO oddaja (id_naloga, id_ucenec, besedilo_oddaje, pot_na_strezniku, status, ocena, komentar_ucitelj) 
                           VALUES (?, ?, ?, ?, 'Oddano', NULL, NULL)";
            $stmt_oddaja = $pdo->prepare($sql_insert);
            $stmt_oddaja->execute([$id_naloga, $user_id, $besedilo_oddaje, $pot_na_strezniku]);
            $sporocilo = "Dopolnitev uspešno oddana!";
            
        } elseif (!$ze_oddano) {
            // First submission - INSERT new record (ocena and komentar_ucitelj default to NULL)
            $sql_insert = "INSERT INTO oddaja (id_naloga, id_ucenec, besedilo_oddaje, pot_na_strezniku, status) 
                           VALUES (?, ?, ?, ?, 'Oddano')";
            $stmt_oddaja = $pdo->prepare($sql_insert);
            $stmt_oddaja->execute([$id_naloga, $user_id, $besedilo_oddaje, $pot_na_strezniku]);
            $sporocilo = "Naloga uspešno oddana!";
        } else {
            throw new Exception("Napaka: Oddaja ni mogoča.");
        }
        
        $pdo->commit();
    } catch (\PDOException $e) {
        $pdo->rollBack();
        throw $e;
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