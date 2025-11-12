<?php
// ---------------------------
// 🔐 VARNOSTNE NASTAVITVE
// ---------------------------
session_set_cookie_params([
    'lifetime' => 0, // sejni piškotek - velja do zaprtja brskalnika
    'path' => '/',
    'domain' => 'projekt-smv.kesug.com', // <-- prilagodi svoji domeni
    'secure' => true, // samo prek HTTPS
    'httponly' => true, // JS ne more dostopati
    'samesite' => 'Strict' // prepreči CSRF prek tujih strani
]);
session_start();

require_once 'povezava.php'; // mora imeti $pdo (PDO povezavo)

$error_message = '';

// Če je uporabnik že prijavljen, preusmeri na pravo stran
if (isset($_SESSION['user_id']) && isset($_SESSION['vloga'])) {
    switch ($_SESSION['vloga']) {
        case 'admin':
            header('Location: adminPage.php');
            exit;
        case 'ucitelj':
            header('Location: ucitelj_ucilnica.php');
            exit;
        case 'ucenec':
            if ($_SESSION['prvi_vpis'] == 1) {
                header('Location: predmetiPage.php');
            } else {
                header('Location: ucenec_ucilnica.php');
            }
            exit;
    }
}

// ---------------------------
// 🧠 OBDELAVA PRIJAVE
// ---------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $geslo = $_POST['geslo'] ?? '';
    $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';

    // Preveri, da polja niso prazna
    if (empty($email) || empty($geslo)) {
        $error_message = 'Vnesite e-mail in geslo.';
    } else {
        // ---------------------------
        // ✅ reCAPTCHA PREVERJANJE
        // ---------------------------
       // $secretKey = '6LeVkwosAAAAAA16FyZVxsPjxkIQyhHYMXb13nPU'; // <-- sem dodaj svoj secret key
        //$verify = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$secretKey}&response={$recaptchaResponse}");
       // $captchaSuccess = json_decode($verify);

       // if (!$captchaSuccess->success) {
       //     $error_message = 'Potrdite, da niste robot.';
       // } else {
            // ---------------------------
            // 🧩 PREVERJANJE UPORABNIKA
            // ---------------------------
            try {
                $stmt = $pdo->prepare("SELECT id_uporabnik, vloga, geslo, prvi_vpis, status 
                                       FROM uporabnik 
                                       WHERE email = ?");
                $stmt->execute([$email]);
                $uporabnik = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($uporabnik) {
                    if (password_verify($geslo, $uporabnik['geslo'])) {
                        if ($uporabnik['status'] !== 'active') {
                            $error_message = 'Vaš račun še ni aktiven. Kontaktirajte administratorja.';
                        } else {
                            // 🟢 USPEŠNA PRIJAVA
                            $_SESSION['user_id'] = $uporabnik['id_uporabnik'];
                            $_SESSION['vloga'] = $uporabnik['vloga'];
                            $_SESSION['prvi_vpis'] = $uporabnik['prvi_vpis'];

                            // Preusmeri glede na vlogo
                            switch ($uporabnik['vloga']) {
                                case 'admin':
                                    header('Location: adminPage.php');
                                    break;
                                case 'ucitelj':
                                    header('Location: ucitelj_ucilnica.php');
                                    break;
                                case 'ucenec':
                                    if ($uporabnik['prvi_vpis'] == 1) {
                                        header('Location: predmetiPage.php');
                                    } else {
                                        header('Location: ucenec_ucilnica.php');
                                    }
                                    break;
                            }
                            exit;
                        }
                    } else {
                        $error_message = 'Napačno geslo.';
                    }
                } else {
                    $error_message = 'Uporabnik s tem e-mailom ne obstaja.';
                }
            } catch (PDOException $e) {
                error_log("Login Error: " . $e->getMessage());
                $error_message = 'Pri prijavi je prišlo do napake. Poskusite znova.';
            }
       // }
    }
}
?>



<!DOCTYPE html>
<html lang="sl">
<head>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=BBH+Sans+Hegarty&family=Climate+Crisis:YEAR@2009&display=swap" rel="stylesheet">
  <meta charset="UTF-8" />

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Momo+Trust+Display&family=Raleway:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta charset="UTF-8">
    <title>Prijava v Redovalnico</title>
    <style>
    html {
      color: #596235;
    }
    body {
      background-color: #cdcdb6;
      margin: 0;
      font-family: "Raleway", sans-serif;
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
    .welcome{
        color: #cdcdb6;
        text-shadow: 0 2px 8px rgba(0,0,0,0.15);
    }
    .hero {
        background-image: url(slike/misty_forest.jpg);
        background-size: cover;       /* makes image fill the section */
        background-repeat: no-repeat; /* prevents tiling/repetition */
        background-position: center;  /* keeps it centered */
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 80px 10%;
        min-height: 70vh;
        box-sizing: border-box;
    }
    .hero .welcome {
      font-family: "BBH Sans Hegarty", sans-serif;
      font-size: 42px;
      font-weight: bold;
      max-width: 400px;
    }
    .hero .login {
      background: #cdcdb6;
      padding: 40px;
      border-radius: 12px;
      width: 350px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      display: flex;
      flex-direction: column;
      gap: 20px;
    }
    .login h3 {
      margin: 0;
      font-size: 22px;
      text-align: center;
    }
    .login label {
      font-size: 14px;
      margin-bottom: 5px;
    }
    .login input {
      width: 93%;
      padding: 12px;
      border: 1px solid #ccc;
      border-radius: 5px;
      font-size: 15px;
    }
    .login button {
      background: #596235;
      color: white;
      padding: 14px;
      margin-top: 40px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      width: 100%;
      font-family: "BBH Sans Hegarty", sans-serif;
      font-size: 16px;
    }
    .login button:hover {
      background: #333;
    }
    .features {
      background-color: #cdcdb6;
      padding: 20px 10%;
      text-align: center;
    }

    .features-title {
      font-family: "Raleway", sans-serif;
      font-size: 36px;
      color: #596235;
      margin-bottom: 40px;
    }

    .features-row {
      display: flex;
      justify-content: space-around;
      align-items: flex-start;
      gap: 20px;
    }

    .feature {
      flex: 1;
      text-align: center;
      position: relative;
    }
    .circle {
      width: 80px;
      height: 80px;
      transition: 500ms;
      background: #ddd;
      border-radius: 50%;
      margin: 0 auto 20px;
      position: relative;
      display: flex;
      justify-content: center;
      align-items: center;
    }
    .circle:hover {
      width: 150px;
      height: 150px;
    }
    .hover-text {
      opacity: 0;
      transition: opacity 300ms ease;
      font-size: 16px;
      color: #596235;
      position: absolute;
      text-align: center;
    }
    .circle:hover .hover-text {
      opacity: 1;
    }
    .feature .box {
      width: 150px;
      height: 70px;
      border-radius: 10%;
      background: #ddd;
      margin: 0 auto;
    }
    .section {
      background: #cdcdb6;
      height: 200px;
      margin: 40px 10%;
      border-radius: 10px;
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

    <section class="hero">
        <div class="welcome">WELCOME</div>
        <div class="login">
            <h3>Prijava</h3>

            <!-- Error message display -->
            <?php if ($error_message): ?>
                <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div>
                    <label for="email">E-mail:</label>
                    <input type="email" placeholder="Enter your email" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                <div>
                    <label for="geslo">Geslo:</label>
                    <input type="password" placeholder="Enter your password" id="geslo" name="geslo" required>
                </div>
               <!-- <div class="g-recaptcha" data-sitekey="6LeVkwosAAAAADztMG0NMVDNvlu2T-w2KPytYOl7"></div> -->
                <!--<script src="https://www.google.com/recaptcha/api.js" async defer></script> -->
                <button type="submit">Prijava</button>
            </form>
            <p style="margin-top: 15px;">Še nimate računa? <a href="registration.php">Registracija</a></p>
        </div>
    </section>

    <section class="features">
      <h1 class="features-title">Naše Prednosti</h1>
      <div class="features-row">
        <div class="feature">
          <div class="circle">
            <span class="hover-text">Svetovno znani učitelji</span>
          </div>
        </div>
        <div class="feature">
          <div class="circle">
            <span class="hover-text">Veliko možnosti izobrazbe</span>
          </div>
        </div>
        <div class="feature">
          <div class="circle">
            <span class="hover-text">Visoka kvantiteta uspešnosti učencev</span>
          </div>
        </div>
      </div>
    </section>
</body>
</html>