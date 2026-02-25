<?php
session_start();
require_once __DIR__ . '/../config/database.php';

$errors = [];
$success = null;

function redirect_to(string $path): void {
  header("Location: " . $path);
  exit;
}

function normalize_email(string $email): string {
  return mb_strtolower(trim($email), 'UTF-8');
}

function load_user_roles(int $idUtente): array {
  $stmt = Database::exec(
    "SELECT r.nomeRuolo
     FROM UtenteRuolo ur
     INNER JOIN Ruoli r ON r.idRuolo = ur.idRuolo
     WHERE ur.idUtente = ?",
    [$idUtente]
  );
  $roles = [];
  while ($row = $stmt->fetch()) {
    $roles[] = $row['nomeRuolo'];
  }
  return $roles;
}

function decide_redirect(array $roles): string {
  // Se ha ruoli professionali -> professionista
  if (in_array('pt', $roles, true) || in_array('nutrizionista', $roles, true)) {
    return '/public/dashboard_professionista.php';
  }
  // Se cliente -> cliente
  if (in_array('cliente', $roles, true)) {
    return '/public/dashboard_cliente.php';
  }
  // fallback
  return '/index.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = (string)($_POST['email'] ?? '');
  $password = (string)($_POST['password'] ?? '');

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Email non valida.";
  }
  if ($password === '') {
    $errors[] = "Inserisci la password.";
  }

  if (!$errors) {
    try {
      $emailNorm = normalize_email($email);

      // Prendo utente con emailNormalizzata (o email come fallback)
      $user = Database::exec(
        "SELECT idUtente, email, emailNormalizzata, passwordHash, nome, cognome,
                statoAccount, tentativiFalliti, bloccatoFinoAl, eliminatoIl
         FROM Utenti
         WHERE (emailNormalizzata = ? OR email = ?)
         LIMIT 1",
        [$emailNorm, trim($email)]
      )->fetch();

      // Messaggio generico per non rivelare se l'email esiste
      $genericError = "Credenziali non corrette.";

      if (!$user || !empty($user['eliminatoIl'])) {
        $errors[] = $genericError;
      } else {

        // Check blocco temporaneo
        if (!empty($user['bloccatoFinoAl'])) {
          $blockedUntil = strtotime($user['bloccatoFinoAl']);
          if ($blockedUntil !== false && $blockedUntil > time()) {
            $errors[] = "Account temporaneamente bloccato. Riprova più tardi.";
          }
        }

        // Check stato account (adatta ai tuoi valori reali)
        if (!$errors) {
          $stato = (string)$user['statoAccount'];

          // Se vuoi permettere login solo ad "attivo"
          if ($stato !== 'attivo') {
            // esempi: pending_email, sospeso, ecc.
            $errors[] = "Account non attivo (stato: {$stato}).";
          }
        }

        // Verifica password
        if (!$errors) {
          $ok = password_verify($password, (string)$user['passwordHash']);

          if (!$ok) {
            // incrementa tentativi
            $tentativi = (int)$user['tentativiFalliti'] + 1;

            // Soglia blocco (modifica se vuoi)
            $maxTentativi = 5;

            if ($tentativi >= $maxTentativi) {
              // blocco 15 minuti
              Database::exec(
                "UPDATE Utenti
                 SET tentativiFalliti = ?, bloccatoFinoAl = DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                 WHERE idUtente = ?",
                [$tentativi, (int)$user['idUtente']]
              );
              $errors[] = "Troppi tentativi. Account bloccato per 15 minuti.";
            } else {
              Database::exec(
                "UPDATE Utenti
                 SET tentativiFalliti = ?
                 WHERE idUtente = ?",
                [$tentativi, (int)$user['idUtente']]
              );
              $errors[] = $genericError;
            }
          } else {
            // OK: reset tentativi, sblocca, aggiorna ultimoLogin
            Database::exec(
              "UPDATE Utenti
               SET tentativiFalliti = 0, bloccatoFinoAl = NULL, ultimoLogin = NOW()
               WHERE idUtente = ?",
              [(int)$user['idUtente']]
            );

            $idUtente = (int)$user['idUtente'];
            $roles = load_user_roles($idUtente);

            // Session hardening
            session_regenerate_id(true);

            $_SESSION['user'] = [
              'idUtente' => $idUtente,
              'email' => (string)$user['email'],
              'nome' => (string)$user['nome'],
              'cognome' => (string)$user['cognome'],
              'roles' => $roles,
            ];

            // Backward compatibility per pagine che leggono ancora chiavi flat in sessione
            $_SESSION['idUtente'] = $idUtente;
            $_SESSION['email'] = (string)$user['email'];
            $_SESSION['roles'] = $roles;

            redirect_to(decide_redirect($roles));
          }
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
  <title>AuraFit - Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root{
      --bg:#070A12;
      --text:#EAF0FF;
      --muted: rgba(234,240,255,.68);
      --line: rgba(234,240,255,.12);
      --brand1:#6D5EF3;
      --brand2:#2EE1A5;
      --brand3:#4CC9F0;
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

    .auth-shell { width: 100%; max-width: 500px; }
    .brand {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 18px;
      font-weight: 700;
      letter-spacing: .2px;
    }
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

    h1 { margin: 0 0 8px; font-size: 32px; }
    .subtitle { margin: 0 0 18px; color: var(--muted); }
    label { display:block; margin: 12px 0 6px; font-weight: 600; }
    input {
      width: 100%;
      padding: 11px 12px;
      border-radius: 12px;
      border: 1px solid var(--line);
      background: rgba(255,255,255,.03);
      color: var(--text);
      outline: none;
    }
    input:focus {
      border-color: rgba(109,94,243,.65);
      box-shadow: 0 0 0 3px rgba(109,94,243,.2);
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
    .footer-link {
      margin-top: 12px;
      color: var(--muted);
      font-size: 14px;
      text-align: center;
    }
    .footer-link a { color: var(--text); text-decoration: underline; }

    .brand-link {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      text-decoration: none;
      color: var(--text);
    }

    .brand-link:hover {
      opacity: 0.85;
    }

    .error, .success {
      margin-bottom: 14px;
      border-radius: 12px;
      padding: 12px;
      border: 1px solid;
      font-size: 14px;
    }
    .error {
      background: rgba(255,127,151,.12);
      border-color: rgba(255,127,151,.5);
      color: #ffd7e1;
    }
    .success {
      background: rgba(99,230,184,.14);
      border-color: rgba(99,230,184,.5);
      color: #d8ffef;
    }
    .error ul { margin: 8px 0 0 18px; padding: 0; }
  </style>
</head>
<body>
<main class="auth-shell">
    <div class="brand">
    <a href="../public/" class="brand-link">
      <img 
        src="media/logo.png"
        alt="AuraFit"
        class="logo-img"
      />
      <span>AuraFit</span>
    </a>
  </div>

  <div class="box">
    <h1>Bentornato</h1>
    <p class="subtitle">Accedi per continuare il tuo percorso su AuraFit.</p>

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
      <label for="email">Email</label>
      <input id="email" type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

      <label for="password">Password</label>
      <input id="password" type="password" name="password" required>

      <button class="btn" type="submit">Accedi</button>
    </form>

    <p class="footer-link">Non hai un account? <a href="register.php">Registrati</a></p>
  </div>
</main>

</body>
</html>
