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

$idCliente = (int)($payload['idCliente'] ?? 0);
$idProgramma = (int)($payload['idProgramma'] ?? 0);

if ($idCliente <= 0 || $idProgramma <= 0) {
  http_response_code(422);
  echo json_encode(['ok' => false, 'error' => 'Parametri non validi.']);
  exit;
}

try {
  $professionistaId = getProfessionistaId($userId);
  if (!$professionistaId) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Profilo professionista non trovato.']);
    exit;
  }

  $tipiConsentiti = [];
  if ($isPt) {
    $tipiConsentiti[] = 'pt';
  }
  if ($isNutrizionista) {
    $tipiConsentiti[] = 'nutrizionista';
  }

  if (!$tipiConsentiti) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Ruolo non autorizzato.']);
    exit;
  }

  $placeholders = implode(',', array_fill(0, count($tipiConsentiti), '?'));
  $associazione = Database::exec(
    "SELECT 1
     FROM Associazioni
     WHERE professionista = ?
       AND cliente = ?
       AND attivaFlag = 1
       AND tipoAssociazione IN ($placeholders)
     LIMIT 1",
    array_merge([$professionistaId, $idCliente], $tipiConsentiti)
  )->fetch();

  if (!$associazione) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Cliente non associato al professionista.']);
    exit;
  }

  $assegnazione = Database::exec(
    "SELECT 1
     FROM AssegnazioniProgramma
     WHERE cliente = ?
       AND programma = ?
       AND stato = 'attivo'
     LIMIT 1",
    [$idCliente, $idProgramma]
  )->fetch();

  if (!$assegnazione) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Assegnazione attiva non trovata.']);
    exit;
  }

  Database::exec(
    "UPDATE AssegnazioniProgramma
     SET stato = 'revocato'
     WHERE cliente = ?
       AND programma = ?
       AND stato = 'attivo'
     LIMIT 1",
    [$idCliente, $idProgramma]
  );

  echo json_encode(['ok' => true]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Errore durante la rimozione del programma.']);
}
