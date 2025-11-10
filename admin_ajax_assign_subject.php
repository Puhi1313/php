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

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. HANDLE FETCH REQUEST (GET) - Get all subjects with teachers and student's current assignments
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'fetch') {
        $id_ucenec = (int)($_GET['id_ucenec'] ?? 0);
        
        if (!$id_ucenec) {
            echo json_encode(['success' => false, 'message' => 'Manjka ID učenca.']);
            exit;
        }

        // Get all subjects with their assigned teachers (from ucitelj_predmet)
        // Similar to teacher logic - get all subjects that have teachers assigned
        // Don't filter by teacher status - show all teachers (admin can decide)
        $sql_subjects = "
            SELECT 
                p.id_predmet,
                p.ime_predmeta,
                u.id_uporabnik AS id_ucitelj,
                u.ime AS ime_ucitelja,
                u.priimek AS priimek_ucitelja
            FROM predmet p
            INNER JOIN ucitelj_predmet up ON p.id_predmet = up.id_predmet
            INNER JOIN uporabnik u ON up.id_ucitelj = u.id_uporabnik
            WHERE u.vloga = 'ucitelj'
            ORDER BY p.ime_predmeta ASC, u.priimek ASC, u.ime ASC
        ";
        $stmt_subjects = $pdo->query($sql_subjects);
        $all_subjects = $stmt_subjects->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug: Log if no subjects found
        if (empty($all_subjects)) {
            error_log("No subjects with teachers found. Query: " . $sql_subjects);
            // Try a simpler query to see if there are any subjects at all
            $test_stmt = $pdo->query("SELECT COUNT(*) FROM predmet");
            $subject_count = $test_stmt->fetchColumn();
            error_log("Total subjects in database: " . $subject_count);
            
            $test_stmt2 = $pdo->query("SELECT COUNT(*) FROM ucitelj_predmet");
            $teacher_subject_count = $test_stmt2->fetchColumn();
            error_log("Total teacher-subject assignments: " . $teacher_subject_count);
        }

        // Get student's current subject assignments
        $sql_assigned = "
            SELECT id_predmet, id_ucitelj
            FROM ucenec_predmet
            WHERE id_ucenec = ?
        ";
        $stmt_assigned = $pdo->prepare($sql_assigned);
        $stmt_assigned->execute([$id_ucenec]);
        $assigned_subjects = $stmt_assigned->fetchAll(PDO::FETCH_ASSOC);

        // Ensure we always return a valid response, even if empty
        $response = [
            'success' => true,
            'subjects' => $all_subjects ?: [],
            'assigned_subjects' => $assigned_subjects ?: []
        ];
        
        // Debug info in development
        if (empty($all_subjects)) {
            $response['debug'] = [
                'subjects_count' => count($all_subjects),
                'message' => 'No subjects with teachers found. Check if teachers are assigned to subjects in ucitelj_predmet table.'
            ];
        }
        
        echo json_encode($response);
        exit;
    }

    // 3. HANDLE ASSIGN/DELETE REQUEST (POST)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents("php://input"), true);
        
        $id_ucenec = (int)($data['id_ucenec'] ?? 0);
        $id_predmet = (int)($data['id_predmet'] ?? 0);
        $id_ucitelj = (int)($data['id_ucitelj'] ?? 0);
        $action = $data['action'] ?? '';

        // Validation
        if (!$id_ucenec || !$id_predmet || !$id_ucitelj) {
            echo json_encode(['success' => false, 'message' => 'Manjkajo obvezni podatki (id_ucenec, id_predmet, id_ucitelj).']);
            exit;
        }

        if ($action !== 'add' && $action !== 'delete') {
            echo json_encode(['success' => false, 'message' => 'Neveljavna akcija.']);
            exit;
        }

        // Verify that the teacher is assigned to this subject
        $sql_verify = "SELECT 1 FROM ucitelj_predmet WHERE id_ucitelj = ? AND id_predmet = ?";
        $stmt_verify = $pdo->prepare($sql_verify);
        $stmt_verify->execute([$id_ucitelj, $id_predmet]);
        if (!$stmt_verify->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Učitelj ni dodeljen temu predmetu.']);
            exit;
        }

        if ($action === 'add') {
            // Check if already assigned
            $sql_check = "SELECT 1 FROM ucenec_predmet WHERE id_ucenec = ? AND id_predmet = ? AND id_ucitelj = ?";
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->execute([$id_ucenec, $id_predmet, $id_ucitelj]);
            if ($stmt_check->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Predmet je že dodeljen temu učencu z istim učiteljem.']);
                exit;
            }

            // Insert new assignment
            $sql_insert = "INSERT INTO ucenec_predmet (id_ucenec, id_predmet, id_ucitelj) VALUES (?, ?, ?)";
            $stmt_insert = $pdo->prepare($sql_insert);
            $stmt_insert->execute([$id_ucenec, $id_predmet, $id_ucitelj]);
            
            echo json_encode(['success' => true, 'message' => 'Predmet uspešno dodeljen učencu.']);
        } else {
            // Delete assignment
            $sql_delete = "DELETE FROM ucenec_predmet WHERE id_ucenec = ? AND id_predmet = ? AND id_ucitelj = ?";
            $stmt_delete = $pdo->prepare($sql_delete);
            $stmt_delete->execute([$id_ucenec, $id_predmet, $id_ucitelj]);
            
            if ($stmt_delete->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Predmet uspešno odstranjen od učenca.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Predmet ni bil najden v dodelitvah učenca.']);
            }
        }
        exit;
    }

    // If neither GET fetch nor POST action, return error
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Neveljavna zahteva.']);

} catch (\PDOException $e) {
    http_response_code(500);
    error_log("Database error in admin_ajax_assign_subject: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Napaka baze: ' . $e->getMessage()]);
} catch (\Exception $e) {
    http_response_code(500);
    error_log("General error in admin_ajax_assign_subject: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Prišlo je do splošne napake.']);
}
?>

