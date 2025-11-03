<?php
session_start();
require_once 'povezava.php';

// 1. ZAŠČITA: Preverimo, ali je učenec prijavljen in ali je prvi vpis
if (!isset($_SESSION['user_id']) || $_SESSION['vloga'] !== 'ucenec' || $_SESSION['prvi_vpis'] != 1) {
    header('Location: login.php'); // Preusmeri nazaj, če pogoji niso izpolnjeni
    exit();
}

$user_id = $_SESSION['user_id'];
$ime_ucenca = 'UČENEC';
$predmeti_z_ucitelji = [];

try {
    // Pridobi ime učenca
    $sql_ime = "SELECT ime, priimek FROM uporabnik WHERE id_uporabnik = ?";
    $stmt_ime = $pdo->prepare($sql_ime);
    $stmt_ime->execute([$user_id]);
    $ucenec_data = $stmt_ime->fetch();
    if ($ucenec_data) {
        $ime_ucenca = $ucenec_data['ime'] . ' ' . $ucenec_data['priimek'];
    }

    // Pridobi vse predmete in jim dodeli učitelje, ki jih poučujejo
    $sql_predmeti = "SELECT id_predmet, ime_predmeta FROM predmet";
    $stmt_predmeti = $pdo->query($sql_predmeti);
    $vsi_predmeti = $stmt_predmeti->fetchAll();

    foreach ($vsi_predmeti as $predmet) {
        $sql_ucitelji = "
            SELECT u.id_uporabnik, u.ime, u.priimek
            FROM ucitelj_predmet up
            JOIN uporabnik u ON up.id_ucitelj = u.id_uporabnik
            WHERE up.id_predmet = ?
        ";
        $stmt_ucitelji = $pdo->prepare($sql_ucitelji);
        $stmt_ucitelji->execute([$predmet['id_predmet']]);
        $ucitelji = $stmt_ucitelji->fetchAll();

        $predmeti_z_ucitelji[] = [
            'id_predmet' => $predmet['id_predmet'],
            'ime_predmeta' => $predmet['ime_predmeta'],
            'ucitelji' => $ucitelji
        ];
    }

} catch (\PDOException $e) {
    die("Napaka pri bazi: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="sl">
<head>
<link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Momo+Trust+Display&family=Raleway:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Izberi Predmete</title>
  <style>
    body {
      margin: 0;
      font-family: "Raleway", sans-serif;
      /*background: #f4f6f9;*/
    }
    .video-background {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      z-index: -1;
    }
    header {
      background: #cdcdb6;
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 15px 30px;
    }
    header .logo {
      font-weight: bold;
      font-size: 18px;
    }
    nav a {
      margin-left: 20px;
      text-decoration: none;
      color: black;
      font-size: 14px;
    }
    nav a:hover {
      text-decoration: underline;
    }
    .hero {
      color: #3b3d15;
      padding: 50px;
      text-align: center;
      min-height: 80vh;
    }
    .hero h2 {
      font-size: 22px;
      margin-bottom: 10px;
    }
    .hero strong {
      font-size: 26px;
    }
    .card {
      background: #cdcdb6;
      border-style: solid;
      border-color: #3b3d15;
      color: #3b3d15;
      border-radius: 20px;
      padding: 30px;
      max-width: 700px;
      margin: 40px auto;
    }
    .card h3 {
      margin-bottom: 20px;
    }
    .subjects {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 15px;
    }
    .subject {
        background: #80852f;
        border-radius: 8px;
        padding: 12px;
        font-size: 16px;
        font-weight: bold;
        cursor: pointer;
        transition: transform 0.2s;
        /* naj bo nad osnovnim ozadjem */
    }
    .subject:hover {
      transform: scale(1.05);
    }
    /* Dropdown meni */
    .dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid #999;
        border-radius: 6px;
        display: none;
        list-style: none;
        padding: 0;
        margin: 0 0 0 0;
        /* poskrbi da dropdown skoči v ospredje */
    }
    .dropdown li {
      padding: 8px;
      cursor: pointer;
    }
    .dropdown li:hover {
      background: #f0f0f0;
    }
    /* Pokaži dropdown ob hoverju */
    .subject:hover .dropdown {
      display: block;
    }
  </style>
</head>
<body>
  <video class="video-background" autoplay muted loop>
    <source src="./video/green_videoPredmeti.mp4" type="video/mp4">
    Your browser does not support the video tag.
  </video>
  <header>
    <div class="logo">REDOVALNICA</div>
    <div>Prijavljen: **<?php echo htmlspecialchars($ime_ucenca); ?>** | <a href="logout.php">Odjava</a></div>
  </header>

  <section class="hero">
    <h2>Pozdravljen/a,</h2>
    <strong><?php echo htmlspecialchars($ime_ucenca); ?>!</strong>
    <p>Prosim, izberi učitelja za vsak predmet, ki ga želiš obiskovati.</p>

    <div class="card">
      <h3>Izberi predmete in učitelje:</h3>
      <div class="subjects">
        <?php foreach ($predmeti_z_ucitelji as $predmet): ?>
            <div class="subject" data-id-predmet="<?php echo $predmet['id_predmet']; ?>">
                <span><?php echo htmlspecialchars($predmet['ime_predmeta']); ?></span>
                <?php if (!empty($predmet['ucitelji'])): ?>
                    <ul class="dropdown">
                        <?php foreach ($predmet['ucitelji'] as $ucitelj): ?>
                            <li data-id-ucitelj="<?php echo $ucitelj['id_uporabnik']; ?>">
                                <?php echo htmlspecialchars($ucitelj['ime'] . ' ' . $ucitelj['priimek']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p style="font-size: 12px; color: red;">Ni učiteljev</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
      </div>
      <button id="finish-selection" style="display: none;">Zaključi izbiro in nadaljuj v Učilnico</button>
      <p id="feedback" style="color: green; margin-top: 15px;"></p>
    </div>
  </section>

  <script>
    const selectedChoices = {};
    const userId = <?php echo json_encode($user_id); ?>;

    document.querySelectorAll('.dropdown li').forEach(item => {
        item.addEventListener('click', (e) => {
            e.stopPropagation();
            
            const teacherLi = e.target;
            const subjectDiv = teacherLi.closest('.subject');
            const subjectId = subjectDiv.dataset.idPredmet;
            const teacherId = teacherLi.dataset.idUcitelj;
            const teacherName = teacherLi.textContent.trim();
            const subjectNameSpan = subjectDiv.querySelector('span');

            selectedChoices[subjectId] = teacherId;
            
            subjectNameSpan.textContent = subjectNameSpan.textContent.split(' - ')[0] + " - " + teacherName;
            subjectDiv.classList.add('selected');
            
            checkIfAllSelected();
        });
    });

    function checkIfAllSelected() {
        // Če je vsaj eden izbran, prikažemo gumb za zaključek
        if (Object.keys(selectedChoices).length > 0) {
            document.getElementById('finish-selection').style.display = 'block';
        }
    }

    document.getElementById('finish-selection').addEventListener('click', async () => {
        if (Object.keys(selectedChoices).length === 0) {
            alert("Prosim, izberi vsaj en predmet.");
            return;
        }

        const feedbackElement = document.getElementById('feedback');
        feedbackElement.textContent = 'Shranjevanje...';
        
        const dataToSend = {
            user_id: userId,
            choices: selectedChoices
        };

        try {
            const response = await fetch("save_choice.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(dataToSend)
            });
            
            const data = await response.json();
            
            if (data.success) {
                feedbackElement.textContent = "Uspešno shranjeno! Nadaljevanje v Učilnico...";
                window.location.href = 'ucenec_ucilnica.php'; 
            } else {
                feedbackElement.textContent = "Napaka pri shranjevanju: " + data.message;
            }
        } catch (err) {
            feedbackElement.textContent = "Napaka pri komunikaciji s strežnikom.";
            console.error("Error:", err);
        }
    });

  </script>
</body>
</html>