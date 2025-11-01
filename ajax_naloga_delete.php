<?php
session_start();
require_once 'povezava.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['vloga'] === 'ucenec') {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Dostop zavrnjen."]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$id_naloga = $data['id_naloga'] ?? null;
$user_id = $_SESSION['user_id'];

if (empty($id_naloga)) {
    echo json_encode(["success" => false, "message" => "Manjka ID naloge."]);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Pridobitev poti datotek (učitelj) in oddaj (učenci) za brisanje
    $sql_naloga = "SELECT pot_na_strezniku FROM naloga WHERE id_naloga = ? AND id_ucitelj = ?";
    $stmt_naloga = $pdo->prepare($sql_naloga);
    $stmt_naloga->execute([$id_naloga, $user_id]);
    $naloga_data = $stmt_naloga->fetch();

    if (!$naloga_data) {
        $pdo->rollBack();
        echo json_encode(["success" => false, "message" => "Naloga ne obstaja ali je ne smete izbrisati."]);
        exit;
    }

    $sql_oddaje = "SELECT pot_na_strezniku FROM oddaja WHERE id_naloga = ?";
    $stmt_oddaje = $pdo->prepare($sql_oddaje);
    $stmt_oddaje->execute([$id_naloga]);
    $oddaje_data = $stmt_oddaje->fetchAll();

    // 2. Brisanje vseh povezanih oddaj
    $sql_delete_oddaje = "DELETE FROM oddaja WHERE id_naloga = ?";
    $stmt_delete_oddaje = $pdo->prepare($sql_delete_oddaje);
    $stmt_delete_oddaje->execute([$id_naloga]);

    // 3. Brisanje naloge
    $sql_delete_naloga = "DELETE FROM naloga WHERE id_naloga = ? AND id_ucitelj = ?";
    $stmt_delete_naloga = $pdo->prepare($sql_delete_naloga);
    $stmt_delete_naloga->execute([$id_naloga, $user_id]);
    
    // 4. Brisanje datotek iz datotečnega sistema (tiho brisanje @)
    if ($naloga_data['pot_na_strezniku'] && file_exists($naloga_data['pot_na_strezniku'])) {
        @unlink($naloga_data['pot_na_strezniku']);
    }
    foreach ($oddaje_data as $oddaja) {
        if ($oddaja['pot_na_strezniku'] && file_exists($oddaja['pot_na_strezniku'])) {
            @unlink($oddaja['pot_na_strezniku']);
        }
    }

    $pdo->commit();
    echo json_encode(["success" => true, "message" => "Naloga in vse povezane oddaje so uspešno izbrisane!"]);

} catch (\PDOException $e) {
    $pdo->rollBack();
    error_log("Naloga delete error: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Napaka baze: Brisanje naloge ni uspelo."]);
}
?>