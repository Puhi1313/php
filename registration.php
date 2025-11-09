<?php
session_start();
// Preverimo, ali je vključena povezava z bazo
require_once 'povezava.php'; 

$error_message = '';
$success_message = '';
// Inicializacija spremenljivk, da se prikažejo v formi ob napaki
$ime = $priimek = $mesto = $kontakt_email = ''; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Pridobitev podatkov iz forme (uporabimo ?? '' za zaščito pred manjkajočimi ključi)
    $ime = trim($_POST['ime'] ?? '');
    $priimek = trim($_POST['priimek'] ?? '');
    $mesto = trim($_POST['mesto'] ?? '');
    // POPRAVEK: Uporaba kontakt_email namesto "email"
    $kontakt_email = trim($_POST['kontakt_email'] ?? ''); 
    $geslo = $_POST['geslo'] ?? '';
    $geslo_ponovno = $_POST['geslo_ponovno'] ?? '';
    
    // Vloga se privzeto nastavi na 'ucenec', saj ni izbirnega polja v formi.
    $vloga = 'ucenec'; 

    // VALIDACIJA
    if (empty($ime) || empty($priimek) || empty($geslo) || empty($kontakt_email) || empty($mesto) || empty($geslo_ponovno)) {
        $error_message = "Vsa polja so obvezna!";
    } elseif ($geslo !== $geslo_ponovno) {
        $error_message = "Gesli se ne ujemata.";
    } elseif (strlen($geslo) < 12) {
        $error_message = "Geslo mora imeti vsaj 12 znakov.";
    } else {
        
        // --- LOGIKA ZA GENERIRANJE ŠOLSKEGA E-MAILA ---
        
        // 1. OČISTIMO in NORMALIZIRAMO ime in priimek za e-mail
        // Odstranimo šumnike (č, š, ž) in presledke ter pretvorimo v male črke
        $ime_clean = strtolower(preg_replace('/[^a-z0-9]/i', '', iconv('utf-8', 'us-ascii//TRANSLIT', $ime)));
        $priimek_clean = strtolower(preg_replace('/[^a-z0-9]/i', '', iconv('utf-8', 'us-ascii//TRANSLIT', $priimek)));

        // 2. GENERIRANJE PRIMARNEGA ŠOLSKEGA E-MAILA
        $skolski_email_base = $ime_clean . '.' . $priimek_clean . '@sola.si';
        $skolski_email = $skolski_email_base;
        $counter = 1;
        $email_exists = true;

        // 3. PREVERJANJE DUPLIKATOV V BAZI
        try {
            while ($email_exists) {
                $sql_check = "SELECT COUNT(*) FROM uporabnik WHERE email = ?";
                $stmt_check = $pdo->prepare($sql_check);
                $stmt_check->execute([$skolski_email]);
                
                if ($stmt_check->fetchColumn() == 0) {
                    $email_exists = false; // Našli smo edinstven e-mail
                } else {
                    // Duplikat obstaja, dodamo številko na konec (npr. janez.novak2@sola.si)
                    $counter++;
                    $skolski_email = $ime_clean . '.' . $priimek_clean . $counter . '@sola.si';
                }
            }

            // 4. HASHERANJE GESLA in VSTAVITEV UPORABNIKA
            $hashirano_geslo = password_hash($geslo, PASSWORD_DEFAULT);
            
            // OPOMBA: V tabelo vstavljamo generirani ŠOLSKI e-mail!
            // Če nimate stolpca 'mesto' v tabeli 'uporabnik', ga odstranite iz SQL stavka!
            $sql_insert = "INSERT INTO uporabnik (ime, priimek, email, geslo, vloga, status, prvi_vpis, mesto) 
                           VALUES (?, ?, ?, ?, ?, 'pending', 1, ?)"; 
            $stmt_insert = $pdo->prepare($sql_insert);
            
            // Vstavitev podatkov
            $stmt_insert->execute([$ime, $priimek, $skolski_email, $hashirano_geslo, $vloga, $mesto]);

            // Uspešna registracija - Povemo mu, kaj je njegov šolski e-mail!
            $success_message = "Registracija uspešna! Vaš PRIJAVNI E-MAIL je: **" . htmlspecialchars($skolski_email) . "**";

            // Ponastavimo spremenljivke za formo
            $ime = $priimek = $mesto = $kontakt_email = '';
            
        } catch (\PDOException $e) {
            $error_message = 'Napaka pri registraciji: ' . $e->getMessage();
            error_log("Registration Error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="sl">
<head>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Momo+Trust+Display&family=Raleway:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Registracija</title>
  <style>
    body {
      margin: 0;
      font-family: "Raleway", sans-serif;
      background: #f4f6f9;
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
      font-size: 20px;
      color: #596235;
    }
    nav {
      display: flex;
      gap: 12px;
      align-items: center;
    }
    nav a {
      text-decoration: none;
      color: #596235;
      font-size: 15px;
      font-weight: 500;
      padding: 8px 16px;
      border-radius: 8px;
      transition: all 0.3s ease;
      position: relative;
    }
    nav a:hover {
      background: rgba(89, 98, 53, 0.1);
      transform: translateY(-1px);
    }
    nav a:active {
      transform: translateY(0);
    }

    .container {
      display: flex;
      justify-content: center;
      margin-top: 150px;
    }

    .form-wrapper {
      background: #ffffff;
      display: flex;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
      overflow: hidden;
      width: 800px;
    }

    .form-left {
      flex: 1;
      padding: 40px;
      background: #cdcdb6;
    }
    .form-left h2 {
      margin-top: 0;
      margin-bottom: 20px;
      text-align: center;
      color: #3b3d15;
    }
    .form-left label {
      display: block;
      margin-bottom: 6px;
      font-size: 14px;
      color: #3b3d15;
    }
    .form-left input {
      width: 100%;
      padding: 10px;
      margin-bottom: 20px;
      border: 1px solid #ccc;
      border-radius: 6px;
      font-size: 14px;
    }
    .form-left button {
      width: 100%;
      padding: 12px;
      background: #596235;
      color: white;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-size: 16px;
    }
    .form-left button:hover {
      background: #1a252f;
    }

    .form-right {
      flex: 1;
      background-image: url("slike/corporate_memphis1.png");
      background-size: cover; 
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      color: #666;
    }
  </style>
</head>
<body>
  <header>
    <div class="logo">LOGO</div>
    <nav>
      <a href="login.php">Prijava</a>
      <a href="registration.php">Registracija</a>
    </nav>
  </header>

  <div class="container">
    <div class="form-wrapper">
      <div class="form-left">
        <h2>Zahteva za registracijo</h2>
        
        <?php if ($error_message): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <p class="success-message"><?php echo htmlspecialchars($success_message); ?></p>
        <?php endif; ?>

        <form method="POST" action="registration.php">
          <label for="ime">Ime</label>
          <input type="text" id="ime" name="ime" placeholder="Vnesite ime" value="<?php echo htmlspecialchars($ime); ?>" required>

          <label for="priimek">Priimek</label>
          <input type="text" id="priimek" name="priimek" placeholder="Vnesite priimek" value="<?php echo htmlspecialchars($priimek); ?>" required>
          
          <label for="mesto">Mesto</label>
          <input type="text" id="mesto" name="mesto" placeholder="Vnesite mesto bivanja" value="<?php echo htmlspecialchars($mesto); ?>" required>

          <label for="kontakt_email">E-mail za kontakt</label>
          <input type="email" id="kontakt_email" name="kontakt_email" placeholder="Vnesite e-mail za kontakt" value="<?php echo htmlspecialchars($kontakt_email); ?>" required>

          <label for="geslo">Geslo</label>
          <input type="password" id="geslo" name="geslo" placeholder="Vnesite geslo" required>
          
          <label for="geslo_ponovno">Ponovi Geslo</label>
          <input type="password" id="geslo_ponovno" name="geslo_ponovno" placeholder="Ponovno vnesite geslo" required>
          
          <button type="submit">Zahtevaj registracijo</button>
          
          <p style="margin-top: 15px; text-align: center;">Ste že registrirani? <a href="login.php">Prijava</a></p>
        </form>
      </div>
      <div class="form-right">
        </div>
    </div>
  </div>
</body>
</html>