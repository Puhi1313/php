<?php
session_start();

// če ni prijavljen, ga vrzi ven
if (!isset($_SESSION["id_uporabnik"])) {
    header("Location: index.php");
    exit;
}

// različne vloge
$vloga = $_SESSION["vloga"];

if ($vloga == "ucitelj") {
    echo "<h1>Dobrodošel učitelj!</h1>";
    // tu bo npr. urnik in naloge za učitelje
} elseif ($vloga == "dijak") {
    echo "<h1>Dobrodošel dijak!</h1>";
    // tu bo seznam predmetov za dijaka
} else {
    echo "<h1>Dobrodošli v sistem!</h1>";
}
?>
