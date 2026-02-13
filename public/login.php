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
    body { font-family: Arial, sans-serif; max-width: 520px; margin: 40px auto; padding: 0 16px; }
    .box { border: 1px solid #ddd; padding: 16px; border-radius: 8px; margin-top: 16px; }
    label { display: block; margin: 10px 0 6px; }
    input { width: 100%; padding: 10px; }
    button { padding: 10px 14px; cursor: pointer; }
    .error { background: #ffe8e8; border: 1px solid #ffb2b2; padding: 10px; border-radius: 6px; }
    .success { background: #e8fff0; border: 1px solid #9de2b3; padding: 10px; border-radius: 6px; }
  </style>
</head>
<body>

<h1>Login AuraFit</h1>

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
    <label>Email</label>
    <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

    <label>Password</label>
    <input type="password" name="password" required>

    <div style="margin-top:12px;">
      <button type="submit">Accedi</button>
      <a href="register.php" style="margin-left:10px;">Non hai un account? Registrati</a>
    </div>
  </form>
</div>

</body>
</html>
