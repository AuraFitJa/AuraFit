<?php
require_once __DIR__ . '/lib/security.php';
aurafit_start_secure_session();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  http_response_code(405);
  header('Allow: POST');
  exit('Metodo non consentito.');
}

if (!aurafit_validate_csrf_token(aurafit_request_csrf_token())) {
  http_response_code(403);
  exit('CSRF token non valido.');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
  $params = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
}
session_destroy();
header('Location: login.php');
exit;
