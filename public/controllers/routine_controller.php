<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../models/routine_model.php';

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

$action = (string)($_REQUEST['action'] ?? '');
$userId = (int)$user['idUtente'];

try {
    switch ($action) {
        case 'getRoutineEditorData':
            $giorno = (int)($_GET['giorno'] ?? 0);
            if ($giorno < 1 || !RoutineModel::isDayOwnedByUser($giorno, $userId)) {
                jsonResponse(403, ['ok' => false, 'message' => 'Routine non accessibile.']);
            }
            $data = RoutineModel::getRoutineEditorData($giorno, $userId);
            jsonResponse(200, ['ok' => true, 'routine' => $data]);
            break;

        case 'searchExercises':
            $query = trim((string)($_GET['query'] ?? ''));
            $equipment = trim((string)($_GET['equipment'] ?? ''));
            $muscle = trim((string)($_GET['muscle'] ?? ''));
            $items = RoutineModel::searchExercises($query, $equipment, $muscle);
            jsonResponse(200, ['ok' => true, 'items' => $items]);
            break;

        case 'addExerciseToDay':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(405, ['ok' => false, 'message' => 'Metodo non consentito.']);
            }
            $giorno = (int)($_POST['giorno'] ?? 0);
            $esercizio = (int)($_POST['esercizio'] ?? 0);
            if ($giorno < 1 || $esercizio < 1 || !RoutineModel::isDayOwnedByUser($giorno, $userId)) {
                jsonResponse(403, ['ok' => false, 'message' => 'Operazione non consentita.']);
            }
            $id = RoutineModel::addExerciseToDay($giorno, $esercizio);
            jsonResponse(201, ['ok' => true, 'idEsercizioGiorno' => $id]);
            break;

        case 'removeExerciseFromDay':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(405, ['ok' => false, 'message' => 'Metodo non consentito.']);
            }
            $id = (int)($_POST['idEsercizioGiorno'] ?? 0);
            if ($id < 1 || !RoutineModel::isExerciseDayOwnedByUser($id, $userId)) {
                jsonResponse(403, ['ok' => false, 'message' => 'Esercizio non accessibile.']);
            }
            RoutineModel::removeExerciseFromDay($id);
            jsonResponse(200, ['ok' => true]);
            break;

        case 'reorderExercises':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(405, ['ok' => false, 'message' => 'Metodo non consentito.']);
            }
            $giorno = (int)($_POST['giorno'] ?? 0);
            $orderedIds = $_POST['orderedIds'] ?? [];
            if (!is_array($orderedIds) || $giorno < 1 || !RoutineModel::isDayOwnedByUser($giorno, $userId)) {
                jsonResponse(422, ['ok' => false, 'message' => 'Ordine non valido.']);
            }
            RoutineModel::reorderExercises($giorno, $orderedIds);
            jsonResponse(200, ['ok' => true]);
            break;

        case 'saveSetsForExercise':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(405, ['ok' => false, 'message' => 'Metodo non consentito.']);
            }
            $id = (int)($_POST['idEsercizioGiorno'] ?? 0);
            $setsRaw = (string)($_POST['sets'] ?? '[]');
            $sets = json_decode($setsRaw, true);

            if ($id < 1 || !is_array($sets) || !RoutineModel::isExerciseDayOwnedByUser($id, $userId)) {
                jsonResponse(422, ['ok' => false, 'message' => 'Serie non valide.']);
            }

            RoutineModel::saveSetsForExercise($id, $sets);
            jsonResponse(200, ['ok' => true]);
            break;

        case 'updateRoutineNotes':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(405, ['ok' => false, 'message' => 'Metodo non consentito.']);
            }
            $giorno = (int)($_POST['giorno'] ?? 0);
            if ($giorno < 1 || !RoutineModel::isDayOwnedByUser($giorno, $userId)) {
                jsonResponse(403, ['ok' => false, 'message' => 'Routine non accessibile.']);
            }
            RoutineModel::updateRoutineNotes($giorno, (string)($_POST['note'] ?? ''));
            jsonResponse(200, ['ok' => true]);
            break;

        case 'updateExerciseNotesRestVideo':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(405, ['ok' => false, 'message' => 'Metodo non consentito.']);
            }
            $id = (int)($_POST['idEsercizioGiorno'] ?? 0);
            if ($id < 1 || !RoutineModel::isExerciseDayOwnedByUser($id, $userId)) {
                jsonResponse(403, ['ok' => false, 'message' => 'Esercizio non accessibile.']);
            }
            RoutineModel::updateExerciseNotesRestVideo(
                $id,
                (string)($_POST['istruzioni'] ?? ''),
                (string)($_POST['urlVideo'] ?? '')
            );
            jsonResponse(200, ['ok' => true]);
            break;

        case 'removeSet':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(405, ['ok' => false, 'message' => 'Metodo non consentito.']);
            }
            $id = (int)($_POST['idEsercizioGiorno'] ?? 0);
            $numeroSerie = (int)($_POST['numeroSerie'] ?? 0);
            if ($id < 1 || $numeroSerie < 1 || !RoutineModel::isExerciseDayOwnedByUser($id, $userId)) {
                jsonResponse(422, ['ok' => false, 'message' => 'Dati serie non validi.']);
            }
            RoutineModel::removeSet($id, $numeroSerie);
            jsonResponse(200, ['ok' => true]);
            break;

        case 'addSet':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                jsonResponse(405, ['ok' => false, 'message' => 'Metodo non consentito.']);
            }
            $id = (int)($_POST['idEsercizioGiorno'] ?? 0);
            if ($id < 1 || !RoutineModel::isExerciseDayOwnedByUser($id, $userId)) {
                jsonResponse(422, ['ok' => false, 'message' => 'Esercizio non valido.']);
            }
            RoutineModel::addEmptySet($id);
            jsonResponse(201, ['ok' => true]);
            break;

        default:
            jsonResponse(400, ['ok' => false, 'message' => 'Action non valida.']);
    }
} catch (Throwable $e) {
    jsonResponse(500, ['ok' => false, 'message' => 'Errore interno routine controller.']);
}
