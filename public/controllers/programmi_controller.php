<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../models/programmi_model.php';

function jsonResponse(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

$user = $_SESSION['user'] ?? null;
if (!$user && isset($_SESSION['idUtente'])) {
    $user = [
        'idUtente' => (int)$_SESSION['idUtente'],
        'roles' => (array)($_SESSION['roles'] ?? []),
    ];
}

if (!$user || empty($user['idUtente'])) {
    jsonResponse(401, ['ok' => false, 'message' => 'Sessione non valida.']);
}

$roles = array_map('strtolower', (array)($user['roles'] ?? []));
if (!in_array('pt', $roles, true)) {
    jsonResponse(403, ['ok' => false, 'message' => 'Accesso consentito solo ai PT.']);
}

$professionistaId = ProgrammiModel::getProfessionistaIdByUserId((int)$user['idUtente']);
if (!$professionistaId) {
    jsonResponse(409, ['ok' => false, 'message' => 'Profilo professionista non trovato.']);
}

$action = (string)($_REQUEST['action'] ?? '');

try {
    switch ($action) {
        case 'listLibrary':
            $folders = ProgrammiModel::listFolders($professionistaId);
            $programs = ProgrammiModel::listProgramTemplates((int)$user['idUtente']);
            jsonResponse(200, ['ok' => true, 'folders' => $folders, 'programs' => $programs]);
            break;

        case 'createFolder':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(405, ['ok' => false, 'message' => 'Metodo non consentito.']);
            }
            $nome = trim((string)($_POST['nome'] ?? ''));
            if ($nome === '') {
                jsonResponse(422, ['ok' => false, 'message' => 'Nome cartella obbligatorio.']);
            }
            $id = ProgrammiModel::createFolder($professionistaId, $nome);
            jsonResponse(201, ['ok' => true, 'idCartella' => $id]);
            break;

        case 'createProgramTemplate':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(405, ['ok' => false, 'message' => 'Metodo non consentito.']);
            }
            $titolo = trim((string)($_POST['titolo'] ?? ''));
            $descrizione = trim((string)($_POST['descrizione'] ?? ''));
            $cartellaIdRaw = $_POST['cartellaId'] ?? null;
            $cartellaId = ($cartellaIdRaw === null || $cartellaIdRaw === '') ? null : (int)$cartellaIdRaw;

            if ($titolo === '') {
                jsonResponse(422, ['ok' => false, 'message' => 'Titolo programma obbligatorio.']);
            }

            $id = ProgrammiModel::createProgramTemplate((int)$user['idUtente'], $cartellaId, $titolo, $descrizione);
            jsonResponse(201, ['ok' => true, 'idProgramma' => $id]);
            break;

        case 'getProgramDetails':
            $idProgramma = (int)($_GET['idProgramma'] ?? 0);
            if ($idProgramma < 1) {
                jsonResponse(422, ['ok' => false, 'message' => 'Programma non valido.']);
            }

            $program = ProgrammiModel::getProgramDetails($idProgramma, (int)$user['idUtente']);
            if (!$program) {
                jsonResponse(404, ['ok' => false, 'message' => 'Programma non trovato.']);
            }

            jsonResponse(200, ['ok' => true, 'program' => $program]);
            break;

        case 'addGiornoToProgram':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(405, ['ok' => false, 'message' => 'Metodo non consentito.']);
            }
            $idProgramma = (int)($_POST['idProgramma'] ?? 0);
            $nome = trim((string)($_POST['nome'] ?? 'Nuovo giorno'));
            if ($idProgramma < 1 || !ProgrammiModel::isProgramOwnedByUser($idProgramma, (int)$user['idUtente'])) {
                jsonResponse(403, ['ok' => false, 'message' => 'Programma non modificabile.']);
            }

            $idGiorno = ProgrammiModel::addGiornoToProgram($idProgramma, $nome);
            jsonResponse(201, ['ok' => true, 'idGiorno' => $idGiorno]);
            break;

        case 'renameProgram':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(405, ['ok' => false, 'message' => 'Metodo non consentito.']);
            }
            $idProgramma = (int)($_POST['idProgramma'] ?? 0);
            $titolo = trim((string)($_POST['titolo'] ?? ''));
            $descrizione = trim((string)($_POST['descrizione'] ?? ''));
            if ($idProgramma < 1 || $titolo === '') {
                jsonResponse(422, ['ok' => false, 'message' => 'Dati non validi.']);
            }
            if (!ProgrammiModel::isProgramOwnedByUser($idProgramma, (int)$user['idUtente'])) {
                jsonResponse(403, ['ok' => false, 'message' => 'Programma non modificabile.']);
            }

            ProgrammiModel::renameProgram($idProgramma, $titolo, $descrizione);
            jsonResponse(200, ['ok' => true]);
            break;

        case 'deleteProgram':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(405, ['ok' => false, 'message' => 'Metodo non consentito.']);
            }
            $idProgramma = (int)($_POST['idProgramma'] ?? 0);
            if ($idProgramma < 1 || !ProgrammiModel::isProgramOwnedByUser($idProgramma, (int)$user['idUtente'])) {
                jsonResponse(403, ['ok' => false, 'message' => 'Programma non eliminabile.']);
            }
            ProgrammiModel::deleteProgram($idProgramma);
            jsonResponse(200, ['ok' => true]);
            break;

        case 'assignProgramToClient':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(405, ['ok' => false, 'message' => 'Metodo non consentito.']);
            }
            $idProgramma = (int)($_POST['idProgramma'] ?? 0);
            $idCliente = (int)($_POST['idCliente'] ?? 0);
            $stato = trim((string)($_POST['stato'] ?? 'attiva'));

            if ($idProgramma < 1 || $idCliente < 1) {
                jsonResponse(422, ['ok' => false, 'message' => 'Dati assegnazione non validi.']);
            }
            if (!ProgrammiModel::isProgramOwnedByUser($idProgramma, (int)$user['idUtente'])) {
                jsonResponse(403, ['ok' => false, 'message' => 'Programma non assegnabile.']);
            }

            $pdo = Database::pdo();
            $pdo->beginTransaction();
            $idAssegnazione = ProgrammiModel::assignProgramToClient($idProgramma, $idCliente, $stato);
            $pdo->commit();

            jsonResponse(201, ['ok' => true, 'idAssegnazione' => $idAssegnazione]);
            break;

        case 'duplicateProgram':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(405, ['ok' => false, 'message' => 'Metodo non consentito.']);
            }
            $idProgramma = (int)($_POST['idProgramma'] ?? 0);
            $titolo = trim((string)($_POST['titolo'] ?? 'Copia programma'));
            if ($idProgramma < 1) {
                jsonResponse(422, ['ok' => false, 'message' => 'Programma non valido.']);
            }

            $newId = ProgrammiModel::duplicateProgram($idProgramma, (int)$user['idUtente'], $titolo);
            jsonResponse(201, ['ok' => true, 'idProgramma' => $newId]);
            break;

        case 'listClients':
            $clients = ProgrammiModel::listPtClients($professionistaId);
            jsonResponse(200, ['ok' => true, 'clients' => $clients]);
            break;

        default:
            jsonResponse(400, ['ok' => false, 'message' => 'Action non valida.']);
    }
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonResponse(500, ['ok' => false, 'message' => 'Errore interno controller programmi.']);
}
