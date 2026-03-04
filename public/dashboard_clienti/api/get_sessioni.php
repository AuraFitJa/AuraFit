<?php
require __DIR__ . '/../common.php';

header('Content-Type: application/json; charset=utf-8');

if (!$dbAvailable) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $dbError ?? 'Database non disponibile.']);
  exit;
}

$programId = (int)($_GET['programId'] ?? 0);
$cursor = (int)($_GET['cursor'] ?? 0);
$limit = (int)($_GET['limit'] ?? 20);

if ($programId <= 0 || $cursor < 0) {
  http_response_code(422);
  echo json_encode(['ok' => false, 'error' => 'Parametri non validi.']);
  exit;
}

if ($limit <= 0) {
  $limit = 20;
}
$limit = min($limit, 30);

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

  $params = [
    $clienteId,
    $programId,
    $clienteId,
    $programId,
    $clienteId,
    $programId,
    $programId,
  ];
  $whereCursor = '';
  if ($cursor > 0) {
    $whereCursor = ' AND eg.idEsercizioGiorno < ? ';
    $params[] = $cursor;
  }
  $params[] = $limit;

  $rows = Database::exec(
    "SELECT
      eg.idEsercizioGiorno,
      e.idEsercizio,
      e.nome AS nomeEsercizio,
      (
        SELECT ss2.repsEffettive
        FROM SerieSvolte ss2
        INNER JOIN SessioniAllenamento sa2 ON sa2.idSessione = ss2.sessione
        LEFT JOIN SeriePrescritte sp2 ON sp2.idSeriePrescritta = ss2.seriePrescritta
        LEFT JOIN EserciziGiorno egp2 ON egp2.idEsercizioGiorno = sp2.esercizioGiorno
        WHERE sa2.cliente = ?
          AND sa2.programma = ?
          AND COALESCE(ss2.esercizio, egp2.esercizio) = e.idEsercizio
        ORDER BY sa2.svoltaIl DESC, ss2.idSerieSvolta DESC
        LIMIT 1
      ) AS lastReps,
      (
        SELECT ss3.caricoEffettivo
        FROM SerieSvolte ss3
        INNER JOIN SessioniAllenamento sa3 ON sa3.idSessione = ss3.sessione
        LEFT JOIN SeriePrescritte sp3 ON sp3.idSeriePrescritta = ss3.seriePrescritta
        LEFT JOIN EserciziGiorno egp3 ON egp3.idEsercizioGiorno = sp3.esercizioGiorno
        WHERE sa3.cliente = ?
          AND sa3.programma = ?
          AND COALESCE(ss3.esercizio, egp3.esercizio) = e.idEsercizio
        ORDER BY sa3.svoltaIl DESC, ss3.idSerieSvolta DESC
        LIMIT 1
      ) AS lastKg,
      (
        SELECT sa4.svoltaIl
        FROM SerieSvolte ss4
        INNER JOIN SessioniAllenamento sa4 ON sa4.idSessione = ss4.sessione
        LEFT JOIN SeriePrescritte sp4 ON sp4.idSeriePrescritta = ss4.seriePrescritta
        LEFT JOIN EserciziGiorno egp4 ON egp4.idEsercizioGiorno = sp4.esercizioGiorno
        WHERE sa4.cliente = ?
          AND sa4.programma = ?
          AND COALESCE(ss4.esercizio, egp4.esercizio) = e.idEsercizio
        ORDER BY sa4.svoltaIl DESC, ss4.idSerieSvolta DESC
        LIMIT 1
      ) AS lastSvoltaIl
    FROM EserciziGiorno eg
    INNER JOIN GiorniAllenamento g ON g.idGiorno = eg.giorno
    INNER JOIN Esercizi e ON e.idEsercizio = eg.esercizio
    WHERE g.programma = ?
      {$whereCursor}
    ORDER BY eg.idEsercizioGiorno DESC
    LIMIT ?",
    $params
  )->fetchAll();

  $items = [];
  foreach ($rows as $row) {
    $items[] = [
      'cursorId' => (int)$row['idEsercizioGiorno'],
      'idEsercizio' => (int)$row['idEsercizio'],
      'nomeEsercizio' => (string)$row['nomeEsercizio'],
      'lastReps' => $row['lastReps'] !== null ? (float)$row['lastReps'] : null,
      'lastKg' => $row['lastKg'] !== null ? (float)$row['lastKg'] : null,
      'lastSvoltaIl' => $row['lastSvoltaIl'],
    ];
  }

  $nextCursor = 0;
  if (!empty($items) && count($items) === $limit) {
    $nextCursor = (int)$items[count($items) - 1]['cursorId'];
  }

  echo json_encode([
    'ok' => true,
    'items' => $items,
    'nextCursor' => $nextCursor,
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Errore caricamento sessioni.']);
}
