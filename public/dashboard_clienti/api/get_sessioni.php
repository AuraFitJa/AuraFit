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
$debug = (int)($_GET['debug'] ?? 0) === 1;

if ($programId <= 0 || $cursor < 0) {
  http_response_code(422);
  echo json_encode(['ok' => false, 'error' => 'Parametri non validi.']);
  exit;
}

$limit = min(max((int)$limit, 1), 30);

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

  $params = [$clienteId, $programId, $programId];
  $whereCursor = '';
  if ($cursor > 0) {
    $whereCursor = ' AND eg.idEsercizioGiorno < ? ';
    $params[] = $cursor;
  }

  $rows = Database::exec(
    "SELECT
      eg.idEsercizioGiorno,
      e.idEsercizio,
      e.nome AS nomeEsercizio,
      lr.repsEffettive AS lastReps,
      lr.caricoEffettivo AS lastKg,
      lr.svoltaIl AS lastSvoltaIl
    FROM EserciziGiorno eg
    INNER JOIN GiorniAllenamento g ON g.idGiorno = eg.giorno
    INNER JOIN Esercizi e ON e.idEsercizio = eg.esercizio
    LEFT JOIN (
      SELECT
        COALESCE(ss.esercizio, egp.esercizio) AS exercise_id,
        ss.repsEffettive,
        ss.caricoEffettivo,
        sa.svoltaIl
      FROM SerieSvolte ss
      INNER JOIN SessioniAllenamento sa ON sa.idSessione = ss.sessione
      LEFT JOIN SeriePrescritte sp ON sp.idSeriePrescritta = ss.seriePrescritta
      LEFT JOIN EserciziGiorno egp ON egp.idEsercizioGiorno = sp.esercizioGiorno
      INNER JOIN (
        SELECT
          COALESCE(ss2.esercizio, egp2.esercizio) AS exercise_id,
          MAX(CONCAT(DATE_FORMAT(sa2.svoltaIl, '%Y%m%d%H%i%S'), '-', LPAD(ss2.idSerieSvolta, 20, '0'))) AS maxKey
        FROM SerieSvolte ss2
        INNER JOIN SessioniAllenamento sa2 ON sa2.idSessione = ss2.sessione
        LEFT JOIN SeriePrescritte sp2 ON sp2.idSeriePrescritta = ss2.seriePrescritta
        LEFT JOIN EserciziGiorno egp2 ON egp2.idEsercizioGiorno = sp2.esercizioGiorno
        WHERE sa2.cliente = ?
          AND sa2.programma = ?
          AND COALESCE(ss2.esercizio, egp2.esercizio) IS NOT NULL
        GROUP BY COALESCE(ss2.esercizio, egp2.esercizio)
      ) lk
        ON lk.exercise_id = COALESCE(ss.esercizio, egp.esercizio)
       AND lk.maxKey = CONCAT(DATE_FORMAT(sa.svoltaIl, '%Y%m%d%H%i%S'), '-', LPAD(ss.idSerieSvolta, 20, '0'))
    ) lr ON lr.exercise_id = e.idEsercizio
    WHERE g.programma = ?
      {$whereCursor}
    ORDER BY eg.idEsercizioGiorno DESC
    LIMIT $limit",
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
  exit;
} catch (Throwable $e) {
  error_log('get_sessioni.php error: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => $debug ? ('Errore caricamento sessioni: ' . $e->getMessage()) : 'Errore caricamento sessioni.',
  ]);
  exit;
}
