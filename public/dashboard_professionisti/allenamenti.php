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
$programs = ProgrammiModel::listProgramTemplates($userId);

renderStart('Allenamenti', 'allenamenti', $email, $roleBadge, $isPt, $isNutrizionista);
?>
<link rel="stylesheet" href="../assets/css/allenamenti.css" />
<section class="card workout-shell">
  <div class="library-toolbar">
    <h2 class="section-title" style="margin:0">Program Library</h2>
  </div>

  <div class="library-toolbar">
    <form class="inline-form" data-folder-form>
      <input class="dark-input" name="nome" placeholder="Nuova cartella" required />
      <button class="btn" type="submit">New Folder</button>
    </form>

    <form class="inline-form" data-program-form>
      <input class="dark-input" name="titolo" placeholder="Titolo programma" required />
      <input class="dark-input" name="descrizione" placeholder="Descrizione" />
      <select class="dark-select" name="cartellaId">
        <option value="">Senza cartella</option>
        <?php foreach ($folders as $folder): ?>
          <option value="<?= (int)$folder['idCartella'] ?>"><?= h((string)$folder['nome']) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn primary" type="submit">Add Workout Program</button>
    </form>
  </div>

  <div class="folder-grid">
    <?php foreach ($folders as $folder): ?>
      <article class="folder-card">
        <strong>📁 <?= h((string)$folder['nome']) ?></strong>
      </article>
    <?php endforeach; ?>
  </div>

  <div class="program-grid">
    <?php foreach ($programs as $program): ?>
      <article class="program-card" data-open-program="<?= (int)$program['idProgramma'] ?>">
        <h4><?= h((string)$program['titolo']) ?></h4>
        <p class="muted-sm"><?= h((string)($program['descrizione'] ?? '')) ?></p>
        <div class="program-meta">
          <span><?= (int)$program['totaleGiorni'] ?> routine</span>
          <span><?= h((string)($program['cartellaNome'] ?? 'Senza cartella')) ?></span>
        </div>
      </article>
    <?php endforeach; ?>
    <article class="program-card add-program-card">
      <div>
        <div style="font-size:30px;text-align:center">＋</div>
        <div class="muted-sm">Crea nuovo programma</div>
      </div>
    </article>
  </div>
</section>
<?php
renderEnd('<script src="../assets/js/program_library.js"></script>');
