<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/Models/SupportoModel.php';

$user = $_SESSION['user'] ?? null;
if (!$user && isset($_SESSION['idUtente'])) {
    $user = [
        'idUtente' => (int)$_SESSION['idUtente'],
        'email' => (string)($_SESSION['email'] ?? ''),
        'roles' => (array)($_SESSION['roles'] ?? []),
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Metodo non consentito.']);
    exit;
}

if (!$user || empty($user['idUtente'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Sessione non valida. Effettua il login.']);
    exit;
}

$roles = array_map('strtolower', (array)($user['roles'] ?? []));
if (!in_array('cliente', $roles, true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Accesso negato: solo i clienti possono usare questa funzione.']);
    exit;
}

$codiceRaw = trim((string)($_POST['codice'] ?? ''));
$codice = strtoupper($codiceRaw);

if ($codice === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Inserisci una ID-Key valida.']);
    exit;
}

try {
    $clienteId = SupportoModel::getClienteIdByUtenteId((int)$user['idUtente']);
    if (!$clienteId) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'message' => 'Profilo cliente non trovato per l’utente autenticato.']);
        exit;
    }

    $pdo = Database::pdo();
    $pdo->beginTransaction();

    $idKey = SupportoModel::findIdKeyByCode($codice, true);
    if (!$idKey) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'ID-Key non trovata. Verifica il codice inserito.']);
        exit;
    }

    $stato = strtolower((string)$idKey['stato']);
    if ($stato !== 'attiva') {
        $pdo->rollBack();
        $msg = 'ID-Key non utilizzabile.';
        if ($stato === 'sospesa') {
            $msg = 'Questa ID-Key è sospesa e non può essere utilizzata.';
        } elseif ($stato === 'eliminata') {
            $msg = 'Questa ID-Key è stata eliminata e non può essere utilizzata.';
        }
        http_response_code(409);
        echo json_encode(['ok' => false, 'message' => $msg]);
        exit;
    }

    $tipoKey = strtolower((string)$idKey['tipoKey']);
    if (!in_array($tipoKey, ['pt', 'nutrizionista'], true)) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['ok' => false, 'message' => 'Tipo ID-Key non supportato.']);
        exit;
    }

    if (!empty($idKey['clienteUtilizzatore'])) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['ok' => false, 'message' => 'Questa ID-Key risulta già utilizzata da un altro cliente.']);
        exit;
    }

    $associazionePrecedente = SupportoModel::getActiveAssociazione($clienteId, $tipoKey, true);
    if ($associazionePrecedente) {
        SupportoModel::terminateAssociazione((int)$associazionePrecedente['idAssociazione']);
    }

    $newAssociazioneId = SupportoModel::createAssociazione(
        $clienteId,
        (int)$idKey['professionista'],
        (int)$idKey['idKey'],
        $tipoKey
    );

    SupportoModel::markIdKeyUsed((int)$idKey['idKey'], $clienteId);
    SupportoModel::ensureChatForAssociazione($newAssociazioneId, $tipoKey);

    $pdo->commit();

    $associazioniAttive = SupportoModel::listAssociazioniAttiveCliente($clienteId);

    echo json_encode([
        'ok' => true,
        'message' => 'Professionista associato correttamente.',
        'associazioni' => $associazioniAttive,
        'archiviataPrecedente' => (bool)$associazionePrecedente,
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Errore durante l’associazione della ID-Key. Riprova.',
    ]);
}
