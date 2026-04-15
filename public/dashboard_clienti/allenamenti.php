<?php
require __DIR__ . '/common.php';

$folders = [];
$selectedFolderId = isset($_GET['cartella']) ? (int)$_GET['cartella'] : 0;
$selectedFolder = null;
$programs = [];
$clienteId = null;
$ptProfessionistaId = null;
$ptUserId = null;
$assignedByProgramId = [];
$assignedFolderIds = [];

if ($dbAvailable) {
  try {
    $clienteRow = Database::exec(
      'SELECT idCliente FROM Clienti WHERE idUtente = ? LIMIT 1',
      [(int)$user['idUtente']]
    )->fetch();

    if ($clienteRow) {
      $clienteId = (int)$clienteRow['idCliente'];

      $ptRow = Database::exec(
        "SELECT p.idProfessionista, p.idUtente
         FROM Associazioni a
         INNER JOIN Professionisti p ON p.idProfessionista = a.professionista
         WHERE a.cliente = ?
           AND a.tipoAssociazione = 'pt'
           AND a.attivaFlag = 1
         ORDER BY a.iniziataIl DESC
         LIMIT 1",
        [$clienteId]
      )->fetch();

      if ($ptRow) {
        $ptProfessionistaId = (int)$ptRow['idProfessionista'];
        $ptUserId = (int)$ptRow['idUtente'];
      }

      $assignedPrograms = Database::exec(
          "SELECT p.idProgramma, p.cartellaId, ap.assegnatoIl, ap.stato
           FROM AssegnazioniProgramma ap
           INNER JOIN ProgrammiAllenamento p ON p.idProgramma = ap.programma
           WHERE ap.cliente = ?
             AND (ap.stato = 'attivo' OR ap.stato IS NULL OR ap.stato = '')
             AND p.stato <> 'archiviato'
           ORDER BY ap.assegnatoIl DESC",
          [$clienteId]
        )->fetchAll();

      foreach ($assignedPrograms as $assignedProgram) {
          $programId = (int)$assignedProgram['idProgramma'];
          $folderId = isset($assignedProgram['cartellaId']) ? (int)$assignedProgram['cartellaId'] : 0;

          if (!isset($assignedByProgramId[$programId])) {
            $assignedByProgramId[$programId] = [
              'assegnatoIl' => (string)($assignedProgram['assegnatoIl'] ?? ''),
              'stato' => (string)($assignedProgram['stato'] ?? ''),
            ];
          }

          if ($folderId > 0) {
            $assignedFolderIds[$folderId] = true;
          }
        }

      if (!empty($assignedFolderIds)) {
          $folderIds = array_keys($assignedFolderIds);
          $placeholders = implode(',', array_fill(0, count($folderIds), '?'));
          $folders = Database::exec(
            "SELECT idCartella, nome, ordine
             FROM ProgrammiCartelle
             WHERE idCartella IN ($placeholders)
             ORDER BY ordine ASC, nome ASC",
            $folderIds
          )->fetchAll();
        }

      $hasProgramsWithoutFolder = false;
      foreach ($assignedPrograms as $assignedProgram) {
          $rawFolderId = isset($assignedProgram['cartellaId']) ? (int)$assignedProgram['cartellaId'] : 0;
          if ($rawFolderId <= 0) {
            $hasProgramsWithoutFolder = true;
            break;
          }
        }

      if ($hasProgramsWithoutFolder || (empty($folders) && !empty($assignedPrograms))) {
          array_unshift($folders, [
            'idCartella' => -1,
            'nome' => 'Schede assegnate',
            'ordine' => -1,
          ]);
        }
    }
  } catch (Throwable $e) {
    $folders = [];
    $assignedByProgramId = [];
    $assignedFolderIds = [];
  }
}

if ($selectedFolderId <= 0 && !empty($folders)) {
  $selectedFolderId = (int)$folders[0]['idCartella'];
}

foreach ($folders as $folder) {
  if ((int)$folder['idCartella'] === $selectedFolderId) {
    $selectedFolder = $folder;
    break;
  }
}

if (!$selectedFolder && !empty($folders)) {
  $selectedFolder = $folders[0];
  $selectedFolderId = (int)$selectedFolder['idCartella'];
}

if ($selectedFolder && $clienteId) {
  $selectedFolderFilterSql = '';
  $queryParams = [$clienteId];

  if ((int)$selectedFolder['idCartella'] > 0) {
    $selectedFolderFilterSql = ' AND p.cartellaId = ?';
    $queryParams[] = (int)$selectedFolder['idCartella'];
  }

  $programs = Database::exec(
    "SELECT p.idProgramma, p.titolo, p.descrizione, ap.assegnatoIl,
            (
              SELECT GROUP_CONCAT(e.nome ORDER BY g.ordine ASC, eg.ordine ASC SEPARATOR ', ')
              FROM GiorniAllenamento g
              INNER JOIN EserciziGiorno eg ON eg.giorno = g.idGiorno
              INNER JOIN Esercizi e ON e.idEsercizio = eg.esercizio
              WHERE g.programma = p.idProgramma
            ) AS previewEsercizi
     FROM ProgrammiAllenamento p
     INNER JOIN AssegnazioniProgramma ap ON ap.programma = p.idProgramma
     WHERE ap.cliente = ?
       AND (ap.stato = 'attivo' OR ap.stato IS NULL OR ap.stato = '')
       AND p.stato <> 'archiviato'" . $selectedFolderFilterSql . "
     ORDER BY ap.assegnatoIl DESC, p.idProgramma DESC",
    $queryParams
  )->fetchAll();
}

renderStart('Allenamenti cliente', 'allenamenti', $email);
?>
<link rel="stylesheet" href="../assets/css/allenamenti.css" />
<section class="card workout-shell">
  <div class="library-toolbar">
    <h2 class="section-title" style="margin:0">Le tue cartelle allenamento</h2>
  </div>

  <div class="folder-grid">
    <?php foreach ($folders as $folder): ?>
      <?php
        $folderId = (int)$folder['idCartella'];
        $isActive = ($folderId === (int)$selectedFolderId);
        $isAssignedFolder = isset($assignedFolderIds[$folderId]);
      ?>
      <a class="folder-card folder-link<?= $isActive ? ' active-folder' : '' ?><?= $isAssignedFolder ? ' assigned-folder' : '' ?>" href="allenamenti.php?cartella=<?= $folderId ?>">
        <strong>📁 <?= h((string)$folder['nome']) ?></strong>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if ($selectedFolder): ?>
    <div class="library-toolbar" style="margin-top:8px">
      <h3 class="section-title" style="margin:0">📁 <?= h((string)$selectedFolder['nome']) ?></h3>
    </div>

    <div class="program-grid">
      <?php if (!$programs): ?>
        <article class="program-card">
          <h4>Nessun programma disponibile</h4>
          <p class="muted-sm">In questa cartella non ci sono ancora programmi visibili.</p>
        </article>
      <?php endif; ?>

      <?php foreach ($programs as $program): ?>
        <?php
          $programId = (int)$program['idProgramma'];
          $assignment = $assignedByProgramId[$programId] ?? null;
          $exercisePreview = trim((string)($program['previewEsercizi'] ?? ''));
          if ($exercisePreview === '') {
            $exercisePreview = 'Nessun esercizio';
          } elseif (strlen($exercisePreview) > 90) {
            $exercisePreview = substr($exercisePreview, 0, 89) . '…';
          }
        ?>
        <article class="program-card<?= $assignment ? ' assigned-program-card' : '' ?>">
          <h4><?= h((string)$program['titolo']) ?></h4>
          <p class="muted-sm"><?= h((string)($program['descrizione'] ?? '')) ?></p>
          <div class="program-meta">
            <span><?= h($exercisePreview) ?></span>
            <?php if ($assignment): ?>
              <span class="status ok">Assegnato</span>
              <span><?= h((string)$assignment['assegnatoIl']) ?></span>
            <?php endif; ?>
          </div>
          <?php if ($assignment): ?>
            <div style="margin-top:10px">
              <a class="btn" href="scheda_assegnata.php?id=<?= $programId ?>">Visualizza scheda assegnata</a>
            </div>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <article class="program-card">
      <h4>Nessuna cartella disponibile</h4>
      <p class="muted-sm">Quando il tuo Personal Trainer condivide la libreria, troverai qui le cartelle.</p>
    </article>
  <?php endif; ?>
</section>

<style>
  .active-folder{border-color:rgba(134,195,255,.75);box-shadow:0 0 0 1px rgba(134,195,255,.35) inset}
  .assigned-folder{background:linear-gradient(135deg,rgba(52,173,121,.2),rgba(77,193,255,.15));border-color:rgba(94,222,171,.55)}
  .assigned-program-card{border-color:rgba(94,222,171,.45)}
</style>
<?php
renderEnd();
