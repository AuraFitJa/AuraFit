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
$giornoId = (int)($payload['giornoId'] ?? 0);
$esercizioGiornoId = (int)($payload['esercizioGiornoId'] ?? 0);
$esercizioId = (int)($payload['esercizioId'] ?? 0);
$durataMinuti = array_key_exists('durataMinuti', $payload) ? $payload['durataMinuti'] : null;
$noteSessione = array_key_exists('noteSessione', $payload) ? trim((string)$payload['noteSessione']) : null;
$prescritte = is_array($payload['prescritte'] ?? null) ? $payload['prescritte'] : [];
$extra = is_array($payload['extra'] ?? null) ? $payload['extra'] : [];
$extraToDelete = is_array($payload['extraToDelete'] ?? null) ? $payload['extraToDelete'] : [];

if ($programId <= 0 || $giornoId <= 0 || $esercizioGiornoId <= 0 || $esercizioId <= 0) {
  http_response_code(422);
  echo json_encode(['ok' => false, 'error' => 'Parametri principali non validi.']);
  exit;
}

$durataParsed = null;
if ($durataMinuti !== null && $durataMinuti !== '') {
  if (!is_numeric($durataMinuti) || (int)$durataMinuti < 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'durataMinuti non valida.']);
    exit;
  }
  $durataParsed = (int)$durataMinuti;
}

$noteParsed = $noteSessione !== null && $noteSessione !== '' ? $noteSessione : null;

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

  $exerciseValidation = Database::exec(
    'SELECT eg.idEsercizioGiorno
     FROM EserciziGiorno eg
     INNER JOIN GiorniAllenamento g ON g.idGiorno = eg.giorno
     WHERE eg.idEsercizioGiorno = ?
       AND eg.giorno = ?
       AND g.programma = ?
       AND eg.esercizio = ?
     LIMIT 1',
    [$esercizioGiornoId, $giornoId, $programId, $esercizioId]
  )->fetch();

  if (!$exerciseValidation) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Esercizio non valido per il programma.']);
    exit;
  }

  $pdo = Database::pdo();
  $pdo->beginTransaction();

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

  if (!$sessione) {
    Database::exec(
      'INSERT INTO SessioniAllenamento (cliente, programma, giorno, svoltaIl, durataMinuti, noteSessione)
       VALUES (?, ?, ?, NOW(), ?, ?)',
      [$clienteId, $programId, $giornoId, $durataParsed, $noteParsed]
    );
    $sessioneId = (int)$pdo->lastInsertId();
  } else {
    $sessioneId = (int)$sessione['idSessione'];
    if (array_key_exists('durataMinuti', $payload) || array_key_exists('noteSessione', $payload)) {
      Database::exec(
        'UPDATE SessioniAllenamento
         SET durataMinuti = ?, noteSessione = ?
         WHERE idSessione = ?',
        [$durataParsed, $noteParsed, $sessioneId]
      );
    }
  }

  $savedPrescritte = 0;
  foreach ($prescritte as $item) {
    if (!is_array($item)) {
      throw new InvalidArgumentException('Formato serie prescritte non valido.');
    }

    $seriePrescrittaId = (int)($item['seriePrescrittaId'] ?? 0);
    if ($seriePrescrittaId <= 0) {
      throw new InvalidArgumentException('seriePrescrittaId non valido.');
    }

    $exists = Database::exec(
      'SELECT 1
       FROM SeriePrescritte
       WHERE idSeriePrescritta = ?
         AND esercizioGiorno = ?
       LIMIT 1',
      [$seriePrescrittaId, $esercizioGiornoId]
    )->fetch();

    if (!$exists) {
      throw new InvalidArgumentException('Serie prescritta non coerente con esercizio.');
    }

    $repsEffettive = $validateNumberOrNull($item['repsEffettive'] ?? null, false);
    $caricoEffettivo = $validateNumberOrNull($item['caricoEffettivo'] ?? null);
    $rpeEffettivo = $validateNumberOrNull($item['rpeEffettivo'] ?? null);
    $completata = !empty($item['completata']) ? 1 : 0;
    $note = isset($item['note']) ? trim((string)$item['note']) : null;
    $note = $note !== '' ? $note : null;

    $existing = Database::exec(
      'SELECT idSerieSvolta
       FROM SerieSvolte
       WHERE sessione = ? AND seriePrescritta = ?
       LIMIT 1',
      [$sessioneId, $seriePrescrittaId]
    )->fetch();

    if ($existing) {
      Database::exec(
        'UPDATE SerieSvolte
         SET repsEffettive = ?, caricoEffettivo = ?, rpeEffettivo = ?, completata = ?, note = ?
         WHERE idSerieSvolta = ?',
        [$repsEffettive, $caricoEffettivo, $rpeEffettivo, $completata, $note, (int)$existing['idSerieSvolta']]
      );
    } else {
      Database::exec(
        'INSERT INTO SerieSvolte (sessione, seriePrescritta, esercizio, numeroSerie, repsEffettive, caricoEffettivo, rpeEffettivo, completata, note)
         VALUES (?, ?, NULL, NULL, ?, ?, ?, ?, ?)',
        [$sessioneId, $seriePrescrittaId, $repsEffettive, $caricoEffettivo, $rpeEffettivo, $completata, $note]
      );
    }

    $savedPrescritte++;
  }

  foreach ($extraToDelete as $deleteIdRaw) {
    $deleteId = (int)$deleteIdRaw;
    if ($deleteId <= 0) {
      continue;
    }

    Database::exec(
      'DELETE FROM SerieSvolte
       WHERE idSerieSvolta = ?
         AND sessione = ?
         AND seriePrescritta IS NULL
         AND esercizio = ?',
      [$deleteId, $sessioneId, $esercizioId]
    );
  }

  $maxNRow = Database::exec(
    'SELECT COALESCE(MAX(numeroSerie), 0) AS maxN
     FROM SerieSvolte
     WHERE sessione = ?
       AND seriePrescritta IS NULL
       AND esercizio = ?',
    [$sessioneId, $esercizioId]
  )->fetch();
  $maxN = (int)($maxNRow['maxN'] ?? 0);

  $savedExtra = 0;
  foreach ($extra as $item) {
    if (!is_array($item)) {
      throw new InvalidArgumentException('Formato serie extra non valido.');
    }

    $repsEffettive = $validateNumberOrNull($item['repsEffettive'] ?? null, false);
    $caricoEffettivo = $validateNumberOrNull($item['caricoEffettivo'] ?? null);
    $rpeEffettivo = $validateNumberOrNull($item['rpeEffettivo'] ?? null);
    $completata = !empty($item['completata']) ? 1 : 0;
    $note = isset($item['note']) ? trim((string)$item['note']) : null;
    $note = $note !== '' ? $note : null;

    $idSerieSvolta = isset($item['idSerieSvolta']) && $item['idSerieSvolta'] !== null ? (int)$item['idSerieSvolta'] : 0;

    if ($idSerieSvolta > 0) {
      $exists = Database::exec(
        'SELECT 1
         FROM SerieSvolte
         WHERE idSerieSvolta = ?
           AND sessione = ?
           AND seriePrescritta IS NULL
           AND esercizio = ?
         LIMIT 1',
        [$idSerieSvolta, $sessioneId, $esercizioId]
      )->fetch();

      if (!$exists) {
        throw new InvalidArgumentException('Serie extra non valida.');
      }

      $numeroSerie = $validateNumberOrNull($item['numeroSerie'] ?? null, false);
      $numeroSerieInt = $numeroSerie !== null ? (int)$numeroSerie : null;

      if ($numeroSerieInt === null || $numeroSerieInt <= 0) {
        $currentNumber = Database::exec(
          'SELECT numeroSerie
           FROM SerieSvolte
           WHERE idSerieSvolta = ?
           LIMIT 1',
          [$idSerieSvolta]
        )->fetch();
        $numeroSerieInt = (int)($currentNumber['numeroSerie'] ?? 0);
      }

      Database::exec(
        'UPDATE SerieSvolte
         SET numeroSerie = ?, repsEffettive = ?, caricoEffettivo = ?, rpeEffettivo = ?, completata = ?, note = ?
         WHERE idSerieSvolta = ?',
        [$numeroSerieInt, $repsEffettive, $caricoEffettivo, $rpeEffettivo, $completata, $note, $idSerieSvolta]
      );
    } else {
      $numeroSerie = $validateNumberOrNull($item['numeroSerie'] ?? null, false);
      $numeroSerieInt = $numeroSerie !== null ? (int)$numeroSerie : 0;

      if ($numeroSerieInt <= 0) {
        $maxN++;
        $numeroSerieInt = $maxN;
      } else {
        while (Database::exec(
          'SELECT 1 FROM SerieSvolte
           WHERE sessione = ?
             AND seriePrescritta IS NULL
             AND esercizio = ?
             AND numeroSerie = ?
           LIMIT 1',
          [$sessioneId, $esercizioId, $numeroSerieInt]
        )->fetch()) {
          $numeroSerieInt++;
        }
        if ($numeroSerieInt > $maxN) {
          $maxN = $numeroSerieInt;
        }
      }

      Database::exec(
        'INSERT INTO SerieSvolte (sessione, seriePrescritta, esercizio, numeroSerie, repsEffettive, caricoEffettivo, rpeEffettivo, completata, note)
         VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?)',
        [$sessioneId, $esercizioId, $numeroSerieInt, $repsEffettive, $caricoEffettivo, $rpeEffettivo, $completata, $note]
      );
    }

    $savedExtra++;
  }

  $pdo->commit();

  echo json_encode([
    'ok' => true,
    'sessioneId' => $sessioneId,
    'savedPrescritte' => $savedPrescritte,
    'savedExtra' => $savedExtra,
  ]);
} catch (InvalidArgumentException $e) {
  if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
    $pdo->rollBack();
  }
  http_response_code(422);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
  if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
    $pdo->rollBack();
  }
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Errore durante il salvataggio delle serie svolte.']);
}
