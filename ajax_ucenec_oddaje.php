<?php
session_start();
require_once 'povezava.php';
header('Content-Type: text/html; charset=utf-8');

// Preverjanje prijave in vloge učitelja
if (!isset($_SESSION['user_id']) || $_SESSION['vloga'] === 'ucenec') {
    http_response_code(403);
    die("Dostop zavrnjen. Niste učitelj.");
}

$data = json_decode(file_get_contents("php://input"), true);
$id_ucenec = $data['id_ucenec'] ?? null;
$id_ucitelja = $data['id_ucitelja'] ?? $_SESSION['user_id'];

if (empty($id_ucenec)) {
    die("Manjka ID učenca.");
}

try {
    // Pridobi vse oddaje izbranega učenca, ki so povezane s tem učiteljem
    $sql_oddaje = "
        SELECT 
            o.id_oddaja, o.datum_oddaje, o.ocena, o.status, o.besedilo_oddaje, o.pot_na_strezniku,
            n.naslov AS naloga_naslov, n.rok_oddaje,
            p.ime_predmeta
        FROM oddaja o
        JOIN naloga n ON o.id_naloga = n.id_naloga
        JOIN predmet p ON n.id_predmet = p.id_predmet
        WHERE o.id_ucenec = ? AND n.id_ucitelj = ?
        ORDER BY o.datum_oddaje DESC
    ";
    $stmt_oddaje = $pdo->prepare($sql_oddaje);
    $stmt_oddaje->execute([$id_ucenec, $id_ucitelja]);
    $oddaje = $stmt_oddaje->fetchAll();

    if (empty($oddaje)) {
        echo "<p>Učenec še ni oddal nobene naloge pri vas.</p>";
        exit;
    }

    echo "<h4>Seznam Oddaj:</h4>";
    echo "<table style='width: 100%; border-collapse: collapse;'>";
    echo "<thead><tr><th>Naloga</th><th>Predmet</th><th>Oddano</th><th>Rok Oddaje</th><th>Status</th><th>Ocena</th><th>Akcija</th></tr></thead>";
    echo "<tbody>";

    foreach ($oddaje as $oddaja) {
        $status_barva = '#999';
        $status_tekst = 'Čaka na oceno';
        
        if ($oddaja['ocena']) {
            $status_tekst = 'Ocenjeno';
            $status_barva = 'green';
            if (strtoupper($oddaja['ocena']) === 'ND') {
                $status_tekst = 'ND - Za dopolnitev';
                $status_barva = 'orange';
            }
        }

        echo "<tr>";
        echo "<td>" . htmlspecialchars($oddaja['naloga_naslov']) . "</td>";
        echo "<td>" . htmlspecialchars($oddaja['ime_predmeta']) . "</td>";
        echo "<td>" . date('d.m.Y H:i', strtotime($oddaja['datum_oddaje'])) . "</td>";
        echo "<td>" . date('d.m.Y H:i', strtotime($oddaja['rok_oddaje'])) . "</td>";
        echo "<td style='font-weight: bold; color: $status_barva;'>" . $status_tekst . "</td>";
        echo "<td style='font-weight: bold;'>" . htmlspecialchars($oddaja['ocena'] ?? '-') . "</td>";
        echo "<td>";
        echo "<button 
                data-oddaja-id='" . $oddaja['id_oddaja'] . "' 
                data-ucenec-ime='(Za osvežitev)' 
                class='pregled-oddaje-btn'
                style='background: #1c4587; color: white; border: none; padding: 5px 10px; cursor: pointer;'>
                Pregled/Oceni
            </button>";
        echo "</td>";
        echo "</tr>";
    }

    echo "</tbody></table>";

} catch (\PDOException $e) {
    http_response_code(500);
    error_log("Database Error (Oddaje Učenca): " . $e->getMessage());
    echo "<p style='color: red;'>Napaka pri nalaganju podatkov: " . $e->getMessage() . "</p>";
}
?>