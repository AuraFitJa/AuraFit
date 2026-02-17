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

if($user['idUtente'] !== 0 && !in_array('cliente', $user['roles'], true)) {
  header('Location: login.php');
  exit;
}

echo '<h1>Dashboard Cliente</h1>';
echo '<p>Utente: ' . htmlspecialchars((string)($user['email'] ?? '')) . '</p>';
echo '<p>Ruoli: ' . htmlspecialchars(implode(', ', (array)($user['roles'] ?? []))) . '</p>';
echo '<p><a href="logout.php">Logout</a></p>';
