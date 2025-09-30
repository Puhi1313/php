<!DOCTYPE html>
<html lang="sl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Izberi Predmete</title>
  <style>
    body {
      margin: 0;
      font-family: Arial, sans-serif;
      background: #eafaff;
    }
    header {
      background: #ddd;
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
      background: #baf0ff;
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
      background: #ccc;
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
        background: #66ff66;
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
    <h2>Pozdravljen/a,</h2>
    <strong>UČENEC</strong>

    <div class="card">
      <h3>Izberi predmete, ki jih želiš imeti:</h3>
      <div class="subjects">
        <div class="subject" data-subject="Matematika">
          Matematika
          <ul class="dropdown">
            <li data-teacher="Učitelj A">Učitelj A</li>
            <li data-teacher="Učitelj B">Učitelj B</li>
          </ul>
        </div>
        <div class="subject" data-subject="Slovenščina">
          Slovenščina
          <ul class="dropdown">
            <li data-teacher="Učitelj C">Učitelj C</li>
            <li data-teacher="Učitelj D">Učitelj D</li>
          </ul>
        </div>
        <div class="subject" data-subject="Angleščina">
          Angleščina
          <ul class="dropdown">
            <li data-teacher="Učitelj E">Učitelj E</li>
            <li data-teacher="Učitelj F">Učitelj F</li>
          </ul>
        </div>
        <div class="subject" data-subject="Fizika">
          Fizika
          <ul class="dropdown">
            <li data-teacher="Učitelj G">Učitelj G</li>
            <li data-teacher="Učitelj H">Učitelj H</li>
          </ul>
        </div>
        <div class="subject" data-subject="Kemija">
          Kemija
          <ul class="dropdown">
            <li data-teacher="Učitelj I">Učitelj I</li>
            <li data-teacher="Učitelj J">Učitelj J</li>
          </ul>
        </div>
        <div class="subject" data-subject="Zgodovina">
          Zgodovina
          <ul class="dropdown">
            <li data-teacher="Učitelj K">Učitelj K</li>
            <li data-teacher="Učitelj L">Učitelj L</li>
          </ul>
        </div>
        <div class="subject" data-subject="Biologija">
          Biologija
          <ul class="dropdown">
            <li data-teacher="Učitelj M">Učitelj M</li>
            <li data-teacher="Učitelj N">Učitelj N</li>
          </ul>
        </div>
      </div>
    </div>
  </section>

  <script>
    document.querySelectorAll('.dropdown li').forEach(item => {
    item.addEventListener('click', (e) => {
        const subjectDiv = e.target.closest('.subject');
        const subject = subjectDiv.dataset.subject;
        const teacher = e.target.dataset.teacher;

        choices[subject] = teacher;
        localStorage.setItem('choices', JSON.stringify(choices));

        fetch("save_choice.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify({ subject, teacher })
        })
        .then(res => res.json())
        .then(data => {
        console.log("Server response:", data);
        })
        .catch(err => console.error("Error:", err));

        subjectDiv.childNodes[0].textContent = subject + " - " + teacher;
    });
    });

  </script>
  <?php
  
  $host = "localhost";
  $user = "root";   //  spremen tole ti ciglin
  $pass = "";       //  spremen tole ti ciglin
  $dbname = "school"; //  spremen tole ti ciglin
  
  $conn = new mysqli($host, $user, $pass, $dbname);
  
  if ($conn->connect_error) {
      die("Connection failed: " . $conn->connect_error);
  }
  
  $data = json_decode(file_get_contents("php://input"), true);
  
  if (isset($data['subject']) && isset($data['teacher'])) {
      $subject = $conn->real_escape_string($data['subject']);
      $teacher = $conn->real_escape_string($data['teacher']);
  
      $sql = "INSERT INTO choices (subject, teacher) VALUES ('$subject', '$teacher')";
  
      if ($conn->query($sql) === TRUE) {
          echo json_encode(["success" => true, "message" => "Saved successfully"]);
      } else {
          echo json_encode(["success" => false, "message" => $conn->error]);
      }
  } else {
      echo json_encode(["success" => false, "message" => "Invalid input"]);
  }
  
  $conn->close();
  ?>
  
</body>
</html>
