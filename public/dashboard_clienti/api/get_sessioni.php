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
$limit = (int)($_GET['limit'] ?? 10);

if ($programId <= 0 || $cursor < 0) {
  http_response_code(422);
  echo json_encode(['ok' => false, 'error' => 'Parametri non validi.']);
  exit;
}

if ($limit <= 0) {
  $limit = 10;
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
       AND stato = 'attivo'
     LIMIT 1",
    [$programId, $clienteId]
  )->fetch();

  if (!$ownership) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Programma non assegnato o non più attivo.']);
    exit;
  }

  if ($cursor === 0) {
    $rows = Database::exec(
      'SELECT sa.idSessione, sa.svoltaIl, sa.durataMinuti, sa.noteSessione,
              g.nome AS giornoNome,
              (SELECT COUNT(*) FROM SerieSvolte ss WHERE ss.sessione = sa.idSessione) AS totSerie
       FROM SessioniAllenamento sa
       LEFT JOIN GiorniAllenamento g ON g.idGiorno = sa.giorno
       WHERE sa.cliente = ?
         AND sa.programma = ?
       ORDER BY sa.svoltaIl DESC, sa.idSessione DESC
       LIMIT ?',
      [$clienteId, $programId, $limit]
    )->fetchAll();
  } else {
    $rows = Database::exec(
      'SELECT sa.idSessione, sa.svoltaIl, sa.durataMinuti, sa.noteSessione,
              g.nome AS giornoNome,
              (SELECT COUNT(*) FROM SerieSvolte ss WHERE ss.sessione = sa.idSessione) AS totSerie
       FROM SessioniAllenamento sa
       LEFT JOIN GiorniAllenamento g ON g.idGiorno = sa.giorno
       WHERE sa.cliente = ?
         AND sa.programma = ?
         AND sa.idSessione < ?
       ORDER BY sa.svoltaIl DESC, sa.idSessione DESC
       LIMIT ?',
      [$clienteId, $programId, $cursor, $limit]
    )->fetchAll();
  }

  $items = [];
  foreach ($rows as $row) {
    $items[] = [
      'idSessione' => (int)$row['idSessione'],
      'svoltaIl' => $row['svoltaIl'],
      'giornoNome' => $row['giornoNome'],
      'durataMinuti' => $row['durataMinuti'] !== null ? (int)$row['durataMinuti'] : null,
      'totSerie' => (int)$row['totSerie'],
    ];
  }

  $nextCursor = 0;
  if (!empty($items)) {
    $nextCursor = (int)$items[count($items) - 1]['idSessione'];
    if (count($items) < $limit) {
      $nextCursor = 0;
    }
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
