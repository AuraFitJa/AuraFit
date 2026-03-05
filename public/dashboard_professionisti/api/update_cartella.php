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

$idCartella = filter_var($payload['idCartella'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$nome = trim((string)($payload['nome'] ?? ''));
$descrizioneInput = $payload['descrizione'] ?? null;
$descrizione = is_string($descrizioneInput) ? trim($descrizioneInput) : '';

if ($idCartella === false) {
  http_response_code(422);
  echo json_encode(['ok' => false, 'error' => 'idCartella non valido.']);
  exit;
}

if ($nome === '') {
  http_response_code(422);
  echo json_encode(['ok' => false, 'error' => 'Nome cartella obbligatorio.']);
  exit;
}

if (mb_strlen($nome) > 150) {
  http_response_code(422);
  echo json_encode(['ok' => false, 'error' => 'Nome cartella troppo lungo (max 150).']);
  exit;
}

if (mb_strlen($descrizione) > 500) {
  http_response_code(422);
  echo json_encode(['ok' => false, 'error' => 'Descrizione troppo lunga (max 500).']);
  exit;
}

try {
  $professionistaId = getProfessionistaId($userId);
  if (!$professionistaId) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Profilo professionista non trovato.']);
    exit;
  }

  $hasDescrizioneColumn = false;
  $hasAggiornatoIlColumn = false;

  try {
    $hasDescrizioneColumn = (bool)Database::exec(
      "SHOW COLUMNS FROM ProgrammiCartelle LIKE 'descrizione'"
    )->fetch();
    $hasAggiornatoIlColumn = (bool)Database::exec(
      "SHOW COLUMNS FROM ProgrammiCartelle LIKE 'aggiornatoIl'"
    )->fetch();
  } catch (Throwable $e) {
    $hasDescrizioneColumn = false;
    $hasAggiornatoIlColumn = false;
  }

  $ownership = Database::exec(
    'SELECT 1 FROM ProgrammiCartelle WHERE idCartella = ? AND professionista = ? LIMIT 1',
    [$idCartella, $professionistaId]
  )->fetch();

  if (!$ownership) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Cartella non autorizzata.']);
    exit;
  }

  if ($hasDescrizioneColumn) {
    $fields = ['nome = ?', 'descrizione = ?'];
    $params = [$nome, ($descrizione === '' ? null : $descrizione)];
  } else {
    $fields = ['nome = ?'];
    $params = [$nome];
  }

  if ($hasAggiornatoIlColumn) {
    $fields[] = 'aggiornatoIl = NOW()';
  }

  $params[] = $idCartella;
  $params[] = $professionistaId;

  $sql = sprintf(
    'UPDATE ProgrammiCartelle SET %s WHERE idCartella = ? AND professionista = ? LIMIT 1',
    implode(', ', $fields)
  );

  Database::exec($sql, $params);
  echo json_encode([
    'ok' => true,
    'cartella' => [
      'idCartella' => (int)$idCartella,
      'nome' => $nome,
      'descrizione' => $hasDescrizioneColumn ? ($descrizione === '' ? null : $descrizione) : null,
    ]
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Errore aggiornamento cartella.']);
}
