<?php
require __DIR__ . '/common.php';
require_once __DIR__ . '/../models/programmi_model.php';

if (!$isPt) {
    renderStart('Allenamenti', 'allenamenti', $email, $roleBadge, $isPt, $isNutrizionista);
    echo '<section class="card"><h2 class="section-title">Allenamenti (PT)</h2><p class="muted">Accesso disponibile solo per i Personal Trainer.</p></section>';
    renderEnd();
    exit;
}

$professionistaId = ProgrammiModel::getProfessionistaIdByUserId($userId);
$folders = $professionistaId ? ProgrammiModel::listFolders($professionistaId) : [];
$selectedFolderId = (int)($_GET['cartella'] ?? 0);
$selectedFolder = null;

foreach ($folders as $folder) {
    if ((int)$folder['idCartella'] === $selectedFolderId) {
        $selectedFolder = $folder;
        break;
    }
}

if ($selectedFolderId > 0 && !$selectedFolder) {
    header('Location: allenamenti.php');
    exit;
}

$programs = [];
if ($selectedFolder) {
    $allPrograms = ProgrammiModel::listProgramTemplates($userId);
    foreach ($allPrograms as $program) {
        if ((int)($program['cartellaId'] ?? 0) !== (int)$selectedFolder['idCartella']) {
            continue;
        }
        $programs[] = $program;
    }
}

renderStart('Allenamenti', 'allenamenti', $email, $roleBadge, $isPt, $isNutrizionista);
?>
<link rel="stylesheet" href="../assets/css/allenamenti.css" />
<section class="card workout-shell">
  <?php if (!$selectedFolder): ?>
    <div class="library-toolbar">
      <h2 class="section-title" style="margin:0">Program Library</h2>
    </div>

    <div class="folder-grid">
      <?php foreach ($folders as $folder): ?>
        <a class="folder-card folder-link" href="allenamenti.php?cartella=<?= (int)$folder['idCartella'] ?>">
          <strong>📁 <?= h((string)$folder['nome']) ?></strong>
        </a>
      <?php endforeach; ?>

      <button type="button" class="folder-card folder-create" data-open-folder-modal>
        <span class="create-plus">＋</span>
        <span class="muted-sm">Crea nuova cartella</span>
      </button>
    </div>
  <?php else: ?>
    <div class="library-toolbar">
      <a href="allenamenti.php" class="link-btn">← Torna alle cartelle</a>
      <h2 class="section-title" style="margin:0">📁 <?= h((string)$selectedFolder['nome']) ?></h2>
    </div>

    <div class="program-grid">
      <?php foreach ($programs as $program): ?>
        <article class="program-card" data-open-program="<?= (int)$program['idProgramma'] ?>" data-folder-id="<?= (int)$selectedFolder['idCartella'] ?>">
          <h4><?= h((string)$program['titolo']) ?></h4>
          <p class="muted-sm"><?= h((string)($program['descrizione'] ?? '')) ?></p>
          <div class="program-meta">
            <span><?= (int)$program['totaleGiorni'] ?> routine</span>
          </div>
        </article>
      <?php endforeach; ?>

      <button type="button" class="program-card add-program-card" data-open-program-modal data-folder-id="<?= (int)$selectedFolder['idCartella'] ?>">
        <div>
          <div class="create-plus">＋</div>
          <div class="muted-sm">Crea nuovo programma</div>
        </div>
      </button>
    </div>
  <?php endif; ?>
</section>

<div class="modal-layer" data-folder-modal>
  <div class="modal-card">
    <h3>Nuova cartella</h3>
    <form data-folder-form>
      <input class="dark-input" name="nome" placeholder="Titolo cartella" required />
      <div class="library-toolbar">
        <button class="btn primary" type="submit">Crea</button>
        <button class="btn" type="button" data-close-folder-modal>Annulla</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-layer" data-program-modal data-program-folder-id="<?= $selectedFolder ? (int)$selectedFolder['idCartella'] : 0 ?>">
  <div class="modal-card">
    <h3>Nuovo programma</h3>
    <form data-program-form>
      <input class="dark-input" name="titolo" placeholder="Titolo programma" required />
      <textarea class="dark-textarea" name="descrizione" placeholder="Descrizione"></textarea>
      <input type="hidden" name="cartellaId" value="<?= $selectedFolder ? (int)$selectedFolder['idCartella'] : 0 ?>" />
      <div class="library-toolbar">
        <button class="btn primary" type="submit">Crea programma</button>
        <button class="btn" type="button" data-close-program-modal>Annulla</button>
      </div>
    </form>
  </div>
</div>
<?php
renderEnd('<script src="../assets/js/program_library.js"></script>');
