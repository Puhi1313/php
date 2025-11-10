<?php
session_start();
require_once 'povezava.php';

// Security: Admin only
if (!isset($_SESSION['user_id']) || $_SESSION['vloga'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$id_ucitelj = (int)($_GET['id'] ?? 0);
if (!$id_ucitelj) {
    header('Location: adminPage.php');
    exit();
}

$ucitelj = null;
$predmeti = [];
$naloge = [];
$error_message = '';

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get teacher info
    $stmt = $pdo->prepare("SELECT * FROM uporabnik WHERE id_uporabnik = ? AND vloga = 'ucitelj'");
    $stmt->execute([$id_ucitelj]);
    $ucitelj = $stmt->fetch();
    
    if (!$ucitelj) {
        $error_message = "Učitelj ni najden.";
    } else {
        // Get assigned subjects
        $stmt = $pdo->prepare("
            SELECT p.id_predmet, p.ime_predmeta
            FROM ucitelj_predmet up
            JOIN predmet p ON up.id_predmet = p.id_predmet
            WHERE up.id_ucitelj = ?
            ORDER BY p.ime_predmeta ASC
        ");
        $stmt->execute([$id_ucitelj]);
        $predmeti = $stmt->fetchAll();
        
        // Get assignments created by this teacher
        $stmt = $pdo->prepare("
            SELECT n.id_naloga, n.naslov, n.datum_objave, n.rok_oddaje, p.ime_predmeta
            FROM naloga n
            JOIN predmet p ON n.id_predmet = p.id_predmet
            WHERE n.id_ucitelj = ?
            ORDER BY n.datum_objave DESC
        ");
        $stmt->execute([$id_ucitelj]);
        $naloge = $stmt->fetchAll();
    }
} catch (\PDOException $e) {
    $error_message = "Napaka pri pridobivanju podatkov: " . $e->getMessage();
    error_log("Teacher details error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Podrobnosti Učitelja - Admin</title>
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
        
        .btn { 
            padding: 8px 12px; 
            border: none; 
            cursor: pointer; 
            border-radius: 6px; 
            margin-right: 5px; 
            text-decoration: none; 
            display: inline-block; 
            font-family: "Raleway", sans-serif;
            font-weight: 500;
            transition: background 0.3s, transform 0.15s ease, box-shadow 0.15s ease;
        }
        .btn:hover { 
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .btn-blue { background-color: #80852f; color: white; }
        .btn-blue:hover { background-color: #6a6f26; }
        .btn-green { background-color: #6b8c7d; color: white; }
        .btn-green:hover { background-color: #5a7568; }
        .btn-red { background-color: #a85d4a; color: white; }
        .btn-red:hover { background-color: #8f4d3d; }
        
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
            display: flex; 
            align-items: center; 
            gap: 10px; 
            border: 1px solid #cdcdb6;
            transition: background 0.2s, border-color 0.2s, transform 0.15s ease, box-shadow 0.15s ease;
        }
        .subject-item:hover {
            background: #d0e7f7;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        }
        .subject-item button { 
            background: #a85d4a; 
            color: white; 
            border: none; 
            padding: 4px 8px; 
            border-radius: 6px; 
            cursor: pointer; 
            font-size: 12px; 
            font-family: "Raleway", sans-serif;
            transition: background 0.3s, transform 0.15s ease;
        }
        .subject-item button:hover {
            background: #8f4d3d;
            transform: translateY(-1px);
        }
        
        .add-subject-form { 
            margin-top: 20px; 
            padding: 15px; 
            background: #f8f8f0; 
            border-radius: 10px; 
            border: 1px solid #cdcdb6;
        }
        .add-subject-form h3 {
            margin-top: 0;
            color: #596235;
        }
        .form-group { margin-bottom: 15px; }
        .form-group label { 
            display: block; 
            margin-bottom: 5px; 
            font-weight: 600; 
            color: #596235;
        }
        .form-group select { 
            width: 100%; 
            padding: 10px; 
            border: 1px solid #cdcdb6; 
            border-radius: 8px; 
            font-family: "Raleway", sans-serif;
            color: #596235;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-group select:focus {
            outline: none;
            border-color: #80852f;
            box-shadow: 0 0 0 3px rgba(128, 133, 47, 0.1);
        }
        
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
    <h1>Podrobnosti Učitelja</h1>
    <nav>
        <a href="adminPage.php">← Nazaj na Admin Panel</a>
    </nav>
</div>

<div class="container">
    <?php if ($error_message): ?>
        <div class="error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php elseif ($ucitelj): ?>
        
        <!-- Teacher Profile -->
        <div class="profile-section">
            <h2>Profil Učitelja</h2>
            <div class="profile-info">
                <?php if (!empty($ucitelj['icona_profila']) && file_exists(__DIR__ . DIRECTORY_SEPARATOR . $ucitelj['icona_profila'])): ?>
                    <img src="<?php echo htmlspecialchars($ucitelj['icona_profila']); ?>" class="profile-pic" alt="Profilna slika">
                <?php else: ?>
                    <div class="profile-pic" style="background: #3f51b5; color: white; display: flex; align-items: center; justify-content: center; font-size: 2em; font-weight: bold;">
                        <?php echo strtoupper(substr($ucitelj['ime'], 0, 1) . substr($ucitelj['priimek'], 0, 1)); ?>
                    </div>
                <?php endif; ?>
                <div>
                    <h3><?php echo htmlspecialchars($ucitelj['ime'] . ' ' . $ucitelj['priimek']); ?></h3>
                    <p><strong>E-mail:</strong> <?php echo htmlspecialchars($ucitelj['email']); ?></p>
                    <p><strong>Status:</strong> <?php echo htmlspecialchars($ucitelj['status']); ?></p>
                </div>
            </div>
        </div>

        <!-- Assigned Subjects -->
        <div class="profile-section">
            <h2>Dodeljeni Predmeti</h2>
            <div id="subjects-container">
                <?php if (empty($predmeti)): ?>
                    <p>Ta učitelj nima dodeljenih predmetov.</p>
                <?php else: ?>
                    <div class="subject-list">
                        <?php foreach ($predmeti as $predmet): ?>
                            <div class="subject-item" data-id="<?php echo $predmet['id_predmet']; ?>">
                                <span><?php echo htmlspecialchars($predmet['ime_predmeta']); ?></span>
                                <button onclick="removeSubject(<?php echo $predmet['id_predmet']; ?>, '<?php echo htmlspecialchars($predmet['ime_predmeta']); ?>')">Odstrani</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Add Subject Form -->
            <div class="add-subject-form">
                <h3>Dodaj Nov Predmet</h3>
                <div class="form-group">
                    <label for="new_subject">Izberi predmet:</label>
                    <select id="new_subject">
                        <option value="">-- Izberi predmet --</option>
                        <!-- Will be populated by JavaScript -->
                    </select>
                </div>
                <button class="btn btn-green" onclick="addSubject()">Dodaj Predmet</button>
                <div id="subject-message" style="margin-top: 10px; font-weight: bold;"></div>
            </div>
        </div>

        <!-- Assignments -->
        <div class="profile-section">
            <h2>Naloge (<?php echo count($naloge); ?>)</h2>
            <?php if (empty($naloge)): ?>
                <p>Ta učitelj še ni objavil nobenih nalog.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Naslov</th>
                            <th>Predmet</th>
                            <th>Datum Objave</th>
                            <th>Rok Oddaje</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($naloge as $naloga): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($naloga['naslov']); ?></td>
                                <td><?php echo htmlspecialchars($naloga['ime_predmeta']); ?></td>
                                <td><?php echo date('d.m.Y H:i', strtotime($naloga['datum_objave'])); ?></td>
                                <td><?php echo date('d.m.Y H:i', strtotime($naloga['rok_oddaje'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    <?php endif; ?>
</div>

<script>
    const teacherId = <?php echo $id_ucitelj; ?>;
    let allSubjects = [];
    let assignedSubjectIds = <?php echo json_encode(array_column($predmeti, 'id_predmet')); ?>;

    // Load all available subjects
    async function loadAllSubjects() {
        try {
            const response = await fetch('admin_ajax_fetch_subjects.php?id_ucitelj=' + teacherId);
            const result = await response.json();
            
            if (result.success) {
                allSubjects = result.all_subjects;
                const select = document.getElementById('new_subject');
                select.innerHTML = '<option value="">-- Izberi predmet --</option>';
                
                result.all_subjects.forEach(subject => {
                    if (!assignedSubjectIds.includes(subject.id_predmet.toString())) {
                        const option = document.createElement('option');
                        option.value = subject.id_predmet;
                        option.textContent = subject.ime_predmeta;
                        select.appendChild(option);
                    }
                });
            }
        } catch (error) {
            console.error('Error loading subjects:', error);
        }
    }

    // Add subject
    async function addSubject() {
        const subjectId = document.getElementById('new_subject').value;
        if (!subjectId) {
            alert('Izberite predmet!');
            return;
        }

        try {
            const response = await fetch('admin_ajax_manage_teacher_subjects.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id_ucitelj: teacherId,
                    id_predmet: subjectId,
                    action: 'add'
                })
            });
            const result = await response.json();

            const messageDiv = document.getElementById('subject-message');
            if (result.success) {
                messageDiv.textContent = result.message;
                messageDiv.style.color = 'green';
                location.reload(); // Reload to show new subject
            } else {
                messageDiv.textContent = 'Napaka: ' + result.message;
                messageDiv.style.color = 'red';
            }
        } catch (error) {
            alert('Napaka pri komunikaciji s strežnikom.');
        }
    }

    // Remove subject
    async function removeSubject(subjectId, subjectName) {
        if (!confirm('Ste prepričani, da želite odstraniti predmet "' + subjectName + '"?')) {
            return;
        }

        try {
            const response = await fetch('admin_ajax_manage_teacher_subjects.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id_ucitelj: teacherId,
                    id_predmet: subjectId,
                    action: 'delete'
                })
            });
            const result = await response.json();

            if (result.success) {
                alert(result.message);
                location.reload();
            } else {
                alert('Napaka: ' + result.message);
            }
        } catch (error) {
            alert('Napaka pri komunikaciji s strežnikom.');
        }
    }

    // Load subjects on page load
    document.addEventListener('DOMContentLoaded', loadAllSubjects);
</script>

</body>
</html>

