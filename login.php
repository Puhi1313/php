<?php
session_start();
require_once 'povezava.php'; // Vključimo PDO povezavo

$error_message = '';

// Hitra preusmeritev za že prijavljene uporabnike
if (isset($_SESSION['user_id']) && isset($_SESSION['vloga'])) {
    if ($_SESSION['vloga'] === 'ucitelj' || $_SESSION['vloga'] === 'admin') {
        header('Location: ucilnicaPage.php');
        exit();
    } elseif ($_SESSION['vloga'] === 'ucenec') {
        if ($_SESSION['prvi_vpis'] == 1) {
             header('Location: predmetiPage.php');
             exit();
        } else {
             header('Location: ucilnicaPage.php');
             exit();
        }
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $geslo = $_POST['geslo'] ?? ''; 

    if (empty($email) || empty($geslo)) {
        $error_message = 'Vnesite e-mail in geslo.';
    } else {
        try {
            $sql = "SELECT id_uporabnik, vloga, geslo, prvi_vpis FROM uporabnik WHERE email = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$email]);
            $uporabnik = $stmt->fetch();

            if ($uporabnik) {
                // Preverjanje gesla (uporablja nehashrano geslo, kot je v tvojem dumpu)
                if ($uporabnik['geslo'] === $geslo) { 
                    
                    $_SESSION['user_id'] = $uporabnik['id_uporabnik'];
                    $_SESSION['vloga'] = $uporabnik['vloga'];
                    $_SESSION['prvi_vpis'] = $uporabnik['prvi_vpis'];

                    // Logika preusmeritve
                    if ($uporabnik['vloga'] === 'ucitelj' || $uporabnik['vloga'] === 'admin') {
                        header('Location: ucilnicaPage.php');
                        exit();
                        
                    } elseif ($uporabnik['vloga'] === 'ucenec') {
                        if ($uporabnik['prvi_vpis'] == 1) {
                            header('Location: predmetiPage.php');
                            exit();
                        } else {
                            header('Location: ucilnicaPage.php');
                            exit();
                        }
                    }

                } else {
                    $error_message = 'Napačno geslo.';
                }
            } else {
                $error_message = 'Uporabnik s tem e-mailom ne obstaja.';
            }

        } catch (\PDOException $e) {
            $error_message = "Napaka pri prijavi.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="UTF-8">
    <title>Prijava v Redovalnico</title>
    <style>
    body {
      margin: 0;
      font-family: Arial, sans-serif;
    }
    header {
      background: #9368b7;
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 10px 20px;
    }
    header .logo {
      font-weight: bold;
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
      background: #95b3f1;
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 80px 10%;
      min-height: 70vh;
      box-sizing: border-box;
    }
    .hero .welcome {
      font-size: 42px;
      font-weight: bold;
      max-width: 400px;
    }
    .hero .login {
      background: #ddd;
      padding: 40px;
      border-radius: 12px;
      width: 350px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
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
    .login input{
      width: 100%;
      padding: 12px;
      border: 1px solid #ccc;
      border-radius: 5px;
      font-size: 15px;
    }
    .login button {
      background: #9368b7;
      color: white;
      padding: 14px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      width: 100%;
      font-size: 16px;
    }
    .login button:hover {
      background: #333;
    }
    .features {
      display: flex;
      justify-content: space-around;
      align-items: flex-start;
      padding: 60px 10%;
      gap: 20px;
    }
    .feature {
      flex: 1;
      text-align: center;
    }
    .feature .circle {
      width: 80px;
      height: 80px;
      background: #ddd;
      border-radius: 50%;
      margin: 0 auto 20px;
    }
    .feature .box {
      width: 150px;
      height: 70px;
      background: #ddd;
      margin: 0 auto;
    }
    .section {
      background: #ddd;
      height: 200px;
      margin: 40px 10%;
      border-radius: 10px;
    }
  </style>
</head>
<body>

    <h2>Prijava</h2>

    <?php if ($error_message): ?>
        <p style="color: red;"><?php echo htmlspecialchars($error_message); ?></p>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <div>
            <label for="email">E-mail:</label>
            <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>
        <div>
            <label for="geslo">Geslo:</label>
            <input type="password" id="geslo" name="geslo" required>
        </div>
        <button type="submit">Prijava</button>
    </form>
</body>
</html>