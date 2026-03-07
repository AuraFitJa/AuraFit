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

$idProgramma = filter_var($payload['idProgramma'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$clientiInput = $payload['clienti'] ?? null;

if ($idProgramma === false) {
  http_response_code(422);
  echo json_encode(['ok' => false, 'error' => 'idProgramma non valido.']);
  exit;
}

if (!is_array($clientiInput)) {
  http_response_code(422);
  echo json_encode(['ok' => false, 'error' => 'Elenco clienti non valido.']);
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
    "SELECT idProgramma
     FROM ProgrammiAllenamento
     WHERE idProgramma = ?
       AND creatoreUtente = ?
       AND stato <> 'archiviato'
     LIMIT 1",
    [$idProgramma, $userId]
  )->fetch();

  if (!$ownership) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Programma non autorizzato.']);
    exit;
  }

  $allowedRows = Database::exec(
    "SELECT c.idCliente
     FROM Associazioni a
     INNER JOIN Clienti c ON c.idCliente = a.cliente
     WHERE a.professionista = ?
       AND a.attivaFlag = 1
       AND a.tipoAssociazione = 'pt'",
    [$professionistaId]
  )->fetchAll();

  $allowedMap = [];
  foreach ($allowedRows as $row) {
    $allowedMap[(int)$row['idCliente']] = true;
  }

  $selectedMap = [];
  foreach ($clientiInput as $clienteRaw) {
    $clienteId = filter_var($clienteRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($clienteId === false) {
      continue;
    }

    $clienteId = (int)$clienteId;
    if (!isset($allowedMap[$clienteId])) {
      continue;
    }

    $selectedMap[$clienteId] = true;
  }

  $selectedClienti = array_keys($selectedMap);
  if (count($selectedClienti) === 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Nessun cliente selezionato valido.']);
    exit;
  }

  $alreadyRows = Database::exec(
    'SELECT cliente FROM AssegnazioniProgramma WHERE programma = ?',
    [$idProgramma]
  )->fetchAll();

  $alreadyMap = [];
  foreach ($alreadyRows as $row) {
    $alreadyMap[(int)$row['cliente']] = true;
  }

  $inserted = 0;
  $skipped = 0;

  foreach ($selectedClienti as $clienteId) {
    if (isset($alreadyMap[$clienteId])) {
      $skipped++;
      continue;
    }

    Database::exec(
      "INSERT INTO AssegnazioniProgramma (programma, cliente, stato)
       VALUES (?, ?, 'attivo')",
      [$idProgramma, $clienteId]
    );

    $inserted++;
  }

  $message = $inserted > 0
    ? 'Scheda assegnata con successo.'
    : 'Nessuna nuova assegnazione effettuata.';

  echo json_encode([
    'ok' => true,
    'inserted' => $inserted,
    'skipped' => $skipped,
    'message' => $message,
  ]);
  exit;
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Errore durante assegnazione programma.']);
  exit;
}
