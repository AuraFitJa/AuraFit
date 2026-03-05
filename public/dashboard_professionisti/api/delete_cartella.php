<?php
require __DIR__ . '/../common.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Metodo non consentito.']);
  exit;
}

if (!$dbAvailable) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $dbError ?? 'Database non disponibile.']);
  exit;
}

$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'JSON non valido.']);
  exit;
}

$idCartella = filter_var($payload['idCartella'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($idCartella === false) {
  http_response_code(422);
  echo json_encode(['ok' => false, 'error' => 'idCartella non valido.']);
  exit;
}

try {
  $professionistaId = getProfessionistaId($userId);
  if (!$professionistaId) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Profilo professionista non trovato.']);
    exit;
  }

  $ownership = Database::exec(
    'SELECT 1 FROM ProgrammiCartelle WHERE idCartella = ? AND professionista = ? LIMIT 1',
    [$idCartella, $professionistaId]
  )->fetch();

  if (!$ownership) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Cartella non autorizzata.']);
    exit;
  }

  $pdo = Database::pdo();
  $pdo->beginTransaction();

  Database::exec(
    'UPDATE ProgrammiAllenamento SET cartellaId = NULL WHERE cartellaId = ? AND creatoreUtente = ?',
    [$idCartella, $userId]
  );

  Database::exec(
    'DELETE FROM ProgrammiCartelle WHERE idCartella = ? AND professionista = ? LIMIT 1',
    [$idCartella, $professionistaId]
  );

  $pdo->commit();

  echo json_encode(['ok' => true]);
} catch (Throwable $e) {
  if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
    $pdo->rollBack();
  }
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Errore eliminazione cartella.']);
}
