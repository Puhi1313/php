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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Momo+Trust+Display&family=Raleway:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <style>
        html { color: #596235; }
        body { 
            margin: 0; 
            padding: 0; 
            font-family: "Raleway", sans-serif;
            background:
                radial-gradient(900px 500px at 10% -10%, rgba(205, 205, 182, 0.65), rgba(205, 205, 182, 0) 70%),
                radial-gradient(900px 500px at 110% 10%, rgba(128, 133, 47, 0.18), rgba(128, 133, 47, 0) 60%),
                linear-gradient(180deg, #f7f8f3 0%, #eff1e4 45%, #e3e6d1 100%);
            background-attachment: fixed;
            color: #596235;
        }
        .header { 
            background: #cdcdb6; 
            color: #596235; 
            padding: 15px 20px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .header h1 { margin: 0; font-size: 24px; font-weight: bold; }
        .header a { 
            color: #596235; 
            text-decoration: none; 
            margin-left: 20px; 
            font-weight: 500;
            transition: text-decoration 0.2s;
        }
        .header a:hover { text-decoration: underline; }
        .container { 
            padding: 20px; 
            max-width: 1200px; 
            margin: 20px auto; 
        }
        
        .profile-section { 
            background: #fff; 
            padding: 20px; 
            border-radius: 16px; 
            margin-bottom: 20px; 
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            border: 1px solid #cdcdb6;
        }
        .profile-section h2 { 
            margin-top: 0; 
            color: #596235; 
            border-bottom: 2px solid #cdcdb6;
            padding-bottom: 10px;
        }
        .profile-info { display: flex; gap: 20px; align-items: center; }
        .profile-pic { 
            width: 100px; 
            height: 100px; 
            border-radius: 50%; 
            object-fit: cover; 
            border: 3px solid #cdcdb6; 
        }
        
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 15px; 
        }
        th, td { 
            padding: 12px 15px; 
            text-align: left; 
            border-bottom: 1px solid #ddd; 
        }
        th { 
            background-color: #f8f8f0; 
            font-weight: 600; 
            color: #596235;
        }
        tr { 
            transition: background 0.2s, transform 0.15s ease, box-shadow 0.15s ease;
        }
        tr:hover { 
            background-color: #e6e6fa; 
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        
        .subject-list { 
            display: flex; 
            flex-wrap: wrap; 
            gap: 10px; 
            margin-top: 10px; 
        }
        .subject-item { 
            background: #e3f2fd; 
            padding: 10px 15px; 
            border-radius: 20px; 
            border: 1px solid #cdcdb6;
            transition: background 0.2s, border-color 0.2s, transform 0.15s ease, box-shadow 0.15s ease;
        }
        .subject-item:hover {
            background: #d0e7f7;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        }
        
        .status-badge { 
            padding: 6px 12px; 
            border-radius: 15px; 
            font-size: 12px; 
            font-weight: 600; 
            display: inline-block;
        }
        .status-oddano { background-color: #6b8c7d; color: white; }
        .status-ocenjeno { background-color: #80852f; color: white; }
        .status-manjkajoce { background-color: #d4a574; color: #596235; }
        .status-zamujeno { background-color: #a85d4a; color: white; }
        
        .grade { 
            font-weight: bold; 
            font-size: 1.1em; 
        }
        .grade-excellent { color: #6b8c7d; }
        .grade-good { color: #80852f; }
        .grade-average { color: #d4a574; }
        .grade-poor { color: #a85d4a; }
        
        .error { 
            color: #a85d4a; 
            padding: 15px; 
            background: #ffebee; 
            border-radius: 10px; 
            margin-bottom: 20px; 
            border: 1px solid #f44336;
        }
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

