<?php
require __DIR__ . '/../common.php';

header('Content-Type: application/json; charset=utf-8');

if (!$dbAvailable) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $dbError ?? 'Database non disponibile.']);
  exit;
}

$idProgramma = filter_input(INPUT_GET, 'idProgramma', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($idProgramma === false || $idProgramma === null) {
  http_response_code(422);
  echo json_encode(['ok' => false, 'error' => 'idProgramma non valido.']);
  exit;
}

try {
  $professionistaId = getProfessionistaId($userId);
  if (!$professionistaId) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Profilo professionista non trovato.']);
    exit;
  }

  $ownership = Database::exec(
    "SELECT idProgramma
     FROM ProgrammiAllenamento
     WHERE idProgramma = ?
       AND creatoreUtente = ?
       AND stato <> 'archiviato'
     LIMIT 1",
    [$idProgramma, $userId]
  )->fetch();

  if (!$ownership) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Programma non autorizzato.']);
    exit;
  }

  $clienti = Database::exec(
    "SELECT c.idCliente, u.nome, u.cognome
     FROM Associazioni a
     INNER JOIN Clienti c ON c.idCliente = a.cliente
     INNER JOIN Utenti u ON u.idUtente = c.idUtente
     WHERE a.professionista = ?
       AND a.attivaFlag = 1
       AND a.tipoAssociazione = 'pt'
     ORDER BY u.nome ASC, u.cognome ASC",
    [$professionistaId]
  )->fetchAll();

  $assegnatiRows = Database::exec(
    'SELECT cliente FROM AssegnazioniProgramma WHERE programma = ?',
    [$idProgramma]
  )->fetchAll();

  $assegnatiMap = [];
  foreach ($assegnatiRows as $row) {
    $assegnatiMap[(int)$row['cliente']] = true;
  }

  $clientiPayload = [];
  foreach ($clienti as $cliente) {
    $idCliente = (int)$cliente['idCliente'];
    $clientiPayload[] = [
      'idCliente' => $idCliente,
      'nome' => (string)($cliente['nome'] ?? ''),
      'cognome' => (string)($cliente['cognome'] ?? ''),
      'giaAssegnato' => isset($assegnatiMap[$idCliente]),
    ];
  }

  echo json_encode([
    'ok' => true,
    'clienti' => $clientiPayload,
  ]);
  exit;
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Errore nel recupero clienti.']);
  exit;
}
