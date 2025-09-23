
<?php
session_start();

// Če je bil obrazec poslan
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST["username"];
    $geslo = $_POST["password"];

    // povezava na bazo (prilagodi podatke)
    $conn = new mysqli("localhost", "root", "", "redovalnica_test1");
    if ($conn->connect_error) {
        die("Povezava ni uspela: " . $conn->connect_error);
    }

    // preverimo uporabnika
    $stmt = $conn->prepare("SELECT id_uporabnik, vloga, geslo FROM uporabnik WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // ⚠️ zaenkrat predvidevamo da je geslo shranjeno kot tekst (kasneje boš uporabil password_hash)
        if ($geslo === $row["geslo"]) {
            // shrani v sejo
            $_SESSION["id_uporabnik"] = $row["id_uporabnik"];
            $_SESSION["vloga"] = $row["vloga"];

            // preusmeritev na main.php
            header("Location: main.php");
            exit;
        } else {
            echo "<script>alert('Napačno geslo');</script>";
        }
    } else {
        echo  "<script>alert('Uporabnik ne obstaja');</script>";
    }
}
?>






<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Mockup to Website</title>
  <style>
    body {
      margin: 0;
      font-family: Arial, sans-serif;
    }
    header {
      background: #e0e0e0;
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
      background: #555;
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
    <form action="index.php" method="post">
        <div class="welcome">WELLCOME</div>
        <div class="login">
        <h3>Prijava</h3>
        <div>
            <label>Email</label>
            <input type="email" placeholder="Enter your email" name = "username">
        </div>
        <div>
            <label>Password</label>
            <input type="password" placeholder="Enter your password" name = "password">
        </div>
        <input type="submit" value = "SUBMIT">
        </div>
    </form>
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


