<?php
require_once __DIR__ . '/lib/security.php';
aurafit_start_secure_session();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
  // Non effettua logout via GET: mostra una pagina di conferma che invia POST con CSRF.
  $csrfToken = aurafit_get_csrf_token();
  ?>
  <!doctype html>
  <html lang="it">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Conferma logout - AuraFit</title>
  </head>
  <body>
    <form id="logoutForm" method="post" action="logout.php">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
      <noscript><button type="submit">Conferma logout</button></noscript>
    </form>
    <script>document.getElementById('logoutForm')?.submit();</script>
  </body>
  </html>
  <?php
  exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  http_response_code(405);
  header('Allow: GET, POST');
  exit('Metodo non consentito.');
}

if (!aurafit_validate_csrf_token(aurafit_request_csrf_token())) {
  http_response_code(403);
  header('Location: login.php?logout=token');
  exit;
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
  $params = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
}
session_destroy();
header('Location: login.php');
exit;
