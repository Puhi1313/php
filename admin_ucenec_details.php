<?php
session_start();
require_once 'povezava.php';

// Security: Admin only
if (!isset($_SESSION['user_id']) || $_SESSION['vloga'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$id_ucenec = (int)($_GET['id'] ?? 0);
if (!$id_ucenec) {
    header('Location: adminPage.php');
    exit();
}

$ucenec = null;
$predmeti = [];
$naloge_oddaje = [];
$error_message = '';

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get student info
    $stmt = $pdo->prepare("SELECT * FROM uporabnik WHERE id_uporabnik = ? AND vloga = 'ucenec'");
    $stmt->execute([$id_ucenec]);
    $ucenec = $stmt->fetch();
    
    if (!$ucenec) {
        $error_message = "Učenec ni najden.";
    } else {
        // Get enrolled subjects
        $stmt = $pdo->prepare("
            SELECT p.id_predmet, p.ime_predmeta, u.ime AS ime_ucitelja, u.priimek AS priimek_ucitelja
            FROM ucenec_predmet up
            JOIN predmet p ON up.id_predmet = p.id_predmet
            JOIN uporabnik u ON up.id_ucitelj = u.id_uporabnik
            WHERE up.id_ucenec = ?
            ORDER BY p.ime_predmeta ASC
        ");
        $stmt->execute([$id_ucenec]);
        $predmeti = $stmt->fetchAll();
        
        // Get assignments with submission status and grades
        $stmt = $pdo->prepare("
            SELECT 
                n.id_naloga,
                n.naslov AS naslov_naloge,
                n.rok_oddaje,
                p.ime_predmeta,
                o.id_oddaja,
                o.datum_oddaje,
                o.status AS status_oddaje,
                o.ocena,
                o.komentar_ucitelj
            FROM ucenec_predmet up
            JOIN predmet p ON up.id_predmet = p.id_predmet
            JOIN naloga n ON n.id_predmet = p.id_predmet
            LEFT JOIN oddaja o ON o.id_naloga = n.id_naloga AND o.id_ucenec = ?
            WHERE up.id_ucenec = ?
            ORDER BY n.rok_oddaje DESC
        ");
        $stmt->execute([$id_ucenec, $id_ucenec]);
        $naloge_oddaje = $stmt->fetchAll();
    }
} catch (\PDOException $e) {
    $error_message = "Napaka pri pridobivanju podatkov: " . $e->getMessage();
    error_log("Student details error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Podrobnosti Učenca - Admin</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f7f6; }
        .header { background-color: #3f51b5; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .header a { color: white; text-decoration: none; margin-left: 20px; font-weight: bold; }
        .container { padding: 20px; max-width: 1200px; margin: 0 auto; }
        
        .profile-section { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .profile-section h2 { margin-top: 0; color: #3f51b5; }
        .profile-info { display: flex; gap: 20px; align-items: center; }
        .profile-pic { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid #3f51b5; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #e0e0e0; font-weight: bold; }
        tr:hover { background-color: #f1f1f1; }
        
        .subject-list { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; }
        .subject-item { background: #e3f2fd; padding: 8px 15px; border-radius: 20px; }
        
        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .status-oddano { background-color: #4CAF50; color: white; }
        .status-ocenjeno { background-color: #2196F3; color: white; }
        .status-manjkajoce { background-color: #ff9800; color: white; }
        .status-zamujeno { background-color: #f44336; color: white; }
        
        .grade { font-weight: bold; font-size: 1.1em; }
        .grade-excellent { color: #4CAF50; }
        .grade-good { color: #2196F3; }
        .grade-average { color: #ff9800; }
        .grade-poor { color: #f44336; }
        
        .error { color: red; padding: 10px; background: #ffebee; border-radius: 4px; margin-bottom: 20px; }
    </style>
</head>
<body>

<div class="header">
    <h1>Podrobnosti Učenca</h1>
    <nav>
        <a href="adminPage.php">← Nazaj na Admin Panel</a>
    </nav>
</div>

<div class="container">
    <?php if ($error_message): ?>
        <div class="error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php elseif ($ucenec): ?>
        
        <!-- Student Profile -->
        <div class="profile-section">
            <h2>Profil Učenca</h2>
            <div class="profile-info">
                <?php if (!empty($ucenec['icona_profila']) && file_exists(__DIR__ . DIRECTORY_SEPARATOR . $ucenec['icona_profila'])): ?>
                    <img src="<?php echo htmlspecialchars($ucenec['icona_profila']); ?>" class="profile-pic" alt="Profilna slika">
                <?php else: ?>
                    <div class="profile-pic" style="background: #3f51b5; color: white; display: flex; align-items: center; justify-content: center; font-size: 2em; font-weight: bold;">
                        <?php echo strtoupper(substr($ucenec['ime'], 0, 1) . substr($ucenec['priimek'], 0, 1)); ?>
                    </div>
                <?php endif; ?>
                <div>
                    <h3><?php echo htmlspecialchars($ucenec['ime'] . ' ' . $ucenec['priimek']); ?></h3>
                    <p><strong>E-mail:</strong> <?php echo htmlspecialchars($ucenec['email']); ?></p>
                    <p><strong>Status:</strong> <?php echo htmlspecialchars($ucenec['status']); ?></p>
                    <p><strong>Prvi vpis:</strong> <?php echo $ucenec['prvi_vpis'] ? 'DA' : 'NE'; ?></p>
                </div>
            </div>
        </div>

        <!-- Enrolled Subjects -->
        <div class="profile-section">
            <h2>Vpisan v Predmete (<?php echo count($predmeti); ?>)</h2>
            <?php if (empty($predmeti)): ?>
                <p>Ta učenec ni vpisan v noben predmet.</p>
            <?php else: ?>
                <div class="subject-list">
                    <?php foreach ($predmeti as $predmet): ?>
                        <div class="subject-item">
                            <strong><?php echo htmlspecialchars($predmet['ime_predmeta']); ?></strong>
                            <br>
                            <small>Učitelj: <?php echo htmlspecialchars($predmet['ime_ucitelja'] . ' ' . $predmet['priimek_ucitelja']); ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Assignments and Submissions -->
        <div class="profile-section">
            <h2>Naloge in Oddaje (<?php echo count($naloge_oddaje); ?>)</h2>
            <?php if (empty($naloge_oddaje)): ?>
                <p>Ta učenec nima nalog za svoje predmete.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Naloga</th>
                            <th>Predmet</th>
                            <th>Rok Oddaje</th>
                            <th>Status</th>
                            <th>Datum Oddaje</th>
                            <th>Ocena</th>
                            <th>Komentar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($naloge_oddaje as $naloga): ?>
                            <?php
                            $now = new DateTime();
                            $rok = new DateTime($naloga['rok_oddaje']);
                            $isOverdue = $now > $rok && !$naloga['id_oddaja'];
                            $status = 'Manjkajoče';
                            $statusClass = 'status-manjkajoce';
                            
                            if ($naloga['id_oddaja']) {
                                if ($naloga['ocena'] !== null) {
                                    $status = 'Ocenjeno';
                                    $statusClass = 'status-ocenjeno';
                                } else {
                                    $status = 'Oddano';
                                    $statusClass = 'status-oddano';
                                }
                            } elseif ($isOverdue) {
                                $status = 'Zamujeno';
                                $statusClass = 'status-zamujeno';
                            }
                            
                            $gradeClass = '';
                            if ($naloga['ocena'] !== null) {
                                if ($naloga['ocena'] >= 5) {
                                    $gradeClass = 'grade-excellent';
                                } elseif ($naloga['ocena'] >= 4) {
                                    $gradeClass = 'grade-good';
                                } elseif ($naloga['ocena'] >= 3) {
                                    $gradeClass = 'grade-average';
                                } else {
                                    $gradeClass = 'grade-poor';
                                }
                            }
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($naloga['naslov_naloge']); ?></td>
                                <td><?php echo htmlspecialchars($naloga['ime_predmeta']); ?></td>
                                <td><?php echo date('d.m.Y H:i', strtotime($naloga['rok_oddaje'])); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $statusClass; ?>">
                                        <?php echo htmlspecialchars($status); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($naloga['datum_oddaje']): ?>
                                        <?php echo date('d.m.Y H:i', strtotime($naloga['datum_oddaje'])); ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($naloga['ocena'] !== null): ?>
                                        <span class="grade <?php echo $gradeClass; ?>">
                                            <?php echo htmlspecialchars($naloga['ocena']); ?>
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($naloga['komentar_ucitelj']): ?>
                                        <?php echo htmlspecialchars($naloga['komentar_ucitelj']); ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    <?php endif; ?>
</div>

</body>
</html>

