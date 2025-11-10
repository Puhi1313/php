<?php
session_start();
require_once 'povezava.php';

// Security: Admin only
if (!isset($_SESSION['user_id']) || $_SESSION['vloga'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$id_user = (int)($_GET['id'] ?? 0);
if (!$id_user) {
    header('Location: adminPage.php');
    exit();
}

$user = null;
$predmeti = [];
$naloge = [];
$ucenci = []; // Students enrolled in teacher's subjects
$error_message = '';
$is_student = false;

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get user info (teacher or student)
    $stmt = $pdo->prepare("SELECT * FROM uporabnik WHERE id_uporabnik = ? AND (vloga = 'ucitelj' OR vloga = 'ucenec')");
    $stmt->execute([$id_user]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $error_message = "Uporabnik ni najden.";
    } else {
        $is_student = ($user['vloga'] === 'ucenec');
        
        if ($is_student) {
            // Get enrolled subjects for student (with teacher info)
            $stmt = $pdo->prepare("
                SELECT 
                    p.id_predmet, 
                    p.ime_predmeta,
                    up.id_ucitelj,
                    u.ime AS ime_ucitelja,
                    u.priimek AS priimek_ucitelja
                FROM ucenec_predmet up
                JOIN predmet p ON up.id_predmet = p.id_predmet
                LEFT JOIN uporabnik u ON up.id_ucitelj = u.id_uporabnik
                WHERE up.id_ucenec = ?
                ORDER BY p.ime_predmeta ASC
            ");
            $stmt->execute([$id_user]);
            $predmeti = $stmt->fetchAll();
            
            // Get assignments for student
            $stmt = $pdo->prepare("
                SELECT 
                    n.id_naloga, 
                    n.naslov, 
                    n.datum_objave, 
                    n.rok_oddaje, 
                    p.ime_predmeta,
                    o.id_oddaja,
                    o.status AS status_oddaje,
                    o.ocena
                FROM ucenec_predmet up
                JOIN predmet p ON up.id_predmet = p.id_predmet
                JOIN naloga n ON n.id_predmet = p.id_predmet
                LEFT JOIN oddaja o ON o.id_naloga = n.id_naloga AND o.id_ucenec = ?
                WHERE up.id_ucenec = ?
                ORDER BY n.datum_objave DESC
            ");
            $stmt->execute([$id_user, $id_user]);
            $naloge = $stmt->fetchAll();
        } else {
            // Get assigned subjects for teacher
            $stmt = $pdo->prepare("
                SELECT p.id_predmet, p.ime_predmeta
                FROM ucitelj_predmet up
                JOIN predmet p ON up.id_predmet = p.id_predmet
                WHERE up.id_ucitelj = ?
                ORDER BY p.ime_predmeta ASC
            ");
            $stmt->execute([$id_user]);
            $predmeti = $stmt->fetchAll();
            
            // Get assignments created by this teacher
            $stmt = $pdo->prepare("
                SELECT n.id_naloga, n.naslov, n.datum_objave, n.rok_oddaje, p.ime_predmeta
                FROM naloga n
                JOIN predmet p ON n.id_predmet = p.id_predmet
                WHERE n.id_ucitelj = ?
                ORDER BY n.datum_objave DESC
            ");
            $stmt->execute([$id_user]);
            $naloge = $stmt->fetchAll();
            
            // Get all students enrolled in this teacher's subjects
            $stmt = $pdo->prepare("
                SELECT DISTINCT
                    u.id_uporabnik,
                    u.ime,
                    u.priimek,
                    u.email,
                    u.status,
                    GROUP_CONCAT(DISTINCT p.ime_predmeta ORDER BY p.ime_predmeta SEPARATOR ', ') AS predmeti
                FROM ucenec_predmet up
                JOIN uporabnik u ON up.id_ucenec = u.id_uporabnik
                JOIN predmet p ON up.id_predmet = p.id_predmet
                WHERE up.id_ucitelj = ? AND u.vloga = 'ucenec'
                GROUP BY u.id_uporabnik, u.ime, u.priimek, u.email, u.status
                ORDER BY u.priimek ASC, u.ime ASC
            ");
            $stmt->execute([$id_user]);
            $ucenci = $stmt->fetchAll();
        }
    }
} catch (\PDOException $e) {
    $error_message = "Napaka pri pridobivanju podatkov: " . $e->getMessage();
    error_log("User details error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Podrobnosti <?php echo $is_student ? 'Učenca' : 'Učitelja'; ?> - Admin</title>
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
            margin-left: 10px;
        }
        .subject-item button:hover {
            background: #8f4d3d;
            transform: translateY(-1px);
        }
        .subject-item .teacher-info {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
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
        
        /* TABS */
        .tab-menu { 
            display: flex; 
            border-bottom: 2px solid #cdcdb6; 
            margin-bottom: 20px; 
            gap: 5px;
        }
        .tab-button { 
            padding: 12px 20px; 
            cursor: pointer; 
            border: 1px solid #cdcdb6;
            border-bottom: none;
            background: #f8f8f0;
            border-radius: 10px 10px 0 0;
            color: #596235;
            transition: background 0.3s, transform 0.15s ease;
            font-family: "Raleway", sans-serif;
            font-weight: 500;
        }
        .tab-button:hover { 
            background: #e6e6fa;
            transform: translateY(-1px);
        }
        .tab-button.active { 
            background: #fff; 
            border-color: #cdcdb6; 
            border-bottom: 2px solid #fff; 
            font-weight: 600; 
            color: #596235;
        }
        .tab-content { 
            display: none;
            background-color: #fff; 
            padding: 20px; 
            border: 1px solid #cdcdb6; 
            border-top: none; 
            border-radius: 0 10px 10px 10px;
        }
        .tab-content.active { 
            display: block; 
        }
        
        /* Clickable student row */
        .student-row {
            cursor: pointer;
            transition: background 0.2s, transform 0.15s ease, box-shadow 0.15s ease;
        }
        .student-row:hover {
            background-color: #e6e6fa !important;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
    </style>
</head>
<body>

<div class="header">
    <h1>Podrobnosti <?php echo $is_student ? 'Učenca' : 'Učitelja'; ?></h1>
    <nav>
        <a href="adminPage.php">← Nazaj na Admin Panel</a>
    </nav>
</div>

<div class="container">
    <?php if ($error_message): ?>
        <div class="error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php elseif ($user): ?>
        
        <!-- User Profile -->
        <div class="profile-section">
            <h2>Profil <?php echo $is_student ? 'Učenca' : 'Učitelja'; ?></h2>
            <div class="profile-info">
                <?php if (!empty($user['icona_profila']) && file_exists(__DIR__ . DIRECTORY_SEPARATOR . $user['icona_profila'])): ?>
                    <img src="<?php echo htmlspecialchars($user['icona_profila']); ?>" class="profile-pic" alt="Profilna slika">
                <?php else: ?>
                    <div class="profile-pic" style="background: #3f51b5; color: white; display: flex; align-items: center; justify-content: center; font-size: 2em; font-weight: bold;">
                        <?php echo strtoupper(substr($user['ime'], 0, 1) . substr($user['priimek'], 0, 1)); ?>
                    </div>
                <?php endif; ?>
                <div>
                    <h3><?php echo htmlspecialchars($user['ime'] . ' ' . $user['priimek']); ?></h3>
                    <p><strong>E-mail:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                    <p><strong>Status:</strong> <?php echo htmlspecialchars($user['status']); ?></p>
                    <?php if ($is_student): ?>
                        <p><strong>Prvi vpis:</strong> <?php echo $user['prvi_vpis'] ? 'DA' : 'NE'; ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Subjects Section -->
        <div class="profile-section">
            <h2><?php echo $is_student ? 'Vpisan v Predmete' : 'Dodeljeni Predmeti'; ?></h2>
            <div id="subjects-container">
                <?php if (empty($predmeti)): ?>
                    <p>Ta <?php echo $is_student ? 'učenec ni vpisan v noben predmet' : 'učitelj nima dodeljenih predmetov'; ?>.</p>
                <?php else: ?>
                    <div class="subject-list">
                        <?php foreach ($predmeti as $predmet): ?>
                            <div class="subject-item" data-id="<?php echo $predmet['id_predmet']; ?>" 
                                 <?php if ($is_student): ?>data-id-ucitelj="<?php echo $predmet['id_ucitelj'] ?? ''; ?>"<?php endif; ?>>
                                <div>
                                    <strong><?php echo htmlspecialchars($predmet['ime_predmeta']); ?></strong>
                                    <?php if ($is_student && !empty($predmet['ime_ucitelja'])): ?>
                                        <div class="teacher-info">
                                            Učitelj: <?php echo htmlspecialchars($predmet['ime_ucitelja'] . ' ' . $predmet['priimek_ucitelja']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <button onclick="removeSubject(<?php echo $predmet['id_predmet']; ?>, '<?php echo htmlspecialchars($predmet['ime_predmeta']); ?>'<?php if ($is_student && !empty($predmet['id_ucitelj'])): ?>, <?php echo $predmet['id_ucitelj']; ?><?php endif; ?>)">Odstrani</button>
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
                <?php if ($is_student): ?>
                <div class="form-group">
                    <label for="new_teacher">Izberi učitelja:</label>
                    <select id="new_teacher" disabled>
                        <option value="">-- Najprej izberite predmet --</option>
                        <!-- Will be populated by JavaScript -->
                    </select>
                </div>
                <?php endif; ?>
                <button class="btn btn-green" onclick="addSubject()">Dodaj Predmet</button>
                <div id="subject-message" style="margin-top: 10px; font-weight: bold;"></div>
            </div>
        </div>

        <!-- Assignments / Students Tabs (only for teachers) -->
        <?php if (!$is_student): ?>
        <div class="profile-section">
            <div class="tab-menu">
                <button class="tab-button active" onclick="openTab(event, 'naloge-tab')">
                    Naloge (<?php echo count($naloge); ?>)
                </button>
                <button class="tab-button" onclick="openTab(event, 'ucenci-tab')">
                    Učenci (<?php echo count($ucenci); ?>)
                </button>
            </div>
            
            <!-- Naloge Tab -->
            <div id="naloge-tab" class="tab-content active">
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
            
            <!-- Učenci Tab -->
            <div id="ucenci-tab" class="tab-content">
                <h2>Učenci (<?php echo count($ucenci); ?>)</h2>
                <?php if (empty($ucenci)): ?>
                    <p>Ta učitelj še nima nobenih učencev v svojih predmetih.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Ime in Priimek</th>
                                <th>E-mail</th>
                                <th>Status</th>
                                <th>Predmeti</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ucenci as $ucenec): ?>
                                <tr class="student-row" onclick="window.location.href='admin_ucitelj_details.php?id=<?php echo $ucenec['id_uporabnik']; ?>'">
                                    <td><?php echo htmlspecialchars($ucenec['id_uporabnik']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($ucenec['ime'] . ' ' . $ucenec['priimek']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($ucenec['email']); ?></td>
                                    <td>
                                        <?php 
                                        $status_map = [
                                            'pending' => 'Čakanje',
                                            'active' => 'Aktiven',
                                            'rejected' => 'Zavrnjen',
                                            'cakanje' => 'Čakanje',
                                            'aktiven' => 'Aktiven',
                                            'blokiran' => 'Zavrnjen'
                                        ];
                                        $status_display = $status_map[$ucenec['status']] ?? htmlspecialchars($ucenec['status']);
                                        ?>
                                        <span style="color: <?php 
                                            echo $ucenec['status'] === 'active' ? '#28a745' : 
                                                ($ucenec['status'] === 'pending' ? '#ffc107' : '#dc3545'); 
                                        ?>;"><?php echo $status_display; ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($ucenec['predmeti']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <!-- For students, show assignments without tabs -->
        <div class="profile-section">
            <h2>Naloge in Oddaje (<?php echo count($naloge); ?>)</h2>
            <?php if (empty($naloge)): ?>
                <p>Ta učenec nima nalog.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Naslov</th>
                            <th>Predmet</th>
                            <th>Datum Objave</th>
                            <th>Rok Oddaje</th>
                            <th>Status</th>
                            <th>Ocena</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($naloge as $naloga): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($naloga['naslov']); ?></td>
                                <td><?php echo htmlspecialchars($naloga['ime_predmeta']); ?></td>
                                <td><?php echo date('d.m.Y H:i', strtotime($naloga['datum_objave'])); ?></td>
                                <td><?php echo date('d.m.Y H:i', strtotime($naloga['rok_oddaje'])); ?></td>
                                <td>
                                    <?php 
                                    if ($naloga['id_oddaja']): 
                                        echo htmlspecialchars($naloga['status_oddaje'] ?? 'Oddano');
                                    else:
                                        $now = new DateTime();
                                        $rok = new DateTime($naloga['rok_oddaje']);
                                        echo ($now > $rok) ? 'Zamujeno' : 'Manjkajoče';
                                    endif;
                                    ?>
                                </td>
                                <td><?php echo $naloga['ocena'] !== null ? htmlspecialchars($naloga['ocena']) : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<script>
    const userId = <?php echo $id_user; ?>;
    const isStudent = <?php echo $is_student ? 'true' : 'false'; ?>;
    let allSubjects = [];
    let allSubjectTeacherCombinations = [];
    let assignedSubjectIds = <?php echo json_encode(array_column($predmeti, 'id_predmet')); ?>;

    // Load all available subjects
    async function loadAllSubjects() {
        try {
            if (isStudent) {
                // For students, get all subject-teacher combinations
                const response = await fetch('admin_ajax_assign_subject.php?action=fetch&id_ucenec=' + userId);
                const result = await response.json();
                
                console.log('Student subjects response:', result); // Debug log
                
                if (result.success) {
                    const subjectSelect = document.getElementById('new_subject');
                    const teacherSelect = document.getElementById('new_teacher');
                    
                    // Show debug info if available
                    if (result.debug) {
                        console.warn('Debug info:', result.debug);
                    }
                    
                    if (result.subjects && result.subjects.length > 0) {
                        allSubjectTeacherCombinations = result.subjects;
                        
                        // Get unique subjects (all subjects that have teachers)
                        const uniqueSubjects = {};
                        result.subjects.forEach(combo => {
                            if (combo.id_predmet && combo.ime_predmeta) {
                                if (!uniqueSubjects[combo.id_predmet]) {
                                    uniqueSubjects[combo.id_predmet] = combo.ime_predmeta;
                                }
                            }
                        });
                        
                        subjectSelect.innerHTML = '<option value="">-- Izberi predmet --</option>';
                        Object.keys(uniqueSubjects).forEach(id => {
                            // Show all subjects (student can be enrolled in same subject with different teachers)
                            const option = document.createElement('option');
                            option.value = id;
                            option.textContent = uniqueSubjects[id];
                            subjectSelect.appendChild(option);
                        });
                        
                        // Handle subject selection change (only add listener once)
                        const existingListener = subjectSelect.getAttribute('data-listener-added');
                        if (!existingListener) {
                            subjectSelect.setAttribute('data-listener-added', 'true');
                            subjectSelect.addEventListener('change', function() {
                                const selectedSubjectId = this.value;
                                teacherSelect.innerHTML = '<option value="">-- Izberi učitelja --</option>';
                                teacherSelect.disabled = !selectedSubjectId;
                                
                                if (selectedSubjectId) {
                                    // Filter teachers for selected subject
                                    const teachersForSubject = result.subjects.filter(
                                        s => s.id_predmet == selectedSubjectId
                                    );
                                    
                                    // Get already assigned teacher IDs for this subject
                                    const assignedTeacherIdsForSubject = new Set();
                                    if (result.assigned_subjects) {
                                        result.assigned_subjects.forEach(as => {
                                            if (as.id_predmet == selectedSubjectId) {
                                                assignedTeacherIdsForSubject.add(as.id_ucitelj.toString());
                                            }
                                        });
                                    }
                                    
                                    teachersForSubject.forEach(combo => {
                                        const option = document.createElement('option');
                                        option.value = combo.id_ucitelj;
                                        option.textContent = combo.ime_ucitelja + ' ' + combo.priimek_ucitelja;
                                        // Optionally disable if already assigned
                                        if (assignedTeacherIdsForSubject.has(combo.id_ucitelj.toString())) {
                                            option.disabled = true;
                                            option.textContent += ' (že dodeljen)';
                                        }
                                        teacherSelect.appendChild(option);
                                    });
                                }
                            });
                        }
                    } else {
                        // If no subjects returned, show helpful message
                        let errorMsg = '-- Ni na voljo predmetov z učitelji --';
                        if (result.debug && result.debug.message) {
                            errorMsg += ' (' + result.debug.message + ')';
                        }
                        subjectSelect.innerHTML = '<option value="">' + errorMsg + '</option>';
                        console.warn('No subjects with teachers found in database', result.debug || '');
                        
                        // Show message to user
                        const messageDiv = document.getElementById('subject-message');
                        if (messageDiv) {
                            messageDiv.textContent = 'Opozorilo: V bazi ni predmetov z dodeljenimi učitelji. Najprej dodelite učitelje predmetom.';
                            messageDiv.style.color = '#d4a574';
                        }
                    }
                } else {
                    // API error
                    const subjectSelect = document.getElementById('new_subject');
                    subjectSelect.innerHTML = '<option value="">-- Napaka pri nalaganju --</option>';
                    console.error('API error:', result.message || 'Unknown error');
                }
            } else {
                // For teachers, use existing endpoint
                const response = await fetch('admin_ajax_fetch_subjects.php?id_ucitelj=' + userId);
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
            }
        } catch (error) {
            console.error('Error loading subjects:', error);
            const subjectSelect = document.getElementById('new_subject');
            if (subjectSelect) {
                subjectSelect.innerHTML = '<option value="">-- Napaka pri nalaganju --</option>';
            }
        }
    }

    // Add subject
    async function addSubject() {
        const subjectId = document.getElementById('new_subject').value;
        if (!subjectId) {
            alert('Izberite predmet!');
            return;
        }
        
        let teacherId = null;
        if (isStudent) {
            teacherId = document.getElementById('new_teacher').value;
            if (!teacherId) {
                alert('Izberite učitelja!');
                return;
            }
        }

        try {
            let response;
            if (isStudent) {
                response = await fetch('admin_ajax_assign_subject.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id_ucenec: userId,
                        id_predmet: subjectId,
                        id_ucitelj: teacherId,
                        action: 'add'
                    })
                });
            } else {
                response = await fetch('admin_ajax_manage_teacher_subjects.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id_ucitelj: userId,
                        id_predmet: subjectId,
                        action: 'add'
                    })
                });
            }
            
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
    async function removeSubject(subjectId, subjectName, teacherId = null) {
        if (!confirm('Ste prepričani, da želite odstraniti predmet "' + subjectName + '"?')) {
            return;
        }

        try {
            let response;
            if (isStudent) {
                response = await fetch('admin_ajax_assign_subject.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id_ucenec: userId,
                        id_predmet: subjectId,
                        id_ucitelj: teacherId,
                        action: 'delete'
                    })
                });
            } else {
                response = await fetch('admin_ajax_manage_teacher_subjects.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id_ucitelj: userId,
                        id_predmet: subjectId,
                        action: 'delete'
                    })
                });
            }
            
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
    
    // Tab switching function
    function openTab(evt, tabName) {
        let i, tabcontent, tablinks;
        
        // Hide all tab content
        tabcontent = document.getElementsByClassName("tab-content");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].classList.remove("active");
        }
        
        // Remove active class from all tab buttons
        tablinks = document.getElementsByClassName("tab-button");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].classList.remove("active");
        }
        
        // Show the selected tab content
        document.getElementById(tabName).classList.add("active");
        
        // Add active class to the clicked button
        if (evt && evt.currentTarget) {
            evt.currentTarget.classList.add("active");
        }
    }
</script>

</body>
</html>

