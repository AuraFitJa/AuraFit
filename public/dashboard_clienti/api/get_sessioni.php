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
      last.repsEffettive AS lastReps,
      last.caricoEffettivo AS lastKg,
      last.svoltaIl AS lastSvoltaIl
    FROM EserciziGiorno eg
    INNER JOIN GiorniAllenamento g ON g.idGiorno = eg.giorno
    INNER JOIN Esercizi e ON e.idEsercizio = eg.esercizio
    LEFT JOIN (
      SELECT z.exercise_id, z.repsEffettive, z.caricoEffettivo, z.svoltaIl
      FROM (
        SELECT
          COALESCE(ss.esercizio, egp.esercizio) AS exercise_id,
          ss.repsEffettive,
          ss.caricoEffettivo,
          sa.svoltaIl,
          ss.idSerieSvolta
        FROM SerieSvolte ss
        INNER JOIN SessioniAllenamento sa ON sa.idSessione = ss.sessione
        LEFT JOIN SeriePrescritte sp ON sp.idSeriePrescritta = ss.seriePrescritta
        LEFT JOIN EserciziGiorno egp ON egp.idEsercizioGiorno = sp.esercizioGiorno
        WHERE sa.cliente = ?
          AND sa.programma = ?
      ) z
      INNER JOIN (
        SELECT pick.exercise_id, pick.svoltaIl, MAX(pick.idSerieSvolta) AS maxSerieId
        FROM (
          SELECT
            COALESCE(ss.esercizio, egp.esercizio) AS exercise_id,
            sa.svoltaIl,
            ss.idSerieSvolta
          FROM SerieSvolte ss
          INNER JOIN SessioniAllenamento sa ON sa.idSessione = ss.sessione
          LEFT JOIN SeriePrescritte sp ON sp.idSeriePrescritta = ss.seriePrescritta
          LEFT JOIN EserciziGiorno egp ON egp.idEsercizioGiorno = sp.esercizioGiorno
          INNER JOIN (
            SELECT t.exercise_id, MAX(t.svoltaIl) AS maxSvoltaIl
            FROM (
              SELECT
                COALESCE(ss.esercizio, egp.esercizio) AS exercise_id,
                sa.svoltaIl
              FROM SerieSvolte ss
              INNER JOIN SessioniAllenamento sa ON sa.idSessione = ss.sessione
              LEFT JOIN SeriePrescritte sp ON sp.idSeriePrescritta = ss.seriePrescritta
              LEFT JOIN EserciziGiorno egp ON egp.idEsercizioGiorno = sp.esercizioGiorno
              WHERE sa.cliente = ?
                AND sa.programma = ?
            ) t
            GROUP BY t.exercise_id
          ) m
            ON m.exercise_id = COALESCE(ss.esercizio, egp.esercizio)
           AND m.maxSvoltaIl = sa.svoltaIl
          WHERE sa.cliente = ?
            AND sa.programma = ?
        ) pick
        GROUP BY pick.exercise_id, pick.svoltaIl
      ) chosen
        ON chosen.exercise_id = z.exercise_id
       AND chosen.svoltaIl = z.svoltaIl
       AND chosen.maxSerieId = z.idSerieSvolta
    ) last ON last.exercise_id = e.idEsercizio
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
  error_log('get_sessioni.php error: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Errore caricamento sessioni.']);
}
