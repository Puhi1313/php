<?php
session_start();
require_once 'povezava.php'; // Include PDO connection

$error_message = '';

// Quick redirect for already logged-in users
if (isset($_SESSION['user_id']) && isset($_SESSION['vloga'])) {
    if ($_SESSION['vloga'] === 'admin') { // DODANO: Admin gre naravnost na adminPage
        header('Location: adminPage.php');
        exit();
    }
    if ($_SESSION['vloga'] === 'ucitelj') {
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
            // DODANO: Pri pridobivanju podatkov dodamo tudi 'status'
            $sql = "SELECT id_uporabnik, vloga, geslo, prvi_vpis, status FROM uporabnik WHERE email = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$email]);
            $uporabnik = $stmt->fetch();
            
            // ZAČASNO DEBUGIRANJE - ODSTRANITE, KO BO DELOVALO!
            if ($uporabnik) {
                // 1. Izpiši vneseno geslo in hash iz baze
                error_log("Preverjam: Vneseno: " . $geslo . " | Hash v DB: " . $uporabnik['geslo']);

                // 2. Preveri, ali password_verify deluje
                if (password_verify($geslo, $uporabnik['geslo'])) {
                    error_log("DEBUG: PASSWORD VERIFY JE VRNIL TRUE!");
                } else {
                    error_log("DEBUG: PASSWORD VERIFY JE VRNIL FALSE!");
                }
            }
            // KONEC ZAČASNEGA DEBUGIRANJA

            if ($uporabnik) {
                if (password_verify($geslo, $uporabnik['geslo'])) {
                    
                    // Preveri status
                    if ($uporabnik['status'] !== 'active') {
                         $error_message = 'Vaš račun je še v obravnavi ali je bil zavrnjen. Kontaktirajte administratorja.';
                    } else {
                        // USPEŠNA PRIJAVA in AKTIVEN STATUS
                        $_SESSION['user_id'] = $uporabnik['id_uporabnik'];
                        $_SESSION['vloga'] = $uporabnik['vloga'];
                        $_SESSION['prvi_vpis'] = $uporabnik['prvi_vpis'];
                        
                        // PREUSMERITEV NA NOVE STRANI
                        if ($uporabnik['vloga'] === 'admin') {
                            header('Location: adminPage.php');
                            exit();
                        } elseif ($uporabnik['vloga'] === 'ucitelj') {
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
                    } // konec preverjanja statusa
                } else {
                    $error_message = 'Napačno geslo.';
                }
            } else {
                $error_message = 'Uporabnik s tem e-mail naslovom ne obstaja.';
            }

        } catch (\PDOException $e) {
            $error_message = 'Napaka pri prijavi. Poskusite znova.';
            error_log("Login Error: " . $e->getMessage()); 
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
        background: #cdcdb6;
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
        background: #d96846;
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
        background: #cdcdb6;
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
        background: #596235;
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
    .error-message {
        color: red;
        font-size: 14px;
        text-align: center;
    }
    </style>
</head>
<body>

    <header>
        <div class="logo">LOGO</div>
        <nav>
            <a href="#stran1">stran1</a>
            <a href="#stran2">stran2</a>
            <a href="#stran3">stran3</a>
            <a href="#stran4">stran4</a>
        </nav>
    </header>

    <section class="hero">
        <div class="welcome">WELLCOME</div>
        <div class="login">
            <h3>Prijava</h3>

            <!-- Error message display -->
            <?php if ($error_message): ?>
                <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
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
            <p style="margin-top: 15px;">Še nimate računa? <a href="registration.php">Registracija</a></p>
        </div>
    </section>

    <section class="features">
        <div class="feature">
            <div class="circle"></div>
            <div class="box"></div>
        </div>
        <div class="feature">
            <div class="circle"></div>
            <div class="box"></div>
        </div>
        <div class="feature">
            <div class="circle"></div>
            <div class="box"></div>
        </div>
    </section>

    <section id="stran1" class="section"></section>

</body>
</html>