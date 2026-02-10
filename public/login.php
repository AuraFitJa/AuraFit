<?php
session_start();
require_once __DIR__ . '/../config/database.php';

$errors = [];

function redirect_to($path) {
    header("Location: " . $path);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email non valida.";
    if ($password === '') $errors[] = "Password obbligatoria.";

    if (!$errors) {
        try {
            $user = Database::exec(
                "SELECT idUtente, email, passwordHash, statoAccount
                 FROM Utenti
                 WHERE email = ?
                 LIMIT 1",
                [$email]
            )->fetch();

            if (!$user) {
                $errors[] = "Credenziali non valide.";
            } else if (($user['statoAccount'] ?? '') !== 'attivo') {
                $errors[] = "Account non attivo.";
            } else if (!password_verify($password, $user['passwordHash'])) {
                $errors[] = "Credenziali non valide.";
            } else {
                $idUtente = (int)$user['idUtente'];

                // Carica ruoli
                $roles = Database::exec(
                    "SELECT r.nomeRuolo
                     FROM UtenteRuolo ur
                     JOIN Ruoli r ON r.idRuolo = ur.idRuolo
                     WHERE ur.idUtente = ?",
                    [$idUtente]
                )->fetchAll();

                $roleNames = [];
                foreach ($roles as $r) $roleNames[] = $r['nomeRuolo'];

                // Sessione
                $_SESSION['user_id'] = $idUtente;
                $_SESSION['email'] = $user['email'];
                $_SESSION['roles'] = $roleNames;

                // Redirect in base ai ruoli
                if (in_array('pt', $roleNames, true) || in_array('nutrizionista', $roleNames, true)) {
                    redirect_to('dashboard_professionista.php');
                } else {
                    redirect_to('dashboard_cliente.php');
                }
            }
        } catch (Throwable $e) {
            $errors[] = "Errore server: " . $e->getMessage();
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
    label { display:block; margin: 8px 0 4px; }
    input { width: 100%; padding: 10px; }
    .error { background: #ffe8e8; border: 1px solid #ffb2b2; padding: 10px; border-radius: 6px; }
    .actions { margin-top: 12px; display:flex; gap:10px; align-items:center; }
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

<div class="box">
  <form method="post" action="">
    <label>Email</label>
    <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

    <label>Password</label>
    <input type="password" name="password" required>

    <div class="actions">
      <button type="submit">Accedi</button>
      <a href="register.php">Crea account</a>
    </div>
  </form>
</div>

</body>
</html>

