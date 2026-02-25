<?php
session_start();

$user = $_SESSION['user'] ?? null;
if (!$user && isset($_SESSION['idUtente'])) {
  $user = [
    'idUtente' => (int)$_SESSION['idUtente'],
    'email' => (string)($_SESSION['email'] ?? ''),
    'roles' => (array)($_SESSION['roles'] ?? []),
  ];
}

if (!$user || empty($user['idUtente'])) {
  header('Location: login.php');
  exit;
}

if (!in_array('cliente', array_map('strtolower', (array)$user['roles']), true)) {
  header('Location: login.php');
  exit;
}

header('Location: dashboard_clienti/overview.php');
exit;
