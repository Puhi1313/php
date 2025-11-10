<?php
session_start();
require_once 'povezava.php'; // Predpostavljamo, da je 'povezava.php' na voljo

// Preverjanje vloge in preusmeritev
if (!isset($_SESSION['user_id']) || $_SESSION['vloga'] !== 'ucenec') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$ime_priimek = 'Neznan uporabnik';
$predmeti_ucenec = [];
$vse_naloge_ucenec = [];

try {
    // $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Pridobitev imena
    $sql_ime = "SELECT ime, priimek FROM uporabnik WHERE id_uporabnik = ?";
    $stmt_ime = $pdo->prepare($sql_ime);
    $stmt_ime->execute([$user_id]);
    $uporabnik_data = $stmt_ime->fetch();
    $ime_priimek = $uporabnik_data ? $uporabnik_data['ime'] . ' ' . $uporabnik_data['priimek'] : 'Neznan uporabnik';

    // 2. Pridobitev vseh predmetov, ki jih ima učenec
    $sql_predmeti = "
        SELECT 
            p.id_predmet, 
            p.ime_predmeta, 
            GROUP_CONCAT(DISTINCT CONCAT(u.ime, ' ', u.priimek) SEPARATOR ', ') AS ucitelji
        FROM ucenec_predmet ucp
        JOIN predmet p ON ucp.id_predmet = p.id_predmet
        JOIN ucitelj_predmet upr ON p.id_predmet = upr.id_predmet
        JOIN uporabnik u ON upr.id_ucitelj = u.id_uporabnik
        WHERE ucp.id_ucenec = ?
        GROUP BY p.id_predmet
        ORDER BY p.ime_predmeta ASC
    ";
    $stmt_predmeti = $pdo->prepare($sql_predmeti);
    $stmt_predmeti->execute([$user_id]);
    $predmeti_ucenec = $stmt_predmeti->fetchAll();

    // --- FILTER: preberi GET parametre (predmet in razvrsti)
    $filter_predmet = isset($_GET['predmet']) ? (int)$_GET['predmet'] : 0;
    $filter_order = isset($_GET['order']) ? $_GET['order'] : 'newest'; // 'newest' or 'oldest'

    // 3. Pridobitev vseh nalog za te predmete (z možnostjo filtriranja)
    // CRITICAL: Check for ALL tasks with ocena=1 (not just those with active podaljsan_rok)
    $sql_naloge = "
        SELECT 
            n.id_naloga, n.naslov, n.opis_naloge, n.rok_oddaje, n.datum_objave, n.id_predmet, n.id_ucitelj, n.pot_na_strezniku AS naloga_datoteka,
            p.ime_predmeta, 
            u.ime AS ime_ucitelja, u.priimek AS priimek_ucitelja,
            -- Latest submission data (for non-failing tasks)
            o_latest.id_oddaja, o_latest.datum_oddaje, o_latest.besedilo_oddaje, o_latest.pot_na_strezniku AS oddaja_datoteka, 
            o_latest.status, o_latest.ocena, o_latest.komentar_ucitelj, o_latest.podaljsan_rok,
            -- Check for failing task: ANY submission with ocena=1 (regardless of podaljsan_rok or status)
            CASE 
                WHEN EXISTS (
                    SELECT 1 FROM oddaja o_fail 
                    WHERE o_fail.id_naloga = n.id_naloga 
                    AND o_fail.id_ucenec = ? 
                    AND o_fail.ocena = '1'
                ) THEN 1 
                ELSE 0 
            END AS je_negativna_naloga,
            -- Get ALL data from the latest submission with ocena=1 (if exists) - this is what we display for failing tasks
            o_fail_data.id_oddaja AS fail_id_oddaja,
            o_fail_data.datum_oddaje AS fail_datum_oddaje,
            o_fail_data.besedilo_oddaje AS fail_besedilo_oddaje,
            o_fail_data.pot_na_strezniku AS fail_oddaja_datoteka,
            o_fail_data.status AS fail_status,
            o_fail_data.ocena AS fail_ocena,
            o_fail_data.komentar_ucitelj AS fail_komentar_ucitelj,
            o_fail_data.podaljsan_rok AS fail_podaljsan_rok
        FROM naloga n
        JOIN predmet p ON n.id_predmet = p.id_predmet
        JOIN uporabnik u ON n.id_ucitelj = u.id_uporabnik
        JOIN ucenec_predmet up ON n.id_predmet = up.id_predmet AND up.id_ucenec = ? 
        -- Get latest submission (for non-failing tasks)
        LEFT JOIN (
            SELECT o1.* 
            FROM oddaja o1
            INNER JOIN (
                SELECT id_naloga, id_ucenec, MAX(datum_oddaje) AS max_datum
                FROM oddaja
                WHERE id_ucenec = ?
                GROUP BY id_naloga, id_ucenec
            ) o2 ON o1.id_naloga = o2.id_naloga 
                AND o1.id_ucenec = o2.id_ucenec 
                AND o1.datum_oddaje = o2.max_datum
        ) o_latest ON n.id_naloga = o_latest.id_naloga AND o_latest.id_ucenec = ?
        -- Get latest submission with ocena=1 (if exists) - regardless of podaljsan_rok or status
        LEFT JOIN oddaja o_fail_data ON o_fail_data.id_naloga = n.id_naloga
            AND o_fail_data.id_ucenec = ?
            AND o_fail_data.ocena = '1'
            AND o_fail_data.id_oddaja = (
                SELECT id_oddaja FROM oddaja o_fail_sub
                WHERE o_fail_sub.id_naloga = n.id_naloga
                    AND o_fail_sub.id_ucenec = ?
                    AND o_fail_sub.ocena = '1'
                ORDER BY o_fail_sub.datum_oddaje DESC
                LIMIT 1
            )
    ";

    // Gradimo dodatne WHERE pogoje za filtriranje
    // Parameters: EXISTS subquery (1), JOIN ucenec_predmet (2), o_latest subquery user_id (3), o_latest JOIN (4), 
    //            o_fail_data JOIN user_id (5), o_fail_data subquery user_id (6)
    $params = [$user_id, $user_id, $user_id, $user_id, $user_id, $user_id];

    if ($filter_predmet > 0) {
        $sql_naloge .= " AND n.id_predmet = ?";
        $params[] = $filter_predmet;
    }

    // Order by date (newest or oldest)
    if ($filter_order === 'oldest') {
        $sql_naloge .= " ORDER BY n.datum_objave ASC";
    } else {
        $sql_naloge .= " ORDER BY n.datum_objave DESC";
    }

    $stmt_naloge = $pdo->prepare($sql_naloge);
    $stmt_naloge->execute($params);
    
    $vse_naloge_ucenec = $stmt_naloge->fetchAll();

} catch (\PDOException $e) {
    // ** TA CATCH BLOK JE KLJUČEN ZA DEBUGIRANJE **
    // Če ta koda pri učencu ne dela, je SQL poizvedba napačna.
    http_response_code(500);
    // IZPIŠITE DEJANSKO NAPAKO ZA POPRAVEK
    die("NAPAKA V UČENEC_UCILNICA.PHP: Poizvedba baze ni uspela. " . $e->getMessage());
}

// Funkcija za določitev statusa naloge na podlagi rokov in oddaje
// This function now receives normalized data (either from failing submission or latest submission)
function get_naloga_status($naloga, $is_failing_task = false) {
    $danes = new DateTime();
    
    // For failing tasks, use podaljsan_rok; otherwise check latest submission or original deadline
    $podaljsan_rok = !empty($naloga['podaljsan_rok']) ? $naloga['podaljsan_rok'] : null;
    $rok_za_preverjanje = $podaljsan_rok ? new DateTime($podaljsan_rok) : new DateTime($naloga['rok_oddaje']);
    $je_prepozno = $danes > $rok_za_preverjanje;

    $status = $naloga['status'] ?? null;
    $ocena = $naloga['ocena'] ?? null;

    // Failing tasks: ocena=1 (always show as needing resubmission)
    if ($is_failing_task && $ocena == '1') {
        if ($podaljsan_rok) {
            $podaljsan_rok_dt = new DateTime($podaljsan_rok);
            if ($podaljsan_rok_dt > $danes) {
                return [
                    'status' => 'Za dopolnitev (Ocena: 1)',
                    'barva' => '#d4a574',
                    'ikona' => '⚠️',
                    'podaljsan_rok' => $podaljsan_rok
                ];
            }
        }
        // Even without podaljsan_rok or if it expired, show as needing resubmission
        return [
            'status' => 'Nezadostno (Ocena: 1)',
            'barva' => '#d4a574',
            'ikona' => '⚠️',
            'podaljsan_rok' => $podaljsan_rok
        ];
    }

    // Check submission status
    if ($status === 'Ocenjeno') {
        if (strtoupper($ocena ?? '') === 'ND') {
            return [
                'status' => 'Za dopolnitev',
                'barva' => '#d4a574',
                'ikona' => '⚠️'
            ];
        } else {
            return [
                'status' => 'Ocenjeno (' . htmlspecialchars($ocena) . ')',
                'barva' => '#6b8c7d',
                'ikona' => '✅'
            ];
        }
    } elseif ($status === 'Oddano' || $status === 'Zamenjana') {
        return [
            'status' => $status === 'Zamenjana' ? 'Zamenjana (Čaka na oceno)' : 'Oddano (Čaka na oceno)',
            'barva' => '#80852f',
            'ikona' => '📝'
        ];
    } elseif ($status === 'Dopolnitev') {
        return [
            'status' => 'Za dopolnitev',
            'barva' => '#d4a574',
            'ikona' => '⚠️',
            'podaljsan_rok' => $podaljsan_rok
        ];
    } elseif ($je_prepozno) {
        return [
            'status' => 'Pretečen rok',
            'barva' => '#a85d4a',
            'ikona' => '❌'
        ];
    } else {
        return [
            'status' => 'Nova naloga',
            'barva' => '#cdcdb6',
            'ikona' => '🌟'
        ];
    }
}

// Filtriranje nalog - CRITICAL: Check failing tasks FIRST
$naloge_nova = []; // Nova naloga, Za dopolnitev (non-failing), Pretečen rok
$naloge_oddano = []; // Oddano (Čaka na oceno)
$naloge_ocenjeno = []; // Ocenjeno (final grades, not failing)
$naloge_negativne = []; // Negativne naloge (ocena = 1, podaljsan_rok v prihodnosti)

foreach ($vse_naloge_ucenec as $naloga) {
    $danes = new DateTime();
    $je_negativna = $naloga['je_negativna_naloga'] == 1;
    
    // STEP 1: Handle FAILING TASKS first (highest priority) - ALL tasks with ocena=1
    if ($je_negativna) {
        // Use data from failing submission if available, otherwise use latest submission
        if (!empty($naloga['fail_id_oddaja'])) {
            // Use data from the submission with ocena=1
            $naloga_normalized = [
                'id_naloga' => $naloga['id_naloga'],
                'naslov' => $naloga['naslov'],
                'opis_naloge' => $naloga['opis_naloge'],
                'rok_oddaje' => $naloga['rok_oddaje'],
                'datum_objave' => $naloga['datum_objave'],
                'id_predmet' => $naloga['id_predmet'],
                'id_ucitelj' => $naloga['id_ucitelj'],
                'naloga_datoteka' => $naloga['naloga_datoteka'],
                'ime_predmeta' => $naloga['ime_predmeta'],
                'ime_ucitelja' => $naloga['ime_ucitelja'],
                'priimek_ucitelja' => $naloga['priimek_ucitelja'],
                'id_oddaja' => $naloga['fail_id_oddaja'],
                'datum_oddaje' => $naloga['fail_datum_oddaje'],
                'besedilo_oddaje' => $naloga['fail_besedilo_oddaje'],
                'oddaja_datoteka' => $naloga['fail_oddaja_datoteka'],
                'status' => $naloga['fail_status'],
                'ocena' => $naloga['fail_ocena'],
                'komentar_ucitelj' => $naloga['fail_komentar_ucitelj'],
                'podaljsan_rok' => $naloga['fail_podaljsan_rok']
            ];
        } else {
            // Fallback: use latest submission data (should have ocena=1)
            $naloga_normalized = [
                'id_naloga' => $naloga['id_naloga'],
                'naslov' => $naloga['naslov'],
                'opis_naloge' => $naloga['opis_naloge'],
                'rok_oddaje' => $naloga['rok_oddaje'],
                'datum_objave' => $naloga['datum_objave'],
                'id_predmet' => $naloga['id_predmet'],
                'id_ucitelj' => $naloga['id_ucitelj'],
                'naloga_datoteka' => $naloga['naloga_datoteka'],
                'ime_predmeta' => $naloga['ime_predmeta'],
                'ime_ucitelja' => $naloga['ime_ucitelja'],
                'priimek_ucitelja' => $naloga['priimek_ucitelja'],
                'id_oddaja' => $naloga['id_oddaja'],
                'datum_oddaje' => $naloga['datum_oddaje'],
                'besedilo_oddaje' => $naloga['besedilo_oddaje'],
                'oddaja_datoteka' => $naloga['oddaja_datoteka'],
                'status' => $naloga['status'],
                'ocena' => $naloga['ocena'],
                'komentar_ucitelj' => $naloga['komentar_ucitelj'],
                'podaljsan_rok' => $naloga['podaljsan_rok']
            ];
        }
        
        $status_data = get_naloga_status($naloga_normalized, true);
        $naloga_normalized['status_info'] = $status_data;
        $rok_za_preverjanje = !empty($naloga_normalized['podaljsan_rok']) 
            ? new DateTime($naloga_normalized['podaljsan_rok']) 
            : new DateTime($naloga_normalized['rok_oddaje']);
        $naloga_normalized['je_prepozno'] = $danes > $rok_za_preverjanje;
        
        $naloge_negativne[] = $naloga_normalized;
        continue; // Don't process further - this is a failing task
    }
    
    // STEP 2: Handle non-failing tasks - use latest submission data
    $naloga_normalized = [
        'id_naloga' => $naloga['id_naloga'],
        'naslov' => $naloga['naslov'],
        'opis_naloge' => $naloga['opis_naloge'],
        'rok_oddaje' => $naloga['rok_oddaje'],
        'datum_objave' => $naloga['datum_objave'],
        'id_predmet' => $naloga['id_predmet'],
        'id_ucitelj' => $naloga['id_ucitelj'],
        'naloga_datoteka' => $naloga['naloga_datoteka'],
        'ime_predmeta' => $naloga['ime_predmeta'],
        'ime_ucitelja' => $naloga['ime_ucitelja'],
        'priimek_ucitelja' => $naloga['priimek_ucitelja'],
        'id_oddaja' => $naloga['id_oddaja'],
        'datum_oddaje' => $naloga['datum_oddaje'],
        'besedilo_oddaje' => $naloga['besedilo_oddaje'],
        'oddaja_datoteka' => $naloga['oddaja_datoteka'],
        'status' => $naloga['status'],
        'ocena' => $naloga['ocena'],
        'komentar_ucitelj' => $naloga['komentar_ucitelj'],
        'podaljsan_rok' => $naloga['podaljsan_rok']
    ];
    
    $status_data = get_naloga_status($naloga_normalized, false);
    $naloga_normalized['status_info'] = $status_data;
    $rok_za_preverjanje = !empty($naloga_normalized['podaljsan_rok']) 
        ? new DateTime($naloga_normalized['podaljsan_rok']) 
        : new DateTime($naloga_normalized['rok_oddaje']);
    $naloga_normalized['je_prepozno'] = $danes > $rok_za_preverjanje;
    
    // Categorize based on status
    $status_str = $status_data['status'];
    if ($status_str === 'Oddano (Čaka na oceno)' || $status_str === 'Zamenjana (Čaka na oceno)') {
        $naloge_oddano[] = $naloga_normalized;
    } elseif (strpos($status_str, 'Ocenjeno') !== false) {
        // Only add to ocenjeno if it's a final grade (not failing)
        // Failing tasks (ocena=1) should have been caught above
        $naloge_ocenjeno[] = $naloga_normalized;
    } else {
        // Nova naloga, Za dopolnitev (non-failing), Pretečen rok
        $naloge_nova[] = $naloga_normalized;
    }
}

?>

<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Učilnica - Učenec</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Momo+Trust+Display&family=Raleway:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <style>
        html{
            color: #596235;
        }
        body {
            margin: 0;
            font-family: "Raleway", sans-serif;
            /* Harmonious layered background using existing palette */
            background:
                radial-gradient(900px 500px at 10% -10%, rgba(205, 205, 182, 0.65), rgba(205, 205, 182, 0) 70%),
                radial-gradient(900px 500px at 110% 10%, rgba(128, 133, 47, 0.18), rgba(128, 133, 47, 0) 60%),
                linear-gradient(180deg, #f7f8f3 0%, #eff1e4 45%, #e3e6d1 100%);
            background-attachment: fixed;
            color: #596235;
        }
        header {
            background: #cdcdb6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        header .logo {
            font-weight: bold;
            font-size: 24px;
            color: #596235;
        }
        nav a {
            margin-left: 20px;
            text-decoration: none;
            color: #596235;
            font-size: 16px;
        }
        nav a:hover {
            text-decoration: underline;
        }
        .container {
            display: flex;
            max-width: 1200px;
            margin: 20px auto;
            background: #fff;
            border: 1px solid #cdcdb6;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            min-height: 80vh;
        }

        /* Glavni Menu (Predmeti) */
        .left-menu {
            width: 250px;
            background: #f8f8f0;
            padding: 20px;
            border-right: 1px solid #ddd;
        }
        .left-menu h4 {
            margin-top: 0;
            color: #596235;
            border-bottom: 2px solid #cdcdb6;
            padding-bottom: 5px;
            margin-bottom: 15px;
        }
        .predmet-item {
            display: block;
            padding: 10px;
            margin-bottom: 5px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 10px;
            text-decoration: none;
            color: #596235;
            transition: background 0.2s, border-color 0.2s, transform 0.15s ease, box-shadow 0.15s ease;
            cursor: pointer;
        }
        .predmet-item:hover, .predmet-item.active {
            background: #e6e6fa; /* Light purple for active/hover */
            border-color: #cdcdb6;
            font-weight: bold;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        }

        /* Glavna Vsebina (Naloge in Status) */
        .main-content {
            flex-grow: 1;
            padding: 20px;
        }
        .main-content h2 {
            color: #596235;
            border-bottom: 2px solid #cdcdb6;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        /* ZAVIHKI (Tabs) */
        .tab-buttons {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 2px solid #ddd;
        }
        .tab-button {
            background: #eee;
            border: 1px solid #ddd;
            border-bottom: none;
            padding: 10px 15px;
            cursor: pointer;
            margin-right: 5px;
            border-radius: 10px 10px 0 0;
            transition: background 0.3s, transform 0.15s ease;
        }
        .tab-button.active {
            background: #fff;
            border-color: #cdcdb6;
            border-bottom: 2px solid #fff;
            font-weight: bold;
        }
        .tab-button:hover { transform: translateY(-1px); }
        .tab-content {
            display: none;
            padding: 10px;
            border: 1px solid #cdcdb6;
            border-top: none;
            border-radius: 0 10px 10px 10px;
        }
        .tab-content.active {
            display: block;
        }

        /* Seznam nalog */
        .naloga-list {
            list-style: none;
            padding: 0;
        }
        .naloga-item {
            padding: 15px;
            margin-bottom: 10px;
            margin-left: 15px;
            border: 1px solid #e2e2e2;
            border-left: 5px solid;
            border-radius: 10px;
            background: #fff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.2s ease;
        }
        .naloga-item:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(0,0,0,0.07); }
        .naloga-item.nova { border-left-color: #cdcdb6; }
        .naloga-item.oddano { border-left-color: #80852f; }
        .naloga-item.ocenjeno { border-left-color: #6b8c7d; }
        .naloga-item.dopolnitev { border-left-color: #d4a574; }
        .naloga-item.pretecen { border-left-color: #a85d4a; }

        .naloga-details {
            flex-grow: 1;
        }
        .naloga-details h5 {
            margin: 0 0 5px 0;
            font-size: 18px;
            color: #596235;
        }
        .naloga-details p {
            margin: 0;
            font-size: 14px;
            color: #596235;
        }

        .naloga-status {
            text-align: right;
        }
        .naloga-status .status-tag {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            color: white;
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 5px;
        }
        .naloga-status button {
            background: #596235;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }
        .naloga-status button:hover {
            background: #0056b3;
        }

        /* Modal */
        .modal {
            display: none; 
            position: fixed; 
            z-index: 1; 
            left: 0;
            top: 0;
            width: 100%; 
            height: 100%; 
            overflow: auto; 
            background-color: rgba(0,0,0,0.4); 
            padding-top: 60px;
        }
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%; 
            max-width: 700px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        .close-btn {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }
        .close-btn:hover,
        .close-btn:focus {
            color: #000;
            text-decoration: none;
            cursor: pointer;
        }
        .oddaja-form label, .oddaja-form input, .oddaja-form textarea, .oddaja-form button {
            display: block;
            width: 100%;
            box-sizing: border-box;
            margin-bottom: 10px;
        }
        .oddaja-form textarea {
            min-height: 150px;
        }
        .oddaja-form button[type="submit"] {
            background-color: #28a745;
            color: white;
            padding: 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .oddaja-form button[type="submit"]:hover:not(:disabled) {
            background-color: #218838;
        }
        .oddaja-form button[type="submit"]:disabled {
            background-color: #999;
            cursor: not-allowed;
        }

        /* Drag & Drop area */
        .dropzone {
            position: relative;
            border: 2px dashed #bfbfae;
            border-radius: 14px;
            background: #f6f7f1;
            padding: 26px;
            text-align: center;
            color: #596235;
            transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
            margin-bottom: 15px;
        }
        .dropzone:hover { box-shadow: 0 6px 16px rgba(0,0,0,0.06); }
        .dropzone.dragover {
            border-color: #80852f;
            background: #eef0e1;
        }
        .dropzone .dz-icon { font-size: 42px; margin-bottom: 10px; display: block; }
        .dropzone .dz-title { font-weight: 700; margin: 6px 0; }
        .dropzone .dz-sub { color: #6c7450; margin-bottom: 12px; }
        .dropzone .dz-browse { 
            padding: 8px 15px; background: #596235; color: #fff; border: none; border-radius: 6px; cursor: pointer; 
            transition: background 0.3s, transform 0.15s ease; font-family: "Raleway", sans-serif; font-size: 14px; font-weight: 500;
        }
        .dropzone .dz-browse:hover { background: #4a5230; transform: translateY(-1px); }
        .dropzone .dz-file-name { margin-top: 8px; font-size: 14px; color: #6c7450; }
    </style>
</head>
<body>

<header>
    <div class="logo">E-Učilnica</div>
    <nav style="display:flex;gap:12px;align-items:center;">
        <?php
            // get current user pic (use $user_id already set earlier)
            $pic = '';
            try {
                $stmt = $pdo->prepare("SELECT icona_profila FROM uporabnik WHERE id_uporabnik = ?");
                $stmt->execute([$user_id]);
                $pic = $stmt->fetchColumn();
            } catch (\Exception $e) { $pic = ''; }
        ?>
        <?php if (!empty($pic) && file_exists(__DIR__ . DIRECTORY_SEPARATOR . $pic)): ?>
            <img src="<?php echo htmlspecialchars($pic); ?>" alt="Profil" style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid #fff;">
        <?php else: ?>
            <div style="width:36px;height:36px;border-radius:50%;background:#2e8b57;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;">
                <?php echo strtoupper(substr($ime_priimek,0,1)); ?>
            </div>
        <?php endif; ?>
        <a href="ucenec_profile.php">Moj profil</a>
        <a href="logout.php">Odjava</a>
    </nav>
</header>

<div class="container">
    <div class="left-menu">
        <h4>Predmeti</h4>
        <?php if (empty($predmeti_ucenec)): ?>
            <p>Nimate dodeljenih predmetov.</p>
        <?php else: ?>
            <?php foreach ($predmeti_ucenec as $predmet): ?>
                <a href="#" class="predmet-item"
                   data-id-predmet="<?php echo htmlspecialchars($predmet['id_predmet']); ?>"
                   data-ime-predmeta="<?php echo htmlspecialchars($predmet['ime_predmeta']); ?>">
                    <?php echo htmlspecialchars($predmet['ime_predmeta']); ?>
                    <small style="display: block; color: #888;">Učitelji: <?php echo htmlspecialchars($predmet['ucitelji'] ?? ''); ?></small>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <div class="main-content">
        <h2>Pregled nalog</h2>

        <!-- FILTER FORM -->
        <form id="filter-form" method="get" style="margin-bottom:15px; display:flex; gap:10px; align-items:center; flex-wrap: wrap;">
            <label for="predmet">Predmet:</label>
            <select name="predmet" id="predmet">
                <option value="0">Vsi predmeti</option>
                <?php foreach ($predmeti_ucenec as $p): ?>
                    <option value="<?php echo (int)$p['id_predmet']; ?>" <?php echo ($filter_predmet == $p['id_predmet']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($p['ime_predmeta']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="order">Razvrsti:</label>
            <select name="order" id="order">
                <option value="newest" <?php echo $filter_order === 'newest' ? 'selected' : ''; ?>>Najnovejše</option>
                <option value="oldest" <?php echo $filter_order === 'oldest' ? 'selected' : ''; ?>>Najstarejše</option>
            </select>

            <button type="submit" style="padding:6px 10px;">Filtriraj</button>
            <a href="ucenec_ucilnica.php" style="margin-left:8px; text-decoration:none;">Reset</a>
        </form>

        <div class="tab-buttons">
            <button class="tab-button active" data-tab="nova-naloga">Neoddane / Za dopolnitev (<?php echo count($naloge_nova); ?>)</button>
            <button class="tab-button" data-tab="nezadostno">Nezadostno (<?php echo count($naloge_negativne); ?>)</button>
            <button class="tab-button" data-tab="oddano-caka-oceno">Oddane (<?php echo count($naloge_oddano); ?>)</button>
            <button class="tab-button" data-tab="ocenjeno">Ocenjene (<?php echo count($naloge_ocenjeno); ?>)</button>
        </div>

        <div id="nova-naloga" class="tab-content active">
            <?php if (empty($naloge_nova)): ?>
                <p>Odlično! Trenutno nimate novih nalog ali nalog za dopolnitev.</p>
            <?php else: ?>
                <ul class="naloga-list">
                    <?php foreach ($naloge_nova as $naloga): 
                        $class = '';
                        if ($naloga['status_info']['status'] === 'Za dopolnitev') { $class = 'dopolnitev'; } 
                        elseif ($naloga['je_prepozno']) { $class = 'pretecen'; } 
                        else { $class = 'nova'; }
                    ?>
                        <li class="naloga-item <?php echo $class; ?>">
                            <div class="naloga-details">
                                <h5><?php echo htmlspecialchars($naloga['naslov']); ?></h5>
                                <p>Predmet: <?php echo htmlspecialchars($naloga['ime_predmeta']); ?> | Rok oddaje: <?php echo date('d.m.Y H:i', strtotime($naloga['rok_oddaje'])); ?></p>
                            </div>
                            <div class="naloga-status">
                                <span class="status-tag" style="background-color: <?php echo $naloga['status_info']['barva']; ?>;"><?php echo $naloga['status_info']['ikona']; ?> <?php echo htmlspecialchars($naloga['status_info']['status']); ?></span>
                                <button class="oddaja-btn" 
                                    data-id-naloga="<?php echo htmlspecialchars($naloga['id_naloga']); ?>"
                                    data-naslov="<?php echo htmlspecialchars($naloga['naslov']); ?>"
                                    data-opis="<?php echo htmlspecialchars($naloga['opis_naloge']); ?>"
                                    data-rok="<?php echo date('d.m.Y H:i', strtotime($naloga['rok_oddaje'])); ?>"
                                    data-datoteka="<?php echo htmlspecialchars($naloga['naloga_datoteka'] ?? ''); ?>"
                                    data-status-oddaje="<?php echo htmlspecialchars($naloga['status']); ?>"
                                    data-ocena="<?php echo htmlspecialchars($naloga['ocena'] ?? ''); ?>"
                                    data-oddaja-besedilo="<?php echo htmlspecialchars($naloga['besedilo_oddaje'] ?? ''); ?>"
                                    data-oddaja-datoteka="<?php echo htmlspecialchars($naloga['oddaja_datoteka'] ?? ''); ?>"
                                    data-komentar-ucitelj="<?php echo htmlspecialchars($naloga['komentar_ucitelj'] ?? ''); ?>"
                                    <?php echo $naloga['je_prepozno'] && $naloga['status_info']['status'] !== 'Za dopolnitev' ? 'disabled' : ''; ?>
                                >
                                    <?php echo $naloga['je_prepozno'] && $naloga['status_info']['status'] !== 'Za dopolnitev' ? 'Pretečen rok' : ($naloga['status_info']['status'] === 'Za dopolnitev' ? 'Dopolni' : 'Oddaj'); ?>
                                </button>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div id="nezadostno" class="tab-content">
            <?php if (empty($naloge_negativne)): ?>
                <p>Odlično! Nimate negativnih nalog za dopolnitev.</p>
            <?php else: ?>
                <ul class="naloga-list">
                    <?php foreach ($naloge_negativne as $naloga): 
                        $podaljsan_rok_display = !empty($naloga['podaljsan_rok']) ? date('d.m.Y H:i', strtotime($naloga['podaljsan_rok'])) : '';
                    ?>
                        <li class="naloga-item dopolnitev">
                            <div class="naloga-details">
                                <h5><?php echo htmlspecialchars($naloga['naslov']); ?></h5>
                                <p>Predmet: <?php echo htmlspecialchars($naloga['ime_predmeta']); ?> 
                                <?php if ($podaljsan_rok_display): ?>
                                    | Podaljšan rok: <strong style="color: #d4a574;"><?php echo $podaljsan_rok_display; ?></strong>
                                <?php endif; ?>
                                </p>
                                <?php if ($naloga['komentar_ucitelj']): ?>
                                    <p style="color: #a85d4a; font-style: italic;">Komentar: <?php echo htmlspecialchars($naloga['komentar_ucitelj']); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="naloga-status">
                                <span class="status-tag" style="background-color: <?php echo $naloga['status_info']['barva']; ?>;"><?php echo $naloga['status_info']['ikona']; ?> <?php echo htmlspecialchars($naloga['status_info']['status']); ?></span>
                                <button class="oddaja-btn" 
                                    data-id-naloga="<?php echo htmlspecialchars($naloga['id_naloga']); ?>"
                                    data-naslov="<?php echo htmlspecialchars($naloga['naslov']); ?>"
                                    data-opis="<?php echo htmlspecialchars($naloga['opis_naloge']); ?>"
                                    data-rok="<?php echo $podaljsan_rok_display ?: date('d.m.Y H:i', strtotime($naloga['rok_oddaje'])); ?>"
                                    data-datoteka="<?php echo htmlspecialchars($naloga['naloga_datoteka'] ?? ''); ?>"
                                    data-status-oddaje="<?php echo htmlspecialchars($naloga['status']); ?>"
                                    data-ocena="<?php echo htmlspecialchars($naloga['ocena'] ?? ''); ?>"
                                    data-oddaja-besedilo="<?php echo htmlspecialchars($naloga['besedilo_oddaje'] ?? ''); ?>"
                                    data-oddaja-datoteka="<?php echo htmlspecialchars($naloga['oddaja_datoteka'] ?? ''); ?>"
                                    data-komentar-ucitelj="<?php echo htmlspecialchars($naloga['komentar_ucitelj'] ?? ''); ?>"
                                    data-podaljsan-rok="<?php echo htmlspecialchars($podaljsan_rok_display); ?>"
                                >
                                    Dopolni
                                </button>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div id="oddano-caka-oceno" class="tab-content">
            <?php if (empty($naloge_oddano)): ?>
                <p>Nimate nalog, ki bi čakale na oceno.</p>
            <?php else: ?>
                <ul class="naloga-list">
                    <?php foreach ($naloge_oddano as $naloga): ?>
                        <li class="naloga-item oddano">
                            <div class="naloga-details">
                                <h5><?php echo htmlspecialchars($naloga['naslov']); ?></h5>
                                <p>Predmet: <?php echo htmlspecialchars($naloga['ime_predmeta']); ?> | Oddano: <?php echo date('d.m.Y H:i', strtotime($naloga['datum_oddaje'])); ?></p>
                            </div>
                            <div class="naloga-status">
                                <span class="status-tag" style="background-color: <?php echo $naloga['status_info']['barva']; ?>;"><?php echo $naloga['status_info']['ikona']; ?> <?php echo htmlspecialchars($naloga['status_info']['status']); ?></span>
                                <button class="oddaja-btn" 
                                    data-id-naloga="<?php echo htmlspecialchars($naloga['id_naloga']); ?>"
                                    data-naslov="<?php echo htmlspecialchars($naloga['naslov']); ?>"
                                    data-opis="<?php echo htmlspecialchars($naloga['opis_naloge']); ?>"
                                    data-rok="<?php echo date('d.m.Y H:i', strtotime($naloga['rok_oddaje'])); ?>"
                                    data-datoteka="<?php echo htmlspecialchars($naloga['naloga_datoteka'] ?? ''); ?>"
                                    data-status-oddaje="<?php echo htmlspecialchars($naloga['status']); ?>"
                                    data-ocena="<?php echo htmlspecialchars($naloga['ocena'] ?? ''); ?>"
                                    data-oddaja-besedilo="<?php echo htmlspecialchars($naloga['besedilo_oddaje'] ?? ''); ?>"
                                    data-oddaja-datoteka="<?php echo htmlspecialchars($naloga['oddaja_datoteka'] ?? ''); ?>"
                                    data-komentar-ucitelj="<?php echo htmlspecialchars($naloga['komentar_ucitelj'] ?? ''); ?>"
                                    data-datum-oddaje="<?php echo htmlspecialchars($naloga['datum_oddaje'] ?? ''); ?>"
                                >
                                    Prikaži
                                </button>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        
        <div id="ocenjeno" class="tab-content">
            <?php if (empty($naloge_ocenjeno)): ?>
                <p>Nimate ocenjenih nalog.</p>
            <?php else: ?>
                <ul class="naloga-list">
                    <?php foreach ($naloge_ocenjeno as $naloga): ?>
                        <li class="naloga-item ocenjeno">
                            <div class="naloga-details">
                                <h5><?php echo htmlspecialchars($naloga['naslov']); ?></h5>
                                <p>Predmet: <?php echo htmlspecialchars($naloga['ime_predmeta']); ?> | Ocenjeno: <?php echo date('d.m.Y H:i', strtotime($naloga['datum_oddaje'])); ?></p>
                            </div>
                            <div class="naloga-status">
                                <span class="status-tag" style="background-color: <?php echo $naloga['status_info']['barva']; ?>;"><?php echo $naloga['status_info']['ikona']; ?> <?php echo htmlspecialchars($naloga['status_info']['status']); ?></span>
                                <button class="oddaja-btn" 
                                    data-id-naloga="<?php echo htmlspecialchars($naloga['id_naloga']); ?>"
                                    data-naslov="<?php echo htmlspecialchars($naloga['naslov']); ?>"
                                    data-opis="<?php echo htmlspecialchars($naloga['opis_naloge']); ?>"
                                    data-rok="<?php echo date('d.m.Y H:i', strtotime($naloga['rok_oddaje'])); ?>"
                                    data-datoteka="<?php echo htmlspecialchars($naloga['naloga_datoteka'] ?? ''); ?>"
                                    data-status-oddaje="<?php echo htmlspecialchars($naloga['status']); ?>"
                                    data-ocena="<?php echo htmlspecialchars($naloga['ocena'] ?? ''); ?>"
                                    data-oddaja-besedilo="<?php echo htmlspecialchars($naloga['besedilo_oddaje'] ?? ''); ?>"
                                    data-oddaja-datoteka="<?php echo htmlspecialchars($naloga['oddaja_datoteka'] ?? ''); ?>"
                                    data-komentar-ucitelj="<?php echo htmlspecialchars($naloga['komentar_ucitelj'] ?? ''); ?>"
                                >
                                    Prikaži Oceno
                                </button>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

    </div>
</div>

<div id="naloga-modal" class="modal">
    <div class="modal-content">
        <span class="close-btn">&times;</span>
        <h3 id="modal-naslov">Naslov naloge</h3>
        <p id="modal-rok">Rok oddaje: </p>
        <hr>
        
        <h4>Opis naloge:</h4>
        <p id="modal-opis"></p>
        <p id="modal-datoteka"></p>

        <div id="oddaja-info-container" style="margin-top: 15px; border: 1px solid #ddd; padding: 10px; background: #f9f9f9; display: none;">
            <p style="color: green; font-weight: bold;">Status: <span id="oddaja-status"></span></p>
            <p id="oddaja-datum"></p>
            <p id="oddaja-besedilo-p">Vaše besedilo: <span id="oddaja-besedilo"></span></p>
            <p id="oddaja-datoteka-p"></p>
            <div id="ocena-info" style="margin-top: 10px; border-top: 1px solid #ccc; padding-top: 10px; display: none;">
                <p style="font-weight: bold;">Ocena: <span id="ocena-vrednost" style="color: green;"></span></p>
                <p>Komentar učitelja: <span id="komentar-ucitelj"></span></p>
            </div>
        </div>

        <h4 id="form-header" style="margin-top: 20px;">Oddaj nalogo:</h4>
        <form id="oddaja-form" action="ajax_oddaja.php" method="POST" enctype="multipart/form-data" class="oddaja-form">
            <input type="hidden" name="id_naloga" id="form-id-naloga" value="">
            
            <label for="besedilo_oddaje">Besedilo oddaje (Opcija):</label>
            <textarea id="besedilo_oddaje_form" name="besedilo_oddaje" rows="6" placeholder="Vnesite svoje besedilo (navadno besedilo ali rešitev)"></textarea>
            
            <label for="datoteka_form">Priloži datoteko (Opcija):</label>
            <div id="dz-oddaja" class="dropzone">
                <span class="dz-icon">☁️</span>
                <div class="dz-title">Povlecite in spustite datoteko sem</div>
                <div class="dz-sub">ALI</div>
                <button type="button" class="dz-browse">Izberite datoteko</button>
                <div class="dz-file-name" id="dz-oddaja-filename">Ni izbrane datoteke</div>
                <input type="file" id="datoteka_form" name="datoteka" style="display:none;">
            </div>
            <p style="font-size: 12px; color: #555;">*Če naložite novo datoteko pri ponovni oddaji, bo stara prebrisana.</p>

            <button type="submit">Oddaj nalogo</button>
        </form>
    </div>
</div>

<script>
    const modal = document.getElementById('naloga-modal');
    const closeBtn = document.querySelector('.close-btn');
    const oddajaForm = document.getElementById('oddaja-form');
    
    // Zapiranje modala
    closeBtn.onclick = function() {
        modal.style.display = "none";
        // Počistimo vsebino pri zapiranju
        oddajaForm.reset();
        const dzFileName = document.getElementById('dz-oddaja-filename');
        if (dzFileName) dzFileName.textContent = 'Ni izbrane datoteke';
    }
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
            oddajaForm.reset();
            const dzFileName = document.getElementById('dz-oddaja-filename');
            if (dzFileName) dzFileName.textContent = 'Ni izbrane datoteke';
        }
    }

    // Funkcija za preklapljanje zavihkov
    function openTab(evt) {
        const tabName = evt.currentTarget.dataset.tab;
        
        // Odstrani 'active' razred z vseh gumbov in vsebin
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        document.querySelectorAll('.tab-button').forEach(b => b.classList.remove('active'));

        // Dodaj 'active' razred izbranemu gumbu in vsebini
        document.getElementById(tabName).classList.add('active');
        evt.currentTarget.classList.add('active');
    }

    document.querySelectorAll('.tab-button').forEach(button => {
        button.addEventListener('click', openTab);
    });

    // Initialize Drag & Drop for oddaja
    initDropzone({
        wrapperId: 'dz-oddaja',
        inputId: 'datoteka_form',
        fileNameId: 'dz-oddaja-filename'
    });

    // Funkcija za odpiranje modala in polnjenje podatkov
    document.querySelectorAll('.oddaja-btn').forEach(button => {
        button.addEventListener('click', function() {
            const idNaloga = this.dataset.idNaloga;
            const naslov = this.dataset.naslov;
            const rok = this.dataset.rok;
            const opis = this.dataset.opis;
            const nalogaDatoteka = this.dataset.datoteka;
            const statusOddaje = this.dataset.statusOddaje; // NULL, Oddano, Ocenjeno
            const ocena = this.dataset.ocena;
            const oddajaBesedilo = this.dataset.oddajaBesedilo;
            const oddajaDatoteka = this.dataset.oddajaDatoteka;
            const komentarUcitelj = this.dataset.komentarUcitelj;
            
            // Ponastavi formo in prikaže podatke
            oddajaForm.reset();
            document.getElementById('form-id-naloga').value = idNaloga;
            document.getElementById('modal-naslov').textContent = naslov;
            const podaljsanRokDisplay = this.dataset.podaljsanRok || '';
            if (podaljsanRokDisplay) {
                document.getElementById('modal-rok').textContent = 'Rok oddaje: ' + rok + ' | Podaljšan rok: ' + podaljsanRokDisplay;
            } else {
                document.getElementById('modal-rok').textContent = 'Rok oddaje: ' + rok;
            }
            document.getElementById('modal-opis').innerHTML = opis.replace(/\n/g, '<br>');

            // Prikaže/skrije datoteko naloge
            const nalogaDatotekaP = document.getElementById('modal-datoteka');
            if (nalogaDatoteka) {
                nalogaDatotekaP.innerHTML = `Priložena datoteka učitelja: <a href="${nalogaDatoteka}" target="_blank">Prenesi datoteko</a>`;
                nalogaDatotekaP.style.display = 'block';
            } else {
                nalogaDatotekaP.style.display = 'none';
            }
            
            // Posodobi/skrije informacije o oddaji
            const oddajaInfoContainer = document.getElementById('oddaja-info-container');
            const ocenaInfo = document.getElementById('ocena-info');
            const formHeader = document.getElementById('form-header');
            const oddajaDatum = this.dataset.datumOddaje; // datum oddaje (če obstaja)
            const oddajaDatumEl = document.getElementById('oddaja-datum');

            if (statusOddaje && statusOddaje !== 'NULL') {
                // Je že oddano
                oddajaInfoContainer.style.display = 'block';
                document.getElementById('oddaja-status').textContent = statusOddaje;
                document.getElementById('oddaja-besedilo').textContent = oddajaBesedilo;
                oddajaDatumEl.textContent = oddajaDatum ? ('Datum oddaje: ' + oddajaDatum) : '';

                const oddajaDatotekaP = document.getElementById('oddaja-datoteka-p');
                if (oddajaDatoteka) {
                    oddajaDatotekaP.innerHTML = `Vaša oddana datoteka: <a href="${oddajaDatoteka}" target="_blank">Prenesi</a>`;
                    oddajaDatotekaP.style.display = 'block';
                } else {
                    oddajaDatotekaP.style.display = 'none';
                }

                document.getElementById('besedilo_oddaje_form').value = oddajaBesedilo; // Predpolni besedilo

                // Prikaz ocene in komentarja
                if (statusOddaje === 'Ocenjeno' || statusOddaje === 'Dopolnitev') {
                    ocenaInfo.style.display = 'block';
                    document.getElementById('ocena-vrednost').textContent = ocena || 'Brez ocene';
                    document.getElementById('komentar-ucitelj').textContent = komentarUcitelj || 'Brez komentarja';
                } else {
                    ocenaInfo.style.display = 'none';
                }

                // Upravljanje forme za ponovno oddajo/prikaz
                const submitBtn = oddajaForm.querySelector('button[type="submit"]');
                const podaljsanRok = this.dataset.podaljsanRok || '';
                
                // CRITICAL: Allow resubmission for ALL tasks with ocena = '1' (regardless of status)
                if (ocena == '1') {
                    // Task with grade 1 - allow resubmission
                    let canResubmit = true;
                    let deadlineText = '';
                    
                    if (podaljsanRok) {
                        // Check if podaljsan_rok is still valid
                        const rokParts = podaljsanRok.split(' ');
                        const dateParts = rokParts[0].split('.');
                        const timeParts = rokParts[1] ? rokParts[1].split(':') : ['00', '00'];
                        const rokDate = new Date(dateParts[2], dateParts[1] - 1, dateParts[0], timeParts[0], timeParts[1]);
                        const now = new Date();
                        canResubmit = now <= rokDate;
                        deadlineText = ' (Rok: ' + podaljsanRok + ')';
                    } else {
                        // No podaljsan_rok - allow resubmission (no deadline restriction)
                        canResubmit = true;
                    }
                    
                    if (canResubmit) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Dopolni nalogo';
                        formHeader.textContent = 'Dopolni nalogo (Ocena: 1):' + deadlineText;
                    } else {
                        submitBtn.disabled = true;
                        submitBtn.textContent = 'Rok za dopolnitev potekel';
                        formHeader.textContent = 'Oddana rešitev:';
                    }
                    
                    ocenaInfo.style.display = 'block';
                } else if (statusOddaje === 'Oddano' || statusOddaje === 'Zamenjana') {
                    // Čaka na oceno - onemogoči oddajo in spremeni naslov
                    submitBtn.disabled = true;
                    submitBtn.textContent = statusOddaje === 'Zamenjana' ? 'Zamenjana (Čaka na oceno)' : 'Oddano (Čaka na oceno)';
                    formHeader.textContent = 'Oddana rešitev:';
                } else if (statusOddaje === 'Dopolnitev' && ocena === 'ND') {
                    // ND grade with status 'Dopolnitev' - check podaljsan_rok
                    let canResubmit = false;
                    if (podaljsanRok) {
                        const rokParts = podaljsanRok.split(' ');
                        const dateParts = rokParts[0].split('.');
                        const timeParts = rokParts[1] ? rokParts[1].split(':') : ['00', '00'];
                        const rokDate = new Date(dateParts[2], dateParts[1] - 1, dateParts[0], timeParts[0], timeParts[1]);
                        const now = new Date();
                        canResubmit = now <= rokDate;
                    } else {
                        // ND without podaljsan_rok - allow resubmission within 7 days of grading
                        if (oddajaDatum) {
                            const dt = new Date(oddajaDatum.replace(/\./g,'-').replace(/ /,'T'));
                            const until = new Date(dt.getTime() + 7*24*60*60*1000);
                            const now = new Date();
                            canResubmit = now <= until;
                        }
                    }
                    
                    if (canResubmit) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Dopolni nalogo';
                        formHeader.textContent = 'Dopolni nalogo:';
                        if (podaljsanRok) {
                            formHeader.textContent += ' (Rok: ' + podaljsanRok + ')';
                        }
                    } else {
                        submitBtn.disabled = true;
                        submitBtn.textContent = 'Rok za dopolnitev potekel';
                        formHeader.textContent = 'Oddana rešitev:';
                    }

                    ocenaInfo.style.display = 'block';
                } else if (statusOddaje === 'Ocenjeno' && ocena !== '1' && ocena !== 'ND') {
                    // Ocenjeno (pozitivno) - onemogoči oddajo in spremeni naslov
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Ocenjeno';
                    formHeader.textContent = 'Ocenjena rešitev:';
                } else {
                    // Privzeto: onemogoči oddajo (varno)
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Že oddano';
                    formHeader.textContent = 'Oddana rešitev:';
                }

            } else {
                // Še ni oddano
                oddajaInfoContainer.style.display = 'none';
                ocenaInfo.style.display = 'none';
                
                // Check deadline - use podaljsan_rok if available, otherwise use original rok
                const podaljsanRok = this.dataset.podaljsanRok || '';
                let rokDate;
                if (podaljsanRok) {
                    // Parse podaljsan_rok (format: d.m.Y H:i)
                    const rokParts = podaljsanRok.split(' ');
                    const dateParts = rokParts[0].split('.');
                    const timeParts = rokParts[1] ? rokParts[1].split(':') : ['00', '00'];
                    rokDate = new Date(dateParts[2], dateParts[1] - 1, dateParts[0], timeParts[0], timeParts[1]);
                } else {
                    // Parse original rok (format: d.m.Y H:i)
                    const rokStr = this.dataset.rok;
                    const rokParts = rokStr.split(' ');
                    const dateParts = rokParts[0].split('.');
                    const timeParts = rokParts[1] ? rokParts[1].split(':') : ['00', '00'];
                    rokDate = new Date(dateParts[2], dateParts[1] - 1, dateParts[0], timeParts[0], timeParts[1]);
                }
                
                const now = new Date();
                const isLate = now > rokDate;
                
                const submitBtn = oddajaForm.querySelector('button[type="submit"]');
                if (isLate) {
                    // Pretečen rok
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Pretečen rok';
                    formHeader.textContent = 'Oddaja ni več mogoča.';
                } else {
                    // Nova oddaja
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Oddaj nalogo';
                    formHeader.textContent = 'Oddaj nalogo:';
                    if (podaljsanRok) {
                        formHeader.textContent += ' (Podaljšan rok: ' + podaljsanRok + ')';
                    }
                }
            }

            modal.style.display = "block";
        });
    });

    // Reusable Drag & Drop init
    function initDropzone({ wrapperId, inputId, fileNameId }) {
        const wrapper = document.getElementById(wrapperId);
        const input = document.getElementById(inputId);
        const fileName = document.getElementById(fileNameId);
        if (!wrapper || !input) return;

        const browseBtn = wrapper.querySelector('.dz-browse');

        // Click to open dialog
        if (browseBtn) browseBtn.addEventListener('click', () => input.click());
        wrapper.addEventListener('click', (e) => {
            if (e.target.classList.contains('dz-browse')) return;
            input.click();
        });

        ['dragenter','dragover'].forEach(evt => {
            wrapper.addEventListener(evt, (e) => {
                e.preventDefault();
                e.stopPropagation();
                wrapper.classList.add('dragover');
            });
        });
        ['dragleave','dragend','drop'].forEach(evt => {
            wrapper.addEventListener(evt, (e) => {
                e.preventDefault();
                e.stopPropagation();
                wrapper.classList.remove('dragover');
            });
        });

        wrapper.addEventListener('drop', (e) => {
            if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                updateDzFileName();
            }
        });

        input.addEventListener('change', updateDzFileName);

        function updateDzFileName() {
            if (!fileName) return;
            if (input.files && input.files.length) {
                const names = Array.from(input.files).map(f => f.name).join(', ');
                fileName.textContent = names;
            } else {
                fileName.textContent = 'Ni izbrane datoteke';
            }
        }
    }

    // AJAX oddaja (using ajax_oddaja.php logic)
    oddajaForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const form = e.currentTarget;
        const formData = new FormData(form);
        
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Oddajanje...';

        try {
            const response = await fetch('ajax_oddaja.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            alert(result.message);
            
            if (result.success) {
                // Če je uspešno, ponovno naloži stran za osvežitev statusa
                modal.style.display = "none";
                window.location.reload(); 
            } else {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Poskusi ponovno oddati';
            }
        } catch (error) {
            alert('Napaka pri komunikaciji s strežnikom med oddajo.');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Oddaj nalogo';
        }
    });

    // Inicialni prikaz zavihka (Nova naloga)
    document.addEventListener('DOMContentLoaded', () => {
        // Pusti, da se prikaže privzeti aktivni zavihek iz HTML
    });

</script>
</body>
</html>