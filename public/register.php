<?php
session_start();
require_once __DIR__ . '/../config/database.php';

$errors = [];
$success = null;

function redirect_to($path) {
    header("Location: " . $path);
    exit;
}

function find_role_id($roleName) {
    $row = Database::exec(
        "SELECT idRuolo FROM Ruoli WHERE nomeRuolo = ? LIMIT 1",
        [$roleName]
    )->fetch();

    return $row ? (int)$row['idRuolo'] : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Campi base
    $nome    = trim($_POST['nome'] ?? '');
    $cognome = trim($_POST['cognome'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    // tipo: cliente | professionista
    $tipo = $_POST['tipo'] ?? 'cliente';

    // ruolo_professionista: pt | nutrizionista | entrambi (solo se tipo=professionista)
    $ruoloProfessionista = $_POST['ruolo_professionista'] ?? 'pt';

    // Dati cliente (facoltativi)
    $altezza = trim($_POST['altezza'] ?? '');
    $peso    = trim($_POST['peso'] ?? '');
    $eta     = trim($_POST['eta'] ?? '');

    // Validazioni
    if ($nome === '' || $cognome === '') $errors[] = "Nome e cognome sono obbligatori.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email non valida.";
    if (strlen($password) < 8) $errors[] = "La password deve essere di almeno 8 caratteri.";

    if ($tipo !== 'cliente' && $tipo !== 'professionista') {
        $errors[] = "Tipo registrazione non valido.";
    }

    if ($altezza < 50 || $altezza > 260) {
  $errors[] = "Altezza non valida (max 260 cm).";
    }

    if ($eta < 1 || $eta > 130) {
      $errors[] = "Età non valida (max 130 anni).";
    }

    if ($peso < 20 || $peso > 700) {
      $errors[] = "Peso non valido (max 700 kg).";
    }

    if ($tipo === 'professionista') {
        if (!in_array($ruoloProfessionista, ['pt', 'nutrizionista', 'entrambi'], true)) {
            $errors[] = "Ruolo professionista non valido.";
        }
    }

    if (!$errors) {
        try {
            $email   = trim($_POST['email'] ?? '');
            $emailNormalizzata = mb_strtolower($email, 'UTF-8'); // <-- subito qui

            // 1) Email unica
            $exists = Database::exec(
              "SELECT idUtente FROM Utenti WHERE email = ? OR emailNormalizzata = ? LIMIT 1",
              [$email, $emailNormalizzata]
            )->fetch();



            if ($exists) {
                $errors[] = "Email già registrata.";
            } else {

                // 2) Inserisci Utente
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $emailNormalizzata = mb_strtolower($email, 'UTF-8');

                Database::exec(
                  "INSERT INTO Utenti (email, emailNormalizzata, passwordHash, nome, cognome, statoAccount)
                   VALUES (?, ?, ?, ?, ?, ?)",
                  [$email, $emailNormalizzata, $hash, $nome, $cognome, 'attivo']
                );


                $idUtente = (int)Database::pdo()->lastInsertId();

                // 3) Ruoli (tabella ponte UtenteRuolo) + creazione Cliente/Professionista
                if ($tipo === 'cliente') {

                    $idRuoloCliente = find_role_id('cliente');
                    if (!$idRuoloCliente) {
                        throw new Exception("Ruolo 'cliente' non trovato. Importa seed.sql.");
                    }

                    Database::exec(
                        "INSERT INTO UtenteRuolo (idUtente, idRuolo) VALUES (?, ?)",
                        [$idUtente, $idRuoloCliente]
                    );

                    // Crea Cliente
                    Database::exec("INSERT INTO Clienti (idUtente) VALUES (?)", [$idUtente]);
                    $idCliente = (int)Database::pdo()->lastInsertId();

                    // ProfiloCliente (facoltativo)
                    Database::exec(
                      "INSERT INTO ProfiloCliente (idCliente, altezzaCm, pesoKg, eta)
                       VALUES (?, ?, ?, ?)",
                      [
                        $idCliente,
                        ($altezza !== '' ? (int)$altezza : null),
                        ($peso !== '' ? (float)$peso : null),
                        ($eta !== '' ? (int)$eta : null),
                      ]
                    );


                    $success = "Registrazione cliente completata. Ora puoi fare login.";

                } else {
                    // Professionista

                    // Crea Professionista
                    Database::exec("INSERT INTO Professionisti (idUtente) VALUES (?)", [$idUtente]);
                    $idProfessionista = (int)Database::pdo()->lastInsertId();

                    // ProfiloProfessionista
                    Database::exec(
                        "INSERT INTO ProfiloProfessionista (idProfessionista, statoVerifica)
                         VALUES (?, ?)",
                        [$idProfessionista, 'pending']
                    );

                    // Ruoli PT / Nutrizionista
                    if ($ruoloProfessionista === 'pt' || $ruoloProfessionista === 'entrambi') {
                        $idRuoloPT = find_role_id('pt');
                        if (!$idRuoloPT) throw new Exception("Ruolo 'pt' non trovato. Importa seed.sql.");

                        Database::exec(
                            "INSERT INTO UtenteRuolo (idUtente, idRuolo) VALUES (?, ?)",
                            [$idUtente, $idRuoloPT]
                        );
                    }

                    if ($ruoloProfessionista === 'nutrizionista' || $ruoloProfessionista === 'entrambi') {
                        $idRuoloNutri = find_role_id('nutrizionista');
                        if (!$idRuoloNutri) throw new Exception("Ruolo 'nutrizionista' non trovato. Importa seed.sql.");

                        Database::exec(
                            "INSERT INTO UtenteRuolo (idUtente, idRuolo) VALUES (?, ?)",
                            [$idUtente, $idRuoloNutri]
                        );
                    }

                    $success = "Registrazione professionista completata. Ora puoi fare login.";
                }
            }

        } catch (Throwable $e) {
            $errors[] = "Errore: " . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>AuraFit - Registrazione</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root{
      --bg:#070A12;
      --text:#EAF0FF;
      --muted: rgba(234,240,255,.68);
      --line: rgba(234,240,255,.12);
      --brand1:#6D5EF3;
      --brand2:#2EE1A5;
      --danger:#ff7f97;
      --success:#63e6b8;
      --sans: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
    }
    * { box-sizing:border-box; }
    body {
      margin: 0;
      min-height: 100vh;
      font-family: var(--sans);
      color: var(--text);
      background: var(--bg);
      display: grid;
      place-items: center;
      padding: 24px 16px;
      position: relative;
    }
    body::before{
      content:"";
      position: fixed;
      inset: 0;
      z-index: -1;
      background:
        radial-gradient(1200px 800px at 20% -10%, rgba(109,94,243,.35), transparent 55%),
        radial-gradient(1100px 700px at 90% 10%, rgba(46,225,165,.22), transparent 55%),
        radial-gradient(900px 700px at 55% 95%, rgba(76,201,240,.18), transparent 55%);
    }

    .auth-shell { width: 100%; max-width: 780px; }
    .brand { display:inline-flex; align-items:center; gap:10px; margin-bottom:18px; font-weight:700; }
    .logo-img {
      width: 40px;
      height: 40px;
      border-radius: 12px;
      object-fit: cover;
      box-shadow: 0 10px 30px rgba(109,94,243,.35);
    }

    .box {
      background: linear-gradient(180deg, rgba(255,255,255,.08), rgba(255,255,255,.04));
      box-shadow: 0 18px 60px rgba(0,0,0,.45), inset 0 0 0 1px rgba(255,255,255,.05);
      border-radius: 24px;
      padding: 26px;
      backdrop-filter: blur(10px);
    }
    .inner-box {
      margin-top: 16px;
      border-radius: 16px;
      padding: 16px;
      border: 1px solid var(--line);
      background: rgba(255,255,255,.03);
    }
    h1 { margin: 0 0 8px; font-size: 32px; }
    h3 { margin: 0 0 10px; }
    .subtitle { margin: 0 0 18px; color: var(--muted); }
    .row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    label { display:block; margin: 12px 0 6px; font-weight: 600; }
    input, select {
      width: 100%;
      padding: 11px 12px;
      border-radius: 12px;
      border: 1px solid var(--line);
      background: rgba(255,255,255,.03);
      color: var(--text);
      outline: none;
    }
    input:focus, select:focus {
      border-color: rgba(109,94,243,.65);
      box-shadow: 0 0 0 3px rgba(109,94,243,.2);
    }

    select {
      appearance: none;
      background: linear-gradient(135deg, rgba(109,94,243,.15), rgba(46,225,165,.1));
      color: var(--text);
    }

    select option {
      background: #0b1220;
      color: #EAF0FF;
    }

    .btn {
      margin-top: 14px;
      width: 100%;
      border: 0;
      border-radius: 12px;
      padding: 12px 14px;
      font-weight: 700;
      cursor: pointer;
      color: #081118;
      background: linear-gradient(135deg, rgba(109,94,243,.95), rgba(46,225,165,.75));
      box-shadow: 0 10px 30px rgba(109,94,243,.25);
    }
    .footer-link { margin-top: 12px; color: var(--muted); font-size: 14px; text-align: center; }
    .footer-link a { color: var(--text); text-decoration: underline; }

    .error, .success {
      margin-bottom: 14px;
      border-radius: 12px;
      padding: 12px;
      border: 1px solid;
      font-size: 14px;
    }
    .error { background: rgba(255,127,151,.12); border-color: rgba(255,127,151,.5); color: #ffd7e1; }
    .success { background: rgba(99,230,184,.14); border-color: rgba(99,230,184,.5); color: #d8ffef; }
    .error ul { margin: 8px 0 0 18px; padding: 0; }

    @media (max-width: 760px) {
      .row { grid-template-columns: 1fr; }
      .box { padding: 20px; }
    }
  </style>
  <script>
    function toggleSections() {
      var tipo = document.getElementById('tipo').value;
      document.getElementById('cliente-section').style.display = (tipo === 'cliente') ? 'block' : 'none';
      document.getElementById('professionista-section').style.display = (tipo === 'professionista') ? 'block' : 'none';
    }
    window.onload = toggleSections;
  </script>
</head>
<body>
<main class="auth-shell">
  <div class="brand">
  <img 
    src="https://i.imgur.com/q8qW3dv.png"
    alt="AuraFit"
    class="logo-img"
  />
  <span>AuraFit</span>
</div>  

  <div class="box">
    <h1>Crea il tuo account</h1>
    <p class="subtitle">Inizia il tuo percorso fitness personalizzato su AuraFit.</p>

    <?php if (!empty($errors)): ?>
      <div class="error">
        <strong>Errore:</strong>
        <ul>
          <?php foreach ($errors as $err): ?>
            <li><?= htmlspecialchars($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="post" action="">
      <div class="row">
        <div>
          <label for="nome">Nome</label>
          <input id="nome" name="nome" required value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>">
        </div>
        <div>
          <label for="cognome">Cognome</label>
          <input id="cognome" name="cognome" required value="<?= htmlspecialchars($_POST['cognome'] ?? '') ?>">
        </div>
      </div>

      <label for="email">Email</label>
      <input id="email" type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

      <label for="password">Password (min 8 caratteri)</label>
      <input id="password" type="password" name="password" required>

      <label for="tipo">Tipo registrazione</label>
      <select id="tipo" name="tipo" onchange="toggleSections()">
        <option value="cliente" <?= (($_POST['tipo'] ?? 'cliente') === 'cliente') ? 'selected' : '' ?>>Cliente</option>
        <option value="professionista" <?= (($_POST['tipo'] ?? '') === 'professionista') ? 'selected' : '' ?>>Professionista</option>
      </select>

      <div id="cliente-section" class="inner-box">
        <h3>Dati Cliente (facoltativi)</h3>
        <div class="row">
          <div>
            <label for="altezza">Altezza (cm)</label>
            <input id="altezza" type="number" name="altezza" min="50" max="260" step="1" value="<?= htmlspecialchars($_POST['altezza'] ?? '') ?>">
          </div>
          <div>
            <label for="peso">Peso (kg)</label>
            <input id="peso" type="number" step="0.1" name="peso" min="20" max="700" value="<?= htmlspecialchars($_POST['peso'] ?? '') ?>">
          </div>
        </div>
        <label for="eta">Età</label>
        <input id="eta" type="number" name="eta" min="1" max="130" value="<?= htmlspecialchars($_POST['eta'] ?? '') ?>">
      </div>

      <div id="professionista-section" class="inner-box">
        <h3>Dati Professionista</h3>
        <label for="ruolo_professionista">Ruolo professionista</label>
        <select id="ruolo_professionista" name="ruolo_professionista">
          <option value="pt" <?= (($_POST['ruolo_professionista'] ?? 'pt') === 'pt') ? 'selected' : '' ?>>PT</option>
          <option value="nutrizionista" <?= (($_POST['ruolo_professionista'] ?? '') === 'nutrizionista') ? 'selected' : '' ?>>Nutrizionista</option>
          <option value="entrambi" <?= (($_POST['ruolo_professionista'] ?? '') === 'entrambi') ? 'selected' : '' ?>>Entrambi</option>
        </select>
      </div>

      <button class="btn" type="submit">Crea account</button>
    </form>

    <p class="footer-link">Hai già un account? <a href="login.php">Accedi</a></p>
  </div>
</main>
</body>
</html>
