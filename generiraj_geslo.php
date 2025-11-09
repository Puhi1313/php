<?php
$testno_geslo = 'geslozaadmin'; // Geslo, s katerim se boste prijavili
$hash = password_hash($testno_geslo, PASSWORD_DEFAULT);

echo "Geslo, ki ga vnašate: " . $testno_geslo . "<br>";
echo "Generirani hash: " . $hash . "<br>";
echo "Preverjanje (mora biti 1/true): " . (password_verify($testno_geslo, $hash) ? '1' : '0') . "<br>";
echo "<br>Skopirajte točen niz od '$' naprej in posodobite bazo!";
?>