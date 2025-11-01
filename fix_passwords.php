<?php
// Vključi povezavo z bazo, da lahko dostopaš do $pdo
require_once 'povezava.php'; 

// --- 1. NASTAVITEV GESEL IN GENERIRANJE HASHEV ---

// Geslo za ADMINA
$admin_geslo = 'Admin1234';
$admin_hash = password_hash($admin_geslo, PASSWORD_DEFAULT);

// Geslo za UČITELJE/UČENCE
$default_geslo = 'test';
$default_hash = password_hash($default_geslo, PASSWORD_DEFAULT);


// --- 2. POSODOBITEV ADMINA ---
try {
    $sql_admin = "
        UPDATE uporabnik 
        SET geslo = ?, status = 'active', prvi_vpis = 0 
        WHERE vloga = 'admin'
    ";
    $stmt_admin = $pdo->prepare($sql_admin);
    $stmt_admin->execute([$admin_hash]);
    echo "<li>ADMIN posodobljen. Geslo: **" . htmlspecialchars($admin_geslo) . "**</li>";
} catch (\PDOException $e) {
    echo "<li style='color: red;'>NAPAKA pri posodabljanju ADMINA: " . $e->getMessage() . "</li>";
}


// --- 3. POSODOBITEV UČITELJEV IN UČENCEV ---
try {
    $sql_default = "
        UPDATE uporabnik 
        SET geslo = ?
        WHERE vloga = 'ucitelj' OR vloga = 'ucenec'
    ";
    $stmt_default = $pdo->prepare($sql_default);
    $stmt_default->execute([$default_hash]);
    $count = $stmt_default->rowCount();
    
    echo "<li>UČITELJI in UČENCI posodobljeni (" . $count . " uporabnikov). Geslo: **" . htmlspecialchars($default_geslo) . "**</li>";
} catch (\PDOException $e) {
    echo "<li style='color: red;'>NAPAKA pri posodabljanju UČITELJEV/UČENCEV: " . $e->getMessage() . "</li>";
}

// Preverjanje za potrditev, da je hash pravilen
if (password_verify($default_geslo, $default_hash)) {
    echo "<p style='color: green;'>**POTRDITEV:** Generirani hashi so pravilni!</p>";
} else {
    echo "<p style='color: red;'>**NAPAKA:** Funkcija password_verify ne deluje pravilno na tem strežniku.</p>";
}

?>

<h2 style="color: red;">OBVEZNO IZBRIŠI TO DATOTEKO PO UPORABI!</h2>