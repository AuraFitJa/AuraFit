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

$programId = (int)($payload['programId'] ?? 0);
$esercizioId = (int)($payload['esercizioId'] ?? 0);
$svoltaIl = trim((string)($payload['svoltaIl'] ?? ''));
$serie = is_array($payload['serie'] ?? null) ? $payload['serie'] : [];

if ($programId <= 0 || $esercizioId <= 0 || $svoltaIl === '' || empty($serie)) {
  http_response_code(422);
  echo json_encode(['ok' => false, 'error' => 'Parametri non validi.']);
  exit;
}

$dt = DateTime::createFromFormat('Y-m-d\TH:i', $svoltaIl) ?: DateTime::createFromFormat('Y-m-d H:i:s', $svoltaIl);
if (!$dt) {
  http_response_code(422);
  echo json_encode(['ok' => false, 'error' => 'Data sessione non valida.']);
  exit;
}

$validateNumberOrNull = static function ($value, bool $allowFloat = true): ?float {
  if ($value === null || $value === '') {
    return null;
  }
  if (!is_numeric($value)) {
    throw new InvalidArgumentException('Valore numerico non valido.');
  }
  $n = (float)$value;
  if ($n < 0) {
    throw new InvalidArgumentException('Valore numerico non valido.');
  }
  if (!$allowFloat && floor($n) !== $n) {
    throw new InvalidArgumentException('Valore intero non valido.');
  }
  return $n;
};

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
       AND stato = 'attivo'
     LIMIT 1",
    [$programId, $clienteId]
  )->fetch();

  if (!$ownership) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Programma non assegnato o non più attivo.']);
    exit;
  }

  $assegnazione = Database::exec(
    'SELECT eg.idEsercizioGiorno, eg.giorno
     FROM EserciziGiorno eg
     INNER JOIN GiorniAllenamento g ON g.idGiorno = eg.giorno
     WHERE g.programma = ?
       AND eg.esercizio = ?
     ORDER BY eg.idEsercizioGiorno ASC
     LIMIT 1',
    [$programId, $esercizioId]
  )->fetch();

  if (!$assegnazione) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Esercizio non assegnato alla scheda.']);
    exit;
  }

  $pdo = Database::pdo();
  $pdo->beginTransaction();

  Database::exec(
    'INSERT INTO SessioniAllenamento (cliente, programma, giorno, svoltaIl)
     VALUES (?, ?, ?, ?)',
    [$clienteId, $programId, (int)$assegnazione['giorno'], $dt->format('Y-m-d H:i:s')]
  );

  $sessioneId = (int)$pdo->lastInsertId();

  $numeroSerie = 1;
  foreach ($serie as $item) {
    if (!is_array($item)) {
      continue;
    }

    $reps = $validateNumberOrNull($item['repsEffettive'] ?? null, false);
    $kg = $validateNumberOrNull($item['caricoEffettivo'] ?? null);
    $rpe = $validateNumberOrNull($item['rpeEffettivo'] ?? null);
    $note = isset($item['note']) ? trim((string)$item['note']) : null;
    $note = $note !== '' ? $note : null;

    Database::exec(
      'INSERT INTO SerieSvolte (sessione, seriePrescritta, esercizio, numeroSerie, repsEffettive, caricoEffettivo, rpeEffettivo, completata, note)
       VALUES (?, NULL, ?, ?, ?, ?, ?, 1, ?)',
      [$sessioneId, $esercizioId, $numeroSerie, $reps, $kg, $rpe, $note]
    );
    $numeroSerie++;
  }

  $pdo->commit();

  echo json_encode(['ok' => true, 'idSessione' => $sessioneId]);
} catch (Throwable $e) {
  if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
    $pdo->rollBack();
  }
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Errore salvataggio sessione.']);
}
