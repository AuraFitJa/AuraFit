<?php
require __DIR__ . '/common.php';
require_once __DIR__ . '/../models/programmi_model.php';
require_once __DIR__ . '/../models/routine_model.php';

if (!$isPt) {
    header('Location: overview.php');
    exit;
}

$idProgramma = (int)($_GET['id'] ?? 0);
if ($idProgramma < 1) {
    header('Location: allenamenti.php');
    exit;
}

$program = ProgrammiModel::getProgramDetails($idProgramma, $userId);
if (!$program) {
    header('Location: allenamenti.php');
    exit;
}

$professionistaId = ProgrammiModel::getProfessionistaIdByUserId($userId);
$clients = $professionistaId ? ProgrammiModel::listPtClients($professionistaId) : [];

$programFolderId = (int)($program['cartellaId'] ?? 0);
$selectedFolderId = (int)($_GET['cartella'] ?? 0);
if ($selectedFolderId < 1 && $programFolderId > 0) {
    $selectedFolderId = $programFolderId;
}
$selectedGiornoId = (int)($_GET['giorno'] ?? 0);
$selectedRoutine = null;

if ($selectedGiornoId > 0 && RoutineModel::isDayOwnedByUser($selectedGiornoId, $userId)) {
    $candidateRoutine = RoutineModel::getRoutineEditorData($selectedGiornoId, $userId);
    if ($candidateRoutine && (int)$candidateRoutine['programma'] === (int)$program['idProgramma']) {
        $selectedRoutine = $candidateRoutine;
    }
}

if (!$selectedRoutine && !empty($program['giorni'])) {
    $firstDayId = (int)$program['giorni'][0]['idGiorno'];
    if ($firstDayId > 0) {
        $selectedRoutine = RoutineModel::getRoutineEditorData($firstDayId, $userId);
    }
}

$switchableDays = array_values(array_filter(
    $program['giorni'],
    static function (array $giorno) use ($selectedRoutine): bool {
        if (!$selectedRoutine) {
            return true;
        }

        return (int)$giorno['idGiorno'] !== (int)$selectedRoutine['idGiorno'];
    }
));

renderStart('Programma', 'allenamenti', $email, $roleBadge, $isPt, $isNutrizionista);
?>
<link rel="stylesheet" href="../assets/css/allenamenti.css" />
<section class="card workout-shell">
  <div class="program-toolbar">
    <a href="allenamenti.php<?= $selectedFolderId > 0 ? '?cartella=' . $selectedFolderId : '' ?>" class="link-btn">← Libreria</a>
    <h2 class="section-title" style="margin:0"><?= h((string)$program['titolo']) ?></h2>
    <button class="btn" data-duplicate-program="<?= (int)$program['idProgramma'] ?>">Duplica</button>
    <button class="btn danger" data-delete-program="<?= (int)$program['idProgramma'] ?>" data-folder-id="<?= (int)$selectedFolderId ?>">Elimina</button>
  </div>

  <p class="muted"><?= h((string)($program['descrizione'] ?? '')) ?></p>

  <?php if (!empty($switchableDays)): ?>
    <div class="day-list">
      <?php foreach ($switchableDays as $giorno): ?>
        <article class="day-card">
          <h3><?= h((string)$giorno['nome']) ?></h3>
          <p class="muted-sm"><?= h((string)($giorno['previewEsercizi'] ?? 'Nessun esercizio')) ?></p>
          <a class="btn" href="programma.php?id=<?= (int)$program['idProgramma'] ?>&cartella=<?= $selectedFolderId ?>&giorno=<?= (int)$giorno['idGiorno'] ?>">Apri workout builder</a>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($selectedRoutine): ?>
    <div class="divider"></div>

    <section class="card workout-shell" data-routine-editor data-giorno="<?= (int)$selectedRoutine['idGiorno'] ?>" style="padding:0;border:none;background:transparent;box-shadow:none">
      <div class="program-toolbar">
        <h3 class="section-title" style="margin:0">Workout Builder · <?= h((string)$selectedRoutine['nome']) ?></h3>
      </div>

      <div class="routine-layout">
        <div>
          <div class="field">
            <label>Routine note</label>
            <textarea class="dark-textarea" data-routine-note placeholder="Aggiungi note per questa routine..."><?= h((string)($selectedRoutine['note'] ?? '')) ?></textarea>
            <div class="library-toolbar" style="margin-top:.5rem">
              <button class="action-mini" type="button" data-save-routine-note>Salva note routine</button>
            </div>
          </div>
          <div data-exercise-list></div>
        </div>

        <aside class="picker">
          <h3 style="margin-top:0">Exercise Picker</h3>
          <input class="dark-input" data-exercise-search placeholder="Search exercise" />
          <div class="exercise-search-results" data-search-results></div>
        </aside>
      </div>
    </section>
  <?php endif; ?>

  <div class="divider"></div>

  <h3>Assegna a cliente</h3>
  <form class="inline-form" data-assign-form>
    <input type="hidden" name="idProgramma" value="<?= (int)$program['idProgramma'] ?>" />
    <select class="dark-select" name="idCliente" required>
      <option value="">Seleziona cliente</option>
      <?php foreach ($clients as $client): ?>
        <option value="<?= (int)$client['idCliente'] ?>"><?= h(trim((string)$client['nome'] . ' ' . (string)$client['cognome'])) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn primary" type="submit">Assegna programma</button>
  </form>

  <ul>
    <?php foreach ($program['assegnazioni'] as $assegnazione): ?>
      <li><?= h(trim((string)$assegnazione['nome'] . ' ' . (string)$assegnazione['cognome'])) ?> — <?= h((string)$assegnazione['stato']) ?></li>
    <?php endforeach; ?>
  </ul>
</section>

<div class="modal-layer" data-delete-program-modal>
  <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="delete-program-modal-title">
    <h3 id="delete-program-modal-title">Conferma eliminazione</h3>
    <p class="muted">Questa azione eliminerà definitivamente il programma. Vuoi continuare?</p>
    <div class="library-toolbar" style="justify-content:flex-end">
      <button class="btn" type="button" data-cancel-delete-program>Annulla</button>
      <button class="btn danger" type="button" data-confirm-delete-program>Elimina programma</button>
    </div>
  </div>
</div>
<?php
renderEnd('<script src="../assets/js/program_library.js"></script><script src="../assets/js/routine_editor.js"></script>');
