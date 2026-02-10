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

    if ($tipo === 'professionista') {
        if (!in_array($ruoloProfessionista, ['pt', 'nutrizionista', 'entrambi'], true)) {
            $errors[] = "Ruolo professionista non valido.";
        }
    }

    if (!$errors) {
        try {
            // 1) Email unica
            $exists = Database::exec(
                "SELECT idUtente FROM Utenti WHERE email = ? LIMIT 1",
                [$email]
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
                        "INSERT INTO ProfiloCliente (idCliente, altezza, peso, eta)
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
    body { font-family: Arial, sans-serif; max-width: 720px; margin: 40px auto; padding: 0 16px; }
    .box { border: 1px solid #ddd; padding: 16px; border-radius: 8px; margin-top: 16px; }
    .row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    label { display: block; margin: 8px 0 4px; }
    input, select { width: 100%; padding: 10px; }
    .error { background: #ffe8e8; border: 1px solid #ffb2b2; padding: 10px; border-radius: 6px; }
    .success { background: #e8fff0; border: 1px solid #9de2b3; padding: 10px; border-radius: 6px; }
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

<h1>Registrazione AuraFit</h1>

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

<div class="box">
  <form method="post" action="">
    <div class="row">
      <div>
        <label>Nome</label>
        <input name="nome" required value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>">
      </div>
      <div>
        <label>Cognome</label>
        <input name="cognome" required value="<?= htmlspecialchars($_POST['cognome'] ?? '') ?>">
      </div>
    </div>

    <label>Email</label>
    <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

    <label>Password (min 8 caratteri)</label>
    <input type="password" name="password" required>

    <label>Tipo registrazione</label>
    <select id="tipo" name="tipo" onchange="toggleSections()">
      <option value="cliente" <?= (($_POST['tipo'] ?? 'cliente') === 'cliente') ? 'selected' : '' ?>>Cliente</option>
      <option value="professionista" <?= (($_POST['tipo'] ?? '') === 'professionista') ? 'selected' : '' ?>>Professionista</option>
    </select>

    <div id="cliente-section" class="box">
      <h3>Dati Cliente (facoltativi)</h3>
      <div class="row">
        <div>
          <label>Altezza (cm)</label>
          <input type="number" name="altezza" value="<?= htmlspecialchars($_POST['altezza'] ?? '') ?>">
        </div>
        <div>
          <label>Peso (kg)</label>
          <input type="number" step="0.1" name="peso" value="<?= htmlspecialchars($_POST['peso'] ?? '') ?>">
        </div>
      </div>
      <label>Età</label>
      <input type="number" name="eta" value="<?= htmlspecialchars($_POST['eta'] ?? '') ?>">
    </div>

    <div id="professionista-section" class="box">
      <h3>Dati Professionista</h3>
      <label>Ruolo professionista</label>
      <select name="ruolo_professionista">
        <option value="pt" <?= (($_POST['ruolo_professionista'] ?? 'pt') === 'pt') ? 'selected' : '' ?>>PT</option>
        <option value="nutrizionista" <?= (($_POST['ruolo_professionista'] ?? '') === 'nutrizionista') ? 'selected' : '' ?>>Nutrizionista</option>
        <option value="entrambi" <?= (($_POST['ruolo_professionista'] ?? '') === 'entrambi') ? 'selected' : '' ?>>Entrambi</option>
      </select>
    </div>

    <div style="margin-top:12px;">
      <button type="submit">Crea account</button>
      <a href="login.php" style="margin-left:10px;">Hai già un account? Login</a>
    </div>
  </form>
</div>

</body>
</html>

