<?php
require __DIR__ . '/../common.php';

header('Content-Type: application/json; charset=utf-8');

if (!$dbAvailable) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $dbError ?? 'Database non disponibile.']);
  exit;
}

$programId = (int)($_GET['programId'] ?? 0);
$esercizioId = (int)($_GET['esercizioId'] ?? 0);

if ($programId <= 0 || $esercizioId <= 0) {
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

  $esercizio = Database::exec(
    'SELECT DISTINCT e.idEsercizio, e.nome
     FROM EserciziGiorno eg
     INNER JOIN GiorniAllenamento g ON g.idGiorno = eg.giorno
     INNER JOIN Esercizi e ON e.idEsercizio = eg.esercizio
     WHERE g.programma = ?
       AND e.idEsercizio = ?
     LIMIT 1',
    [$programId, $esercizioId]
  )->fetch();

  if (!$esercizio) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Esercizio non trovato nel programma.']);
    exit;
  }

  $serie = Database::exec(
    'SELECT ss.idSerieSvolta, sa.svoltaIl, ss.numeroSerie, ss.repsEffettive, ss.caricoEffettivo, ss.rpeEffettivo, ss.completata, ss.note
     FROM SerieSvolte ss
     INNER JOIN SessioniAllenamento sa ON sa.idSessione = ss.sessione
     LEFT JOIN SeriePrescritte sp ON sp.idSeriePrescritta = ss.seriePrescritta
     LEFT JOIN EserciziGiorno egp ON egp.idEsercizioGiorno = sp.esercizioGiorno
     WHERE sa.cliente = ?
       AND sa.programma = ?
       AND COALESCE(ss.esercizio, egp.esercizio) = ?
     ORDER BY sa.svoltaIl DESC, ss.numeroSerie ASC, ss.idSerieSvolta DESC',
    [$clienteId, $programId, $esercizioId]
  )->fetchAll();

  echo json_encode([
    'ok' => true,
    'esercizio' => [
      'idEsercizio' => (int)$esercizio['idEsercizio'],
      'nome' => (string)$esercizio['nome'],
    ],
    'serie' => $serie,
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Errore caricamento dettaglio sessione.']);
}
