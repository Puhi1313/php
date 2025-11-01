<?php
session_start();
require_once 'povezava.php';

// Preverjanje prijave in preusmeritev
if (!isset($_SESSION['user_id']) || !isset($_SESSION['vloga'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$vloga = $_SESSION['vloga'];
$ime_priimek = 'Neznan uporabnik';
$urnik = [];

try {
    // Pridobitev imena in priimka
    $sql_ime = "SELECT ime, priimek FROM uporabnik WHERE id_uporabnik = ?";
    $stmt_ime = $pdo->prepare($sql_ime);
    $stmt_ime->execute([$user_id]);
    $uporabnik_data = $stmt_ime->fetch();
    $ime_priimek = $uporabnik_data ? $uporabnik_data['ime'] . ' ' . $uporabnik_data['priimek'] : 'Neznan uporabnik';

    // Pridobitev seznama predmetov (glavni menu na levi)
    $predmeti_menu = [];
    if ($vloga === 'ucenec') {
        $sql_predmeti = "
            SELECT p.id_predmet, p.ime_predmeta, u.id_uporabnik AS id_ucitelja, u.ime AS ime_ucitelja, u.priimek AS priimek_ucitelja
            FROM ucenec_predmet up
            JOIN predmet p ON up.id_predmet = p.id_predmet
            JOIN uporabnik u ON up.id_ucitelj = u.id_uporabnik
            WHERE up.id_ucenec = ?
            ORDER BY p.ime_predmeta
        ";
        $stmt_predmeti = $pdo->prepare($sql_predmeti);
        $stmt_predmeti->execute([$user_id]);
        $predmeti_menu = $stmt_predmeti->fetchAll();

        // UČENEC: Pridobi urnik, filtriran glede na učitelja, ki mu je dodeljen ta predmet
        $sql_urnik = "
            SELECT ur.dan, ur.ura, p.ime_predmeta, p.id_predmet, u.id_uporabnik AS id_ucitelja, u.ime AS ime_ucitelja, u.priimek AS priimek_ucitelja
            FROM ucenec_urnik ucur
            JOIN urnik ur ON ucur.id_predmet = ur.id_predmet AND ucur.id_ucitelj = ur.id_ucitelj
            JOIN predmet p ON ur.id_predmet = p.id_predmet
            JOIN uporabnik u ON ur.id_ucitelj = u.id_uporabnik
            WHERE ucur.id_ucenec = ?
            ORDER BY FIELD(dan, 'Ponedeljek', 'Torek', 'Sreda', 'Četrtek', 'Petek'), ura
        ";
        $stmt_urnik = $pdo->prepare($sql_urnik);
        $stmt_urnik->execute([$user_id]);
        $urnik_raw = $stmt_urnik->fetchAll();
        
        // Grupiranje urnika po dnevih
        $urnik = [];
        foreach ($urnik_raw as $ura) {
            $urnik[$ura['dan']][] = $ura;
        }

    } else {
        // UČITELJ (in ADMIN): Pridobi predmete, ki jih poučuje/ureja
        $sql_predmeti = "
            SELECT p.id_predmet, p.ime_predmeta, up.id_uporabnik AS id_ucitelja, up.ime AS ime_ucitelja, up.priimek AS priimek_ucitelja
            FROM ucitelj_predmet ucp
            JOIN predmet p ON ucp.id_predmet = p.id_predmet
            JOIN uporabnik up ON ucp.id_ucitelj = up.id_uporabnik
            WHERE ucp.id_ucitelj = ?
            ORDER BY p.ime_predmeta
        ";
        $stmt_predmeti = $pdo->prepare($sql_predmeti);
        $stmt_predmeti->execute([$user_id]);
        $predmeti_menu = $stmt_predmeti->fetchAll();
    }

} catch (\PDOException $e) {
    $ime_priimek = 'Napaka pri pridobivanju podatkov.';
    error_log("Database Error in ucilnicaPage: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Učilnica</title>
    <style>
        /* STILI */
        body { margin: 0; font-family: Arial, sans-serif; background: #f4f6f9; display: flex; }
        header { background: #343a40; color: white; display: flex; justify-content: space-between; align-items: center; padding: 15px 30px; width: 100%; position: fixed; top: 0; z-index: 1000; }
        header .logo { font-weight: bold; font-size: 18px; }
        .container { display: flex; width: 100%; margin-top: 60px; /* Offset for fixed header */ }
        /* SIDEBAR (PREDMETI) */
        .sidebar { width: 250px; background: #ffffff; padding: 20px; box-shadow: 2px 0 5px rgba(0,0,0,0.1); height: calc(100vh - 60px); position: fixed; left: 0; overflow-y: auto; }
        .sidebar h3 { margin-top: 0; border-bottom: 2px solid #ddd; padding-bottom: 10px; margin-bottom: 15px; }
        .predmeti-list { list-style: none; padding: 0; }
        .predmet-item { padding: 10px; cursor: pointer; border-radius: 4px; margin-bottom: 5px; background: #f8f9fa; transition: background 0.2s; }
        .predmet-item:hover, .predmet-item.active { background: #e9ecef; }
        .predmet-item span { display: block; font-size: 0.9em; color: #6c757d; }
        /* CONTENT (URNIK/NALOGE) */
        .content { flex-grow: 1; margin-left: 250px; padding: 20px; }
        .urnik { margin-bottom: 30px; }
        .urnik h3 { border-bottom: 2px solid #ddd; padding-bottom: 10px; margin-bottom: 15px; }
        .accordion .day { border: 1px solid #ccc; margin-bottom: 10px; border-radius: 6px; overflow: hidden; }
        .day-header { background: #fff; padding: 15px; font-weight: bold; cursor: pointer; display: flex; justify-content: space-between; align-items: center; }
        .day-content { display: none; padding: 15px; background: #f4f4f4; }
        .day-content ul { margin: 0; padding-left: 20px; list-style-type: none; }
        .day-content li { margin-bottom: 5px; }
        .day-content li a { text-decoration: none; color: #007bff; }
        .day-content li a:hover { text-decoration: underline; }
        .day.open .day-content { display: block; }
        .arrow { transition: transform 0.3s; }
        .day.open .arrow { transform: rotate(90deg); }
        /* NALOGE CONTENT */
        #content-details { border: 1px solid #dee2e6; padding: 20px; border-radius: 6px; background: #fff; min-height: 400px; }
        .btn { background: #007bff; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; margin-top: 10px; }
        .btn-danger { background: #dc3545; }
        .btn:hover { opacity: 0.9; }
        .naloga-detajli h4 { margin-top: 20px; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        .oddaja-form textarea, .oddaja-form input[type="text"], .oddaja-form input[type="datetime-local"] {
            width: 100%; padding: 8px; margin-bottom: 10px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px;
        }
        .modal {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); display: none; justify-content: center; align-items: center; z-index: 1001;
        }
        .modal-content {
            background: white; padding: 20px; border-radius: 8px; width: 90%; max-width: 800px; max-height: 90vh; overflow-y: auto; position: relative;
        }
        .modal-close {
            position: absolute; top: 10px; right: 20px; font-size: 30px; font-weight: bold; cursor: pointer;
        }
    </style>
</head>
<body>

<header>
    <div class="logo">E-Učilnica</div>
    <div>Prijavljen: **<?php echo htmlspecialchars($ime_priimek); ?>** (Vloga: **<?php echo htmlspecialchars(ucfirst($vloga)); ?>**) | <a href="logout.php" style="color: #ffc107; text-decoration: none;">Odjava</a></div>
</header>

<div class="container">
    <aside class="sidebar">
        <h3>Predmeti</h3>
        <ul class="predmeti-list">
        <?php foreach ($predmeti_menu as $p): ?>
            <li 
                class="predmet-item" 
                data-id-predmet="<?php echo $p['id_predmet']; ?>" 
                data-id-ucitelja="<?php echo $p['id_ucitelja']; ?>"
                data-ime-predmeta="<?php echo htmlspecialchars($p['ime_predmeta']); ?>"
                data-ime-ucitelja="<?php echo htmlspecialchars($p['ime_ucitelja'] . ' ' . $p['priimek_ucitelja']); ?>"
            >
                <?php echo htmlspecialchars($p['ime_predmeta']); ?>
                <span>Učitelj: <?php echo htmlspecialchars($p['ime_ucitelja'] . ' ' . $p['priimek_ucitelja']); ?></span>
            </li>
        <?php endforeach; ?>
        </ul>
    </aside>

    <main class="content">

        <div class="urnik">
            <h3>Urnik</h3>
            <div class="accordion">
            <?php foreach ($urnik as $dan => $ure): ?>
                <div class="day">
                    <div class="day-header">
                        <?php echo htmlspecialchars($dan); ?>
                        <span class="arrow">></span>
                    </div>
                    <div class="day-content">
                        <ul>
                        <?php foreach ($ure as $ura): ?>
                            <li>
                                **<?php echo htmlspecialchars($ura['ura']); ?>**: 
                                <a href="#"
                                   class="predmet-item"
                                   data-id-predmet="<?php echo $ura['id_predmet']; ?>" 
                                   data-id-ucitelja="<?php echo $ura['id_ucitelja']; ?>"
                                   data-ime-predmeta="<?php echo htmlspecialchars($ura['ime_predmeta']); ?>"
                                   data-ime-ucitelja="<?php echo htmlspecialchars($ura['ime_ucitelja'] . ' ' . $ura['priimek_ucitelja']); ?>"
                                >
                                    <?php echo htmlspecialchars($ura['ime_predmeta']); ?> 
                                    (<?php echo htmlspecialchars($ura['ime_ucitelja'] . ' ' . $ura['priimek_ucitelja']); ?>)
                                </a>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>

        <div class="naloge">
            <h3 id="content-header">Izberite predmet iz menija na levi ali urnika.</h3>
            <div id="content-details">
                <p>Izbrani predmet bo tukaj prikazal zadnjo aktivno nalogo in ustrezno formo za oddajo/ocenjevanje.</p>
            </div>
        </div>

    </main>
</div>

<div id="modal-oddaja" class="modal">
    <div class="modal-content">
        <span class="modal-close">&times;</span>
        <h3 id="modal-title">Pregled oddaje</h3>
        <div id="modal-body">
            </div>
    </div>
</div>


<script>
// ----------------------------------------------------
// POMOŽNE FUNKCIJE
// ----------------------------------------------------

const vloga = '<?php echo $vloga; ?>';

// Funkcija za inicializacijo harmonike (Urnik)
function setupAccordion() {
    document.querySelectorAll('.day-header').forEach(header => {
        header.addEventListener('click', () => {
            const day = header.closest('.day');
            day.classList.toggle('open');
        });
    });
}

// Funkcija za poslušalce na formi za oddajo (UČENEC)
function setupFormListeners() {
    // Poslušalec za formo za oddajo naloge (UČENEC)
    const oddajaForm = document.getElementById('oddaja-form');
    if (oddajaForm) {
        oddajaForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            const oddajaBtn = this.querySelector('button[type="submit"]');
            oddajaBtn.disabled = true;
            oddajaBtn.textContent = 'Oddajanje...';

            try {
                const response = await fetch('ajax_oddaja.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                alert(result.message);
                oddajaBtn.disabled = false;
                oddajaBtn.textContent = result.success ? 'Oddaja posodobljena!' : 'Oddaj Nalogo'; // Morda bolj generično

                if (result.success) {
                    // Ponovno naloži detajle za prikaz posodobljene oddaje/statusa
                    document.querySelector('.predmet-item.active').click(); 
                }
            } catch (error) {
                alert('Napaka pri komunikaciji s strežnikom med oddajo.');
                oddajaBtn.disabled = false;
                oddajaBtn.textContent = 'Oddaj Nalogo';
            }
        });
    }
}

// Funkcija za poslušalce gumbov za učitelja (Create Naloga, Delete Naloga, Pregled Oddaje)
function setupTeacherListeners() {
    // 1. Logika za kreiranje/objavo nove naloge (UČITELJ)
    const nalogaForm = document.getElementById('naloga-form');
    if (nalogaForm) {
        nalogaForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('vloga', vloga);
            
            const nalogaBtn = this.querySelector('button[type="submit"]');
            nalogaBtn.disabled = true;
            nalogaBtn.textContent = 'Shranjevanje...';

            try {
                const response = await fetch('ajax_naloga.php?action=create', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                alert(result.message);
                nalogaBtn.textContent = result.success ? 'Naloga shranjena!' : 'Poskusi ponovno';
                nalogaBtn.disabled = false;
                
                if (result.success) {
                    // Ponovno naloži detajle za prikaz nove naloge
                    document.querySelector('.predmet-item.active').click(); 
                }
            } catch (error) {
                alert('Napaka pri komunikaciji s strežnikom.');
                nalogaBtn.disabled = false;
                nalogaBtn.textContent = 'Objavi Nalogo';
            }
        });
    }
    
    // 2. Logika za brisanje naloge (UČITELJ)
    const deleteBtn = document.getElementById('delete-naloga-btn');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', async function() {
            const idNaloga = this.dataset.idNaloga;
            
            if (!confirm("Ali ste prepričani, da želite IZBRISATI to nalogo in VSE oddaje učencev zanjo? Ta akcija je NEPOVRATNA!")) {
                return;
            }
            
            deleteBtn.disabled = true;
            deleteBtn.textContent = 'Brisanje...';

            try {
                const response = await fetch('ajax_naloga_delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id_naloga: idNaloga })
                });
                
                const result = await response.json();
                alert(result.message);
                
                if (result.success) {
                    // Ponovno naloži detajle, da se prikaže nova forma
                    document.querySelector('.predmet-item.active').click(); 
                } else {
                    deleteBtn.disabled = false;
                    deleteBtn.textContent = 'Izbriši to nalogo';
                }
            } catch (error) {
                alert('Napaka pri komunikaciji s strežnikom med brisanjem.');
                deleteBtn.disabled = false;
                deleteBtn.textContent = 'Izbriši to nalogo';
            }
        });
    }
    
    // 3. Logika za prikaz modala za pregled/ocenjevanje (UČITELJ)
    document.querySelectorAll('.pregled-oddaje-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const idOddaja = e.target.dataset.oddajaId;
            const ucenecIme = e.target.dataset.ucenecIme;
            showGradingModal(idOddaja, ucenecIme);
        });
    });
}

// Funkcija za prikaz Modala za ocenjevanje
function showGradingModal(idOddaja, ucenecIme) {
    const modal = document.getElementById('modal-oddaja');
    const modalBody = document.getElementById('modal-body');
    const modalTitle = document.getElementById('modal-title');
    
    modalTitle.textContent = `Pregled oddaje: ${ucenecIme}`;
    modalBody.innerHTML = '<p>Nalaganje detajlov oddaje...</p>';
    modal.style.display = 'flex'; // Prikaži modal

    // Naloži vsebino z AJAX
    fetch('ajax_oddaja_pregled.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_oddaja: idOddaja })
    })
    .then(res => res.text())
    .then(html => {
        modalBody.innerHTML = html;
        
        // Po nalaganju vsebine, inicializiramo formo za ocenjevanje znotraj modala
        const gradingForm = document.getElementById('grading-form');
        if (gradingForm) {
            gradingForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                
                const gradeBtn = this.querySelector('button[type="submit"]');
                gradeBtn.disabled = true;
                gradeBtn.textContent = 'Shranjevanje...';
                
                try {
                    const response = await fetch('ajax_ocenjevanje.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    alert(result.message);
                    gradeBtn.disabled = false;
                    gradeBtn.textContent = 'Shrani Oceno';
                    
                    if (result.success) {
                        // Zapri modal
                        modal.style.display = 'none';
                        // Ponovno naloži seznam oddaj znotraj predmeta (klik na predmet)
                        const activeItem = document.querySelector('.predmet-item.active');
                        if (activeItem) {
                            activeItem.click();
                        }
                    }
                    
                } catch (error) {
                    alert('Napaka pri ocenjevanju: ' + error.message);
                    gradeBtn.disabled = false;
                    gradeBtn.textContent = 'Shrani Oceno';
                }
            });
        }
    })
    .catch(error => {
        modalBody.innerHTML = '<p style="color: red;">Napaka pri nalaganju detajlov oddaje.</p>';
        console.error('Fetch error:', error);
    });
}

// Funkcija za zapiranje modala
document.querySelector('.modal-close').addEventListener('click', () => {
    document.getElementById('modal-oddaja').style.display = 'none';
});
// Zapri modal, če klikneš izven vsebine
window.addEventListener('click', (event) => {
    const modal = document.getElementById('modal-oddaja');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
});


// Glavna funkcija za nalaganje vsebine predmeta
async function loadSubjectContent(idPredmet, idUcitelja, imePredmeta, imeUcitelja, element) {
    
    // Odstrani 'active' status iz vseh in ga dodaj trenutnemu elementu
    document.querySelectorAll('.predmet-item').forEach(item => {
        item.classList.remove('active');
    });
    // Preverimo, ali je element iz urnika (<a>) ali iz stranske vrstice (<li>)
    // Element, ki ima data atribute, je predmet, ne glede na to ali je <li> ali <a>
    element.classList.add('active'); 
    
    const nalogaVsebina = document.getElementById('content-details');
    nalogaVsebina.innerHTML = '<p>Nalaganje nalog...</p>'; // Prikaz nalaganja

    try {
        const response = await fetch('ajax_naloga.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id_predmet: idPredmet,
                id_ucitelja: idUcitelja,
                vloga: vloga
            })
        });
        
        const html = await response.text();
        
        // Vstavitev vsebine
        document.getElementById('content-header').textContent = `Naloge za ${imePredmeta} (Učitelj: ${imeUcitelja})`;
        document.getElementById('content-details').innerHTML = html;
        
        // KLJUČNO: Po nalogi vsebine ponovno inicializiramo poslušalce
        if (vloga === 'ucitelj' || vloga === 'admin') {
            setupTeacherListeners();
        }
        setupFormListeners(); 
        
    } catch (error) {
        document.getElementById('content-header').textContent = `Napaka pri nalaganju za ${imePredmeta}`;
        document.getElementById('content-details').innerHTML = '<p style="color: red;">Prišlo je do napake pri nalaganju podatkov za ta predmet.</p>';
    }
}


// ----------------------------------------------------
// IZVEDBA KODE (DOMContentLoaded)
// ----------------------------------------------------

// 1. Nastavi poslušalce za Urnik Harmonika
setupAccordion(); 

// 2. Glavna logika: Poslušalec za klike na predmete (iz urnika in menija)
document.querySelectorAll('.predmet-item').forEach(item => {
    item.addEventListener('click', (e) => {
        e.preventDefault();
        // Poiščemo najbližji element, ki ima data atribute za predmet, ne glede na to, ali je klik na <li> ali <a>
        const targetElement = e.currentTarget; 
        const idPredmet = targetElement.dataset.idPredmet;
        const idUcitelja = targetElement.dataset.idUcitelja;
        const imePredmeta = targetElement.dataset.imePredmeta;
        const imeUcitelja = targetElement.dataset.imeUcitelja;
        
        loadSubjectContent(idPredmet, idUcitelja, imePredmeta, imeUcitelja, targetElement);
    });
});

</script>
</body>
</html>