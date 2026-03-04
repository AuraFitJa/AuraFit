<?php
require __DIR__ . '/../common.php';

header('Content-Type: application/json; charset=utf-8');

if (!$dbAvailable) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $dbError ?? 'Database non disponibile.']);
  exit;
}

$programId = (int)($_GET['programId'] ?? 0);
$giornoId = (int)($_GET['giornoId'] ?? 0);
$esercizioGiornoId = (int)($_GET['esercizioGiornoId'] ?? 0);

if ($programId <= 0 || $giornoId <= 0 || $esercizioGiornoId <= 0) {
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
    'SELECT 1
     FROM AssegnazioniProgramma
     WHERE programma = ? AND cliente = ?
     LIMIT 1',
    [$programId, $clienteId]
  )->fetch();

  if (!$ownership) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Programma non assegnato al profilo.']);
    exit;
  }

  $exerciseRow = Database::exec(
    'SELECT eg.idEsercizioGiorno, eg.giorno, eg.istruzioni, eg.urlVideo,
            e.idEsercizio, e.nome, e.categoria, e.muscoloPrincipale, e.unitaPredefinita, e.descrizione
     FROM EserciziGiorno eg
     INNER JOIN GiorniAllenamento g ON g.idGiorno = eg.giorno
     INNER JOIN Esercizi e ON e.idEsercizio = eg.esercizio
     WHERE eg.idEsercizioGiorno = ?
       AND eg.giorno = ?
       AND g.programma = ?
     LIMIT 1',
    [$esercizioGiornoId, $giornoId, $programId]
  )->fetch();

  if (!$exerciseRow) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Esercizio non trovato per il programma indicato.']);
    exit;
  }

  $seriePrescritte = Database::exec(
    'SELECT idSeriePrescritta, numeroSerie, targetReps, repsMin, repsMax, targetCarico, targetRPE, recuperoSecondi, tempo, note
     FROM SeriePrescritte
     WHERE esercizioGiorno = ?
     ORDER BY numeroSerie ASC',
    [$esercizioGiornoId]
  )->fetchAll();

  $sessione = Database::exec(
    'SELECT idSessione
     FROM SessioniAllenamento
     WHERE cliente = ?
       AND giorno = ?
       AND DATE(svoltaIl) = CURDATE()
     ORDER BY idSessione DESC
     LIMIT 1',
    [$clienteId, $giornoId]
  )->fetch();

  $prescritteById = [];
  $extra = [];

  if ($sessione) {
    $sessioneId = (int)$sessione['idSessione'];

    $prescritteSvolte = Database::exec(
      'SELECT idSerieSvolta, seriePrescritta, repsEffettive, caricoEffettivo, rpeEffettivo, completata, note
       FROM SerieSvolte
       WHERE sessione = ?
         AND seriePrescritta IS NOT NULL',
      [$sessioneId]
    )->fetchAll();

    foreach ($prescritteSvolte as $row) {
      $prescritteById[(string)$row['seriePrescritta']] = [
        'idSerieSvolta' => (int)$row['idSerieSvolta'],
        'repsEffettive' => $row['repsEffettive'] !== null ? (float)$row['repsEffettive'] : null,
        'caricoEffettivo' => $row['caricoEffettivo'] !== null ? (float)$row['caricoEffettivo'] : null,
        'rpeEffettivo' => $row['rpeEffettivo'] !== null ? (float)$row['rpeEffettivo'] : null,
        'completata' => (int)$row['completata'],
        'note' => $row['note'],
      ];
    }

    $extra = Database::exec(
      'SELECT idSerieSvolta, esercizio, numeroSerie, repsEffettive, caricoEffettivo, rpeEffettivo, completata, note
       FROM SerieSvolte
       WHERE sessione = ?
         AND seriePrescritta IS NULL
         AND esercizio = ?
       ORDER BY numeroSerie ASC, idSerieSvolta ASC',
      [$sessioneId, (int)$exerciseRow['idEsercizio']]
    )->fetchAll();
  }

  echo json_encode([
    'ok' => true,
    'esercizio' => [
      'idEsercizio' => (int)$exerciseRow['idEsercizio'],
      'nome' => (string)$exerciseRow['nome'],
      'categoria' => $exerciseRow['categoria'],
      'muscoloPrincipale' => $exerciseRow['muscoloPrincipale'],
      'unitaPredefinita' => $exerciseRow['unitaPredefinita'],
      'descrizione' => $exerciseRow['descrizione'],
    ],
    'assegnazione' => [
      'istruzioni' => $exerciseRow['istruzioni'],
      'urlVideo' => $exerciseRow['urlVideo'],
    ],
    'seriePrescritte' => $seriePrescritte,
    'svolte' => [
      'prescritteById' => $prescritteById,
      'extra' => $extra,
    ],
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Errore nel caricamento del modal.']);
}
