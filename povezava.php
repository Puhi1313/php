<?php
// povezava.php
// Nastavitve za bazo podatkov
$host = 'localhost'; // Ali ip naslov strežnika
$db   = 'redovalnica_test1'; // Ime tvoje baze
$user = 'root'; // Uporabniško ime (če uporabljaš XAMPP/WAMP, je to običajno 'root')
$pass = ''; // Geslo (če uporabljaš XAMPP/WAMP, je to običajno prazno)
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     // Ustvarimo povezavo s PDO
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     // V primeru napake izpišemo napako in prekinemo izvajanje
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>