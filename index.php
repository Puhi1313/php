<?php
/**
 * index.php
 * Glavna vstopna točka aplikacije.
 * Preusmeri uporabnika na prijavno stran.
 */

// Takoj preusmeri na login.php
header('Location: login.php');
exit();
?>