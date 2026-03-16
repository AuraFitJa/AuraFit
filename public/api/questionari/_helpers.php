<?php

function q_json_response(array $payload, int $status = 200): void {
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload);
  exit;
}

function q_parse_json_body(): array {
  $raw = file_get_contents('php://input');
  if ($raw === false || trim($raw) === '') {
    return [];
  }

  $data = json_decode($raw, true);
  if (!is_array($data)) {
    q_json_response(['ok' => false, 'error' => 'JSON non valido.'], 400);
  }

  return $data;
}

function q_require_method(string $method): void {
  if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== strtoupper($method)) {
    q_json_response(['ok' => false, 'error' => 'Metodo non consentito.'], 405);
  }
}

function q_bootstrap(): void {
  if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
  }

  $dbPath = __DIR__ . '/../../../config/database.php';
  if (!file_exists($dbPath)) {
    q_json_response(['ok' => false, 'error' => 'Config database mancante.'], 500);
  }

  require_once $dbPath;
  if (!class_exists('Database')) {
    q_json_response(['ok' => false, 'error' => 'Classe Database non disponibile.'], 500);
  }
}

function q_logged_user(): array {
  $user = $_SESSION['user'] ?? null;
  if (!$user && isset($_SESSION['idUtente'])) {
    $user = [
      'idUtente' => (int)$_SESSION['idUtente'],
      'email' => (string)($_SESSION['email'] ?? ''),
      'roles' => (array)($_SESSION['roles'] ?? []),
    ];
  }

  if (!$user || empty($user['idUtente'])) {
    q_json_response(['ok' => false, 'error' => 'Utente non autenticato.'], 401);
  }

  return $user;
}

function q_roles(array $user): array {
  return array_values(array_unique(array_map('strtolower', (array)($user['roles'] ?? []))));
}

function q_get_professionista_id(int $idUtente): ?int {
  $row = Database::exec('SELECT idProfessionista FROM Professionisti WHERE idUtente = ? LIMIT 1', [$idUtente])->fetch();
  return $row ? (int)$row['idProfessionista'] : null;
}

function q_get_cliente_id(int $idUtente): ?int {
  $row = Database::exec('SELECT idCliente FROM Clienti WHERE idUtente = ? LIMIT 1', [$idUtente])->fetch();
  return $row ? (int)$row['idCliente'] : null;
}

function q_require_professionista_context(): array {
  q_bootstrap();
  $user = q_logged_user();
  $roles = q_roles($user);
  if (!in_array('pt', $roles, true) && !in_array('nutrizionista', $roles, true)) {
    q_json_response(['ok' => false, 'error' => 'Permesso negato.'], 403);
  }

  $profId = q_get_professionista_id((int)$user['idUtente']);
  if (!$profId) {
    q_json_response(['ok' => false, 'error' => 'Profilo professionista non trovato.'], 403);
  }

  return ['user' => $user, 'roles' => $roles, 'professionistaId' => $profId];
}

function q_require_cliente_context(): array {
  q_bootstrap();
  $user = q_logged_user();
  $roles = q_roles($user);
  if (!in_array('cliente', $roles, true)) {
    q_json_response(['ok' => false, 'error' => 'Permesso negato.'], 403);
  }

  $clienteId = q_get_cliente_id((int)$user['idUtente']);
  if (!$clienteId) {
    q_json_response(['ok' => false, 'error' => 'Profilo cliente non trovato.'], 403);
  }

  return ['user' => $user, 'roles' => $roles, 'clienteId' => $clienteId];
}

function q_questionario_owned(int $questionarioId, int $professionistaId): ?array {
  $row = Database::exec(
    'SELECT * FROM Questionari WHERE idQuestionario = ? AND professionista = ? LIMIT 1',
    [$questionarioId, $professionistaId]
  )->fetch();

  return $row ?: null;
}

function q_cliente_associato(int $professionistaId, int $clienteId): bool {
  $row = Database::exec(
    "SELECT a.idAssociazione
     FROM Associazioni a
     WHERE a.professionista = ?
       AND a.cliente = ?
       AND a.attivaFlag = 1
       AND a.tipoAssociazione IN ('pt','nutrizionista')
     LIMIT 1",
    [$professionistaId, $clienteId]
  )->fetch();

  return (bool)$row;
}

function q_get_or_create_draft(int $assegnazioneId, int $questionarioId, int $clienteId): int {
  $draft = Database::exec(
    "SELECT idCompilazione
     FROM QuestionarioCompilazioni
     WHERE assegnazione = ? AND cliente = ? AND stato = 'bozza'
     ORDER BY aggiornatoIl DESC
     LIMIT 1",
    [$assegnazioneId, $clienteId]
  )->fetch();

  if ($draft) {
    return (int)$draft['idCompilazione'];
  }

  $maxNum = Database::exec(
    'SELECT COALESCE(MAX(numeroCompilazione),0) AS maxNumero FROM QuestionarioCompilazioni WHERE assegnazione = ?',
    [$assegnazioneId]
  )->fetch();
  $nextNum = ((int)($maxNum['maxNumero'] ?? 0)) + 1;

  Database::exec(
    "INSERT INTO QuestionarioCompilazioni
      (assegnazione, questionario, cliente, numeroCompilazione, stato, iniziatoIl, aggiornatoIl)
     VALUES (?, ?, ?, ?, 'bozza', NOW(), NOW())",
    [$assegnazioneId, $questionarioId, $clienteId, $nextNum]
  );

  return (int)Database::pdo()->lastInsertId();
}
