<?php
require __DIR__ . '/../common.php';

header('Content-Type: application/json; charset=utf-8');

if (!$dbAvailable) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $dbError ?? 'Database non disponibile.']);
  exit;
}

$programId = (int)($_GET['programId'] ?? 0);
$sessioneId = (int)($_GET['sessioneId'] ?? 0);

if ($programId <= 0 || $sessioneId <= 0) {
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
       AND stato = 'attivo'
     LIMIT 1",
    [$programId, $clienteId]
  )->fetch();

  if (!$ownership) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Programma non assegnato o non più attivo.']);
    exit;
  }

  $sessione = Database::exec(
    'SELECT sa.idSessione, sa.svoltaIl, sa.durataMinuti, sa.noteSessione, sa.giorno,
            g.nome AS giornoNome
     FROM SessioniAllenamento sa
     LEFT JOIN GiorniAllenamento g ON g.idGiorno = sa.giorno
     WHERE sa.idSessione = ?
       AND sa.cliente = ?
       AND sa.programma = ?
     LIMIT 1',
    [$sessioneId, $clienteId, $programId]
  )->fetch();

  if (!$sessione) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Sessione non trovata.']);
    exit;
  }

  $serie = Database::exec(
    "SELECT
      ss.idSerieSvolta,
      ss.seriePrescritta,
      ss.esercizio AS esercizioExtra,
      ss.numeroSerie AS numeroExtra,
      ss.repsEffettive,
      ss.caricoEffettivo,
      ss.rpeEffettivo,
      ss.completata,
      ss.note,
      sp.numeroSerie AS numeroPrescritto,
      ePres.nome AS nomeEsercizioPrescritto,
      eExtra.nome AS nomeEsercizioExtra
    FROM SerieSvolte ss
    LEFT JOIN SeriePrescritte sp ON sp.idSeriePrescritta = ss.seriePrescritta
    LEFT JOIN EserciziGiorno eg ON eg.idEsercizioGiorno = sp.esercizioGiorno
    LEFT JOIN Esercizi ePres ON ePres.idEsercizio = eg.esercizio
    LEFT JOIN Esercizi eExtra ON eExtra.idEsercizio = ss.esercizio
    WHERE ss.sessione = ?
    ORDER BY
      COALESCE(ePres.nome, eExtra.nome, 'ZZZ') ASC,
      COALESCE(sp.numeroSerie, ss.numeroSerie, 9999) ASC,
      ss.idSerieSvolta ASC",
    [$sessioneId]
  )->fetchAll();

  $gruppi = [];
  foreach ($serie as $row) {
    $nome = (string)($row['nomeEsercizioPrescritto'] ?? $row['nomeEsercizioExtra'] ?? 'Esercizio');
    if (!isset($gruppi[$nome])) {
      $gruppi[$nome] = [
        'nome' => $nome,
        'serie' => [],
      ];
    }

    $gruppi[$nome]['serie'][] = [
      'tipo' => $row['seriePrescritta'] !== null ? 'prescritta' : 'extra',
      'numeroSerie' => $row['numeroPrescritto'] ?? $row['numeroExtra'],
      'caricoEffettivo' => $row['caricoEffettivo'] !== null ? (float)$row['caricoEffettivo'] : null,
      'repsEffettive' => $row['repsEffettive'] !== null ? (float)$row['repsEffettive'] : null,
      'rpeEffettivo' => $row['rpeEffettivo'] !== null ? (float)$row['rpeEffettivo'] : null,
      'completata' => (int)($row['completata'] ?? 0),
      'note' => $row['note'],
    ];
  }

  echo json_encode([
    'ok' => true,
    'sessione' => [
      'idSessione' => (int)$sessione['idSessione'],
      'svoltaIl' => $sessione['svoltaIl'],
      'giornoNome' => $sessione['giornoNome'],
      'durataMinuti' => $sessione['durataMinuti'] !== null ? (int)$sessione['durataMinuti'] : null,
      'noteSessione' => $sessione['noteSessione'],
    ],
    'esercizi' => array_values($gruppi),
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Errore caricamento dettaglio sessione.']);
}
