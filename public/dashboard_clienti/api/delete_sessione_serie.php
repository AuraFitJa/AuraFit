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

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '{}', true);
if (!is_array($payload)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Payload non valido.']);
  exit;
}

$programId = (int)($payload['programId'] ?? 0);
$idSerieSvolta = (int)($payload['idSerieSvolta'] ?? 0);
if ($programId <= 0 || $idSerieSvolta <= 0) {
  http_response_code(422);
  echo json_encode(['ok' => false, 'error' => 'Parametri non validi.']);
  exit;
}

try {
  $cliente = Database::exec(
    'SELECT idCliente FROM Clienti WHERE idUtente = ? LIMIT 1',
    [(int)$user['idUtente']]
  )->fetch();

  if (!$cliente) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Profilo cliente non trovato.']);
    exit;
  }

  $clienteId = (int)$cliente['idCliente'];

  $ownership = Database::exec(
    "SELECT 1
     FROM AssegnazioniProgramma
     WHERE programma = ?
       AND cliente = ?
       AND stato IN ('attivo', 'attiva')
     LIMIT 1",
    [$programId, $clienteId]
  )->fetch();

  if (!$ownership) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Programma non assegnato o non più attivo.']);
    exit;
  }

  $exists = Database::exec(
    'SELECT ss.idSerieSvolta
     FROM SerieSvolte ss
     INNER JOIN SessioniAllenamento sa ON sa.idSessione = ss.sessione
     WHERE ss.idSerieSvolta = ?
       AND sa.programma = ?
       AND sa.cliente = ?
     LIMIT 1',
    [$idSerieSvolta, $programId, $clienteId]
  )->fetch();

  if (!$exists) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Serie non trovata.']);
    exit;
  }

  Database::exec(
    'DELETE FROM SerieSvolte WHERE idSerieSvolta = ? LIMIT 1',
    [$idSerieSvolta]
  );

  echo json_encode(['ok' => true]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Errore durante la rimozione della serie.']);
}
