<?php
require __DIR__ . '/common.php';
require_once __DIR__ . '/../models/routine_model.php';

if (!$isPt) {
    header('Location: overview.php');
    exit;
}

$giornoId = (int)($_GET['giorno'] ?? 0);
if ($giornoId < 1 || !RoutineModel::isDayOwnedByUser($giornoId, $userId)) {
    header('Location: allenamenti.php');
    exit;
}

$routine = RoutineModel::getRoutineEditorData($giornoId, $userId);
if (!$routine) {
    header('Location: allenamenti.php');
    exit;
}

renderStart('Routine Editor', 'allenamenti', $email, $roleBadge, $isPt, $isNutrizionista);
?>
<link rel="stylesheet" href="../assets/css/allenamenti.css" />
<section class="card workout-shell" data-routine-editor data-giorno="<?= (int)$routine['idGiorno'] ?>">
  <div class="program-toolbar">
    <a href="programma.php?id=<?= (int)$routine['programma'] ?>" class="link-btn">← Programma</a>
    <h2 class="section-title" style="margin:0">Edit Routine · <?= h((string)$routine['nome']) ?></h2>
  </div>

  <div class="routine-layout">
    <div>
      <div class="field">
        <label>Routine note</label>
        <textarea class="dark-textarea" readonly><?= h((string)($routine['note'] ?? '')) ?></textarea>
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
<?php
renderEnd('<script src="../assets/js/routine_editor.js"></script>');
